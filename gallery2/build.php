#!/usr/bin/php -f
<?php
error_reporting(E_ALL);
/* $TAG and $PATCH_FOR are not used for the nightlies */
$TAG = 'tags/RELEASE_2_2_6';
$PATCH_FOR = array('RELEASE_2_2', 'RELEASE_2_2_1', 'RELEASE_2_2_2', 'RELEASE_2_2_3', 'RELEASE_2_2_4', 'RELEASE_2_2_5');
$SVNURL = 'https://gallery.svn.sourceforge.net/svnroot/gallery/';
$BASEDIR = dirname(__FILE__);
$SRCDIR = $BASEDIR . '/src';
$TMPDIR = $BASEDIR . '/tmp';
$DISTDIR = $BASEDIR . '/dist';
$SKIP_CHECKOUT = false;

/**
 * Quiet makes all optional output quiet, but warnings are allowed to
 * make output.  That way cron jobs will only send an email if something
 * goes awry.
 */
$QUIET = false;

/**
 * Needed for the nightly.  Programmers should call getRevision() before
 * reading this variable.
 */
$REVISION = 0;

/**
 * This wrapper for chdir removes the need to manually check the result
 * of chdir every time.  When debugging the nightly build process,
 * failing immediately saved a lot of confusion.
 */
function req_chdir($dir) {
    if (!chdir($dir)) {
	my_die("Could not change to $dir\n");
    }
}

/**
 * Wrapping mkdir for the same reason -- fail early.
 */
function req_mkdir($dir) {
    if (!mkdir($dir)) {
	my_die("Could not make dir: $dir\n");
    }
}

/**
 * By default this is chatty to let you know exactly what is happening.
 * Again, we fail hard and early if anything goes wrong.
 */
function req_exec($cmd, $comment="") {
    quiet_print("Executing: $cmd");
    $result = 0;
    $output = array();
    exec($cmd, $output, $result);
    if ($result) {
	my_die("Command failed:\n$cmd\n" . join("\n", $output) . "\n$comment\n");
    }
    quiet_print("Done:      $cmd");
    return join("\n", $output);
}

/**
 * Non-error printing is all piped through this function.
 */
function quiet_print($string) {
    global $QUIET;
    if (empty($QUIET)) {
	print $string . "\n";
    }
}

/**
 * A wrapper for die()/exit() that helps to return an actual error code on error.
 */
function my_die($error = '', $returnCode = 1) {
    $returnCode = (int) $returnCode;
    if (!empty($error)) {
	print "$error\n";
    } else {
	print "An error occurred.\n";
    }
    die($returnCode);
}

/**
 * Check out code from svn unless $SKIP_CHECKOUT has been set to true.
 */
function checkOut($tag) {
    global $SRCDIR, $BASEDIR, $SVNURL, $SKIP_CHECKOUT, $QUIET;
    quiet_print("Checking out code...");
    req_chdir($SRCDIR);
    if ($SKIP_CHECKOUT) {
	quiet_print("Skipping checkout...");
    } else {
	$cmd = 'svn checkout ' . ($QUIET ? '-q ' : '') . $SVNURL . $tag . '/gallery2';
	req_exec($cmd, "Checkout failed.");
    }
    req_chdir($BASEDIR);
    quiet_print('Done.');
}

/**
 * Gets a revision number from the already checked out code and sets the global variable.
 */
function getRevision() {
    global $REVISION, $SRCDIR, $BASEDIR;
    req_chdir($SRCDIR . '/gallery2');
    $cmd = "svn info | awk '$1 == \"Revision:\" {print $2}'";
    $revision = req_exec($cmd, "Getting revision failed");
    if (!is_numeric($revision) || 1 > $revision) {
	my_die("The revision number $revision does not appear to be valid.\n");
    }
    $REVISION = (int) trim($revision);
    req_chdir($BASEDIR);
}

function getPackages() {
    global $SRCDIR;

    foreach (glob("$SRCDIR/gallery2/modules/*/module.inc") as $path) {
	$id = basename(dirname($path));
	$code = file_get_contents($path);

	/* Get the version */
	preg_match('/\$this->setVersion\(\'(.*?)\'\)/', $code, $matches);
	$packages['modules'][$id]['version'] = $matches[1];

	if ($id == 'core') {
	    preg_match('/\$this->setGalleryVersion\(\'(.*?)\'\)/', $code, $matches);
	    $packages['version'] = $matches[1];
	    continue;
	}

	$packages['all']['modules'][$id] = true;
	$packages['all-en']['modules'][$id] = true;
	$typicalModules = in_array($id, array('imagemagick', 'netpbm', 'gd', 'ffmpeg', 'rating',
				'archiveupload', 'comment', 'exif', 'icons', 'keyalbum',
				'rearrange', 'rewrite', 'search', 'shutterfly', 'slideshow'));
	$packages['typical']['modules'][$id] = $typicalModules;
	$packages['typical-en']['modules'][$id] = $typicalModules;
	$packages['minimal']['modules'][$id] =
	    in_array($id, array('imagemagick', 'netpbm', 'gd'));
    }

    foreach (glob("$SRCDIR/gallery2/themes/*/theme.inc") as $path) {
	$id = basename(dirname($path));
	$code = file_get_contents($path);

	/* Get the version */
	preg_match('/\$this->setVersion\(\'(.*?)\'\)/', $code, $matches);
	$packages['themes'][$id]['version'] = $matches[1];

	$packages['all']['themes'][$id] = true;
	$packages['all-en']['themes'][$id] = true;
	$packages['typical']['themes'][$id] = !in_array($id, array('tile'));
	$packages['typical-en']['themes'][$id] = !in_array($id, array('tile'));
	$packages['minimal']['themes'][$id] = in_array($id, array('matrix', 'siriux'));
    }

    return $packages;
}

function buildPluginPackage($type, $id, $version) {
    global $BASEDIR, $SRCDIR, $TMPDIR, $DISTDIR;
    quiet_print("Build plugin $id ($version)...");
    req_chdir("$SRCDIR/gallery2");

    $relative = "${type}s/$id";
    $fileList = req_exec("find $relative -name .svn -prune -o -type f -print");
    $files = explode("\n", $fileList);

    /* Dump the list to a tmp file */
    $fd = fopen("$TMPDIR/files.txt", 'w+');
    fwrite($fd, join("\n", $files));
    fclose($fd);

    /* Tar and zip it */
    $cmd = "tar czf $DISTDIR/g2-$type-$id-$version.tar.gz --files-from=$TMPDIR/files.txt";
    req_exec($cmd, "Tar Failed");
    escapePatterns("$TMPDIR/files.txt", "$TMPDIR/escapedFiles.txt");
    $cmd = "zip -9 -q -r $DISTDIR/g2-$type-$id-$version.zip ${type}s/$id -i@$TMPDIR/escapedFiles.txt";
    req_exec($cmd, "Zip failed.");

    unlink("$TMPDIR/files.txt");
    unlink("$TMPDIR/escapedFiles.txt");
    req_chdir($BASEDIR);
    quiet_print('Done.');
}

function buildPackage($version, $tag, $packages, $developer) {
    global $BASEDIR, $SRCDIR, $TMPDIR, $DISTDIR;
    quiet_print("Build $tag of $version...");

    /* Get all files */
    req_chdir($SRCDIR);
    $fileList = req_exec("find gallery2 -name .svn -prune -o -type f -print");
    $originalFiles = $files = explode("\n", $fileList);

    /* Pull all non developer files, if necessary */
    if (!$developer) {
	$files = preg_grep('|gallery2/modules/\w+/test/|', $files, PREG_GREP_INVERT);
	$files = preg_grep('|gallery2/lib/tools/(?!po/)|', $files, PREG_GREP_INVERT);
    }

    /* The core package is always included */
    $packageCopy = $packages;
    $packageCopy['modules']['core'] = true;

    /* Pull all modules and themes that shouldn't be in this distro */
    foreach (array('modules', 'themes') as $pluginType ) {
	foreach ($packageCopy[$pluginType] as $id => $include) {
	    if (!$include) {
		$files = preg_grep("|gallery2/$pluginType/$id/|", $files, PREG_GREP_INVERT);
	    } else if (in_array($tag, array('minimal', 'typical-en', 'full-en'))) {
		$files =
		    preg_grep("|gallery2/$pluginType/$id/po/\w+\.mo|", $files, PREG_GREP_INVERT);
		$files =
		    preg_grep("|gallery2/$pluginType/$id/po/\w+\.po|", $files, PREG_GREP_INVERT);
	    }
	}
    }
    /* Dump the list to a tmp file */
    $fd = fopen("$TMPDIR/files.txt", 'w+');
    fwrite($fd, join("\n", $files));
    fclose($fd);

    /* Copy our chosen files to our tmp dir */
    if (file_exists("$TMPDIR/gallery2")) {
	req_exec("rm -rf $TMPDIR/gallery2");
    }
    req_mkdir("$TMPDIR/gallery2");

    req_chdir($SRCDIR);
    req_exec("tar cf - --files-from=$TMPDIR/files.txt  | (cd $TMPDIR && tar xf -)", "Temporary copy via tar failed.");

    /* Update manifests to reflect files we've removed */
    req_chdir($TMPDIR);
    filterManifests($originalFiles, $files);

    /* Tar and zip it */
    if (!empty($version) && !empty($tag)) {
	$basename = "gallery-$version-$tag";
    } elseif (!empty($version)) {
	$basename = "gallery-$version";
    } elseif (!empty($tag)) {
	$basename = "gallery-$tag";
    } else {
	$basename = "gallery";
    }

    $cmd = "tar czf $DISTDIR/$basename.tar.gz --files-from=$TMPDIR/files.txt";
    req_exec($cmd, "Tar failed.");

    escapePatterns("$TMPDIR/files.txt", "$TMPDIR/escapedFiles.txt");
    $cmd = "zip -9 -q -r $DISTDIR/$basename.zip gallery2 -i@$TMPDIR/escapedFiles.txt";
    req_exec($cmd, "Zip failed");

    unlink("$TMPDIR/files.txt");
    unlink("$TMPDIR/escapedFiles.txt");
    req_chdir($BASEDIR);
    quiet_print('Done.');
}

function filterManifests($originalFiles, $files) {
    foreach (preg_grep('|/MANIFEST$|', $files) as $manifest) {
        if (preg_match('|test/data/MANIFEST|', $manifest)) {
	    continue;
	}
	if (!($fd = fopen("$manifest.new", "w"))) {
	    my_die("Error opening $manifest.new for write");
	}
	foreach (file($manifest) as $line) {
	    if (!preg_match("{^(#|R\t)}", $line)) {
		$split = explode("\t", $line);
		$file = 'gallery2/' . $split[0];
		if (!in_array($file, $originalFiles)) {
		    my_die("Unexpected file <$file>");
		}
		if (!in_array($file, $files)) {
		    continue;
		}
	    }
	    fwrite($fd, $line);
	}
	fclose($fd);
	if (filesize("$manifest.new") != filesize($manifest)) {
	    rename("$manifest.new", $manifest);
	} else {
	    unlink("$manifest.new");
	}
    }
}

function escapePatterns($infile, $outfile) {
    $fd = fopen($outfile, "w");
    foreach (file($infile) as $line) {
	fwrite($fd, preg_quote($line));
    }
    fclose($fd);
}

function buildPatch($patchFromTag) {
    global $TMPDIR, $SRCDIR, $DISTDIR, $BASEDIR, $SVNURL, $TAG;
    quiet_print("Build patch for $patchFromTag...");
    $finalPackage = array();

    $fromVersionTag = extractVersionTag($patchFromTag);
    $toVersionTag = extractVersionTag($TAG);

    ob_start();
    include(dirname(__FILE__) . '/patch-README.txt.inc');
    $readmeText = ob_get_contents();
    ob_end_clean();

    $patchDir = "$TMPDIR/$fromVersionTag";
    if (!file_exists($patchDir)) {
	@req_mkdir($patchDir);
    }
    $patchTmp = "$patchDir/patch-$fromVersionTag.txt";

    $readme = fopen("$patchDir/README.txt", "wb");
    fwrite($readme, $readmeText);
    fclose($readme);
    $finalPackage['README.txt'] = 1;

    /*
     * We want to drop all unit test and translation related lines from the diff because the user
     * may not have those files so we can't patch them.  This means that we also need to drop those
     * lines from the MANIFEST diffs.  Generate the diff and then postprocess for these changes.
     */
    if (file_exists("$patchTmp.raw")) {
	quiet_print("Skipping svn diff because $patchTmp already exists");
    } else {
	$SVN_DIFF = "svn diff ${SVNURL}tags/$patchFromTag/gallery2 $SVNURL$TAG/gallery2";
	req_exec("$SVN_DIFF > $patchTmp.raw", 'Making raw patch failed.');
    }

    $manifest = array();
    foreach ($patchLines = file("$patchTmp.raw") as $i => $line) {
	if (!strncmp($line, 'Property changes on:', 20)) {
	    $skipDiff = true;
	} else if (!strncmp($line, 'Index: ', 7) && !strncmp($patchLines[$i + 1], '=======', 7)) {
	    $changedFile = rtrim(substr($line, 7));
	    $isManifest = (substr($changedFile, -8) == 'MANIFEST');
	    $skipDiff = preg_match('{^lib/tools/|/test/phpunit/|/po/|locale/.*\.mo$}',
				   $changedFile);
	    preg_match('{^(?:modules|themes)/(.*?)/}', $changedFile, $matches);
	    $patchToken = empty($matches) ? 'core' : $matches[1];
	    if (!$skipDiff) {
		if (!isset($patchFD[$patchToken])) {
		    $patchFD[$patchToken] = fopen("$patchDir/patch-$patchToken.txt", 'w');

		    /*
		     * Leave the patch files out of the final zip file for now, since they have
		     * some known issues.  The biggest problem with them right now is that we
		     * can't filter the MANIFEST diff properly without doing it manually which is
		     * an ever-increasingly expensive operation as we do more patches.
		     *
		     * For reference, the problem with filtering MANIFEST diffs is that they will
		     * have hunks that contain test and .po/.mo files.  You can't just remove
		     * those lines from the hunk because the hunk may contain legitimate changes
		     * that bracket the ones you're removing so they have to be there for context,
		     * or the patch will fail.  One approach is to convert the remove lines (the
		     * ones that start with '-') to context lines by changing the '-' to a ' '.
		     * Then delete the matching '+' lines.  This leaves the line counts in the
		     * hunk alone, except in the case where there's a + without a -.  In those
		     * cases we can manually intervene (but that's the rare case).
		     *
		     * The problem with this approach is that if you have a run of - and + lines
		     * that contains both lines you don't want to change and lines that you do
		     * want to change, you'll wind up in a situation where the + line that you
		     * keep is in the wrong position.  See http://tools.gallery2.org/pastebin/1810
		     * for an example of this.
		     *
		     * This is fixable, but it's not fixed yet.  So for now, let's leave patches
		     * out of the equation.  Uncomment the line below when this is fixed.
		     */
		    /*
		    $finalPackage["patch-$patchToken.txt"] = 1;
		    */
		}
		$fd = $patchFD[$patchToken];
	    }
	    req_exec('mkdir -p ' . ($dir = "$patchDir/$patchToken/" . dirname($changedFile)));
	    if ($isManifest) {
		/* Filter out test files so we don't pollute non-dev dists with dev data */
		$lines = preg_grep('{^(modules/\w+/test/|lib/tools)}',
				   file("$SRCDIR/gallery2/$changedFile"), PREG_GREP_INVERT);
		$new = fopen("$patchDir/$patchToken/$changedFile", 'wb');
		fwrite($new, implode('', $lines));
		fclose($new);
	    } else {
		req_exec("cp $SRCDIR/gallery2/$changedFile $dir");
	    }
	}
	if ($skipDiff) {
	    continue;
	}
	if ($isManifest) {
	    $manifest[] = $line;
	    if (($line{0} == '-' || $line{0} == '+') && count($manifest) > 5) {
		if (preg_match('{^[-+](?:lib/tools/|.*/test/phpunit/|.*/po/|.*locale/.*\.mo\s)}',
			       $line)) {
		    $gotLineToRemove = true;
		} else {
		    $lastStart = 0;
		}
	    }
	    $end = ($i + 1 == count($patchLines) || !strncmp($patchLines[$i + 1], 'Index: ', 7));
	    if ($end || !strncmp($patchLines[$i + 1], '@@', 2)) {
		if (!empty($gotLineToRemove)) {
		    /* End of one @@ diff section that contains lines we want to omit */
		    if ($lastStart) {
			/* Section was clean, remove it */
			array_splice($manifest, $lastStart);
		    } else {
			/* Uh oh, section also had a diff we want to keep.. manual fix needed */
			$manifest[] = "^FIXME^\n";
			print "\nWARNING: Unable to automatically process $changedFile diffs\n";
			print "Need to manually edit FIXME sections\n\n";
		    }
		}
		$lastStart = count($manifest);
		$gotLineToRemove = false;
	    }
	    if ($end) {
		if (count($manifest) > 5) {
		    fwrite($fd, implode('', $manifest));
		}
		$manifest = array();
	    }
	    continue;
	}
	fwrite($fd, $line);
    }

    foreach ($patchFD as $plugin => $fd) {
	fclose($fd);
	req_chdir("$patchDir/$plugin");
	req_exec("zip -q -r ../changed-files-$plugin.zip *", "Making zip for $plugin failed.");
	$finalPackage["changed-files-$plugin.zip"] = 1;
    }
    @unlink($patchTmp);

    req_chdir($patchDir);
    req_exec(sprintf("zip -9 -q -r %s/update-$fromVersionTag-to-$toVersionTag.zip %s",
		     $DISTDIR, implode(' ', array_keys($finalPackage))));

    #system("/bin/rm -rf $patchDir");
    quiet_print('Done.');
}

function extractVersionTag($input) {
    $input = preg_replace('/^.*(?:RELEASE|BRANCH)_(.*)/', '$1', $input);
    return str_replace('_', '.', $input);
}

function buildPreinstaller($version) {
    global $DISTDIR;
    global $QUIET;
    req_exec("svn "
	     . ($QUIET ? " -q" : " ")
	     . " update preinstaller");
    req_exec("zip -9 -j -q $DISTDIR/preinstaller-$version.zip " .
	   "preinstaller/LICENSE preinstaller/README.txt preinstaller/preinstall.php");
}

function usage() {
    return "usage: build.php <cmd>\n" .
	"command is one of nightly, quietnightly, release, preinstaller, patches, export, scrub, clean\n";
}

/**
 * Moved into a function so that we could force a scrub and clean before the nightly.
 */
function scrub() {
    global $SRCDIR, $SKIP_CHECKOUT;
    if (!$SKIP_CHECKOUT) {
	quiet_print('Scrubbing.');
        req_exec("rm -rf $SRCDIR");
    }
}

/**
 * Moved into a function so that we could force a scrub and clean before the nightly.
 */
function clean() {
    global $TMPDIR, $DISTDIR;
    quiet_print('Cleaning.');
    req_exec("rm -rf $TMPDIR $DISTDIR");
}

/**
 * This function creates directories as needed and verifies that they have
 * the appropriate permissions.
 */
function verify_dirs() {
    global $TMPDIR, $SRCDIR, $DISTDIR;
    foreach (array($TMPDIR, $SRCDIR, $DISTDIR) as $dir) {
	if (!file_exists($dir)) {
	    req_mkdir($dir);
	}
	if (!is_readable($dir)) {
	    my_die("Cannot read $dir\n");
	}
	if (!is_dir($dir)) {
	    my_die("$dir is not a directory.");
	}
    }
}

function alreadyPublished($file) {
    global $DISTDIR;
    global $TMPDIR;
    static $data;

    if (!isset($data)) {
	$downloads_html = $TMPDIR . '/downloads.html';
	if (!file_exists($downloads_html)) {
	    req_exec("wget -q -O $downloads_html http://prdownloads.sf.net/gallery");
	}

	$lines = file($downloads_html);
	$conflicts = 0;
	foreach ($lines as $line) {
	    if (preg_match('|href="[^"]*/gallery/(.*?)[?"]|', $line, $matches)) {
		$tmp = $matches[1];
		if ($tmp == '..') {
		    continue;
		}
		$data[$tmp] = 1;
	    }
	}
    }

    return !empty($data[$file]);
}


if ($argc < 2) {
    print usage();
    exit(0); /* not really an error condition */
} else {
    quiet_print('Doing ' . $argv[1]);
}
switch ($argv[1]) {
case 'preinstaller':
    verify_dirs();
    $packages = getPackages();
    buildPreinstaller($packages['version']);
    break;

case 'quietnightly':
    /* We just set the quiet flag and fall through to the nightly case */
    $QUIET = true;
case 'nightly':
    clean();
    scrub();
    verify_dirs();
    checkOut('trunk');
    getRevision();
    require $SRCDIR . '/gallery2/lib/tools/bin/makeManifest.php';
    makeManifest();
    $packages = getPackages();
    buildPackage('nightly', '', $packages['all'], false);
    buildPreinstaller($packages['version']);
    break;

case 'release':
    /*
     * Note: Don't build the manifests for final releases.  When we do a
     * release, the manifests should be up to date in SVN.  If something
     * has gone wrong and we're divergent from SVN then building the
     * MANIFESTs here will obscure that.
     */
    verify_dirs();
    checkOut($TAG);
    $packages = getPackages();
    buildPackage($packages['version'], 'minimal', $packages['minimal'], false);
    buildPackage($packages['version'], 'typical', $packages['typical'], false);
    buildPackage($packages['version'], 'typical-en', $packages['typical-en'], false);
    buildPackage($packages['version'], 'full', $packages['all'], false);
    buildPackage($packages['version'], 'full-en', $packages['all-en'], false);
    buildPackage($packages['version'], 'developer', $packages['all'], true);

    foreach ($packages['themes'] as $id => $info) {
	buildPluginPackage('theme', $id, $info['version']);
    }

    foreach ($packages['modules'] as $id => $info) {
	if ($id == 'core') {
	    continue;
	}
	buildPluginPackage('module', $id, $info['version']);
    }
    /* fall through and build patches also */

case 'patches':
    verify_dirs();
    if ($argv[1] == 'patches') {
	checkOut($TAG);
    }
    if (!empty($PATCH_FOR)) {
	foreach ($PATCH_FOR as $patchFromTag) {
	    buildPatch($patchFromTag);
	}
    }
    break;

case 'export':
    verify_dirs();
    foreach (glob("$DISTDIR/*.{tar.gz,zip}", GLOB_BRACE) as $file) {
	$file = basename($file);
	if (!alreadyPublished($file)) {
	    $files[] = $file;
	} else print "Skipping $file\n";
    }

    req_chdir($DISTDIR);
    $cmd = 'ncftpput -u anonymous -p gallery@ upload.sourceforge.net /incoming '
	    . join(' ', $files);
    req_exec($cmd, "Export failed.");
    req_chdir($BASEDIR);
    break;

case 'scrub':
    scrub();
    /* Fall through to the 'clean' target */
case 'clean':
    clean();
    break;

default:
    print usage();
    exit;
}

?>
