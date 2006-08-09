#!/usr/bin/php -f
<?php
error_reporting(E_ALL);
/**
 * $TAG and $PATCH_FOR are not used for the nightlies
 */
$TAG = 'RELEASE_2_1_2';
$PATCH_FOR = array('RELEASE_2_1', 'RELEASE_2_1_1');
$SVNURL = 'https://svn.sourceforge.net/svnroot/gallery/';
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
 * This wrapper for chdir removes the need to manually check the result
 * of chdir every time.  When debugging the nightly build process,
 * failing immediately saved a lot of confusion.
 */
function req_chdir($dir) {
    chdir($dir) || die("Could not change to $dir\n");
}
/**
 * Wrapping mkdir for the same reason -- fail early.
 */
function req_mkdir($dir) {
    mkdir($dir) || die("Could not make dir: $dir\n");
}
/**
 * By default this is chatty to let you know exactly what is happening.
 * Again, we fail hard and early if anything goes wrong.
 */
function req_system($cmd, $comment="") {
    global $QUIET;
    if (!$QUIET) {
	print "Executing: $cmd\n";
    }
    $result = 0;
    system($cmd, $result);
    if ($result) {
	die("Command failed:\n$cmd\n$comment\n");
    }
    if (!$QUIET) {
	print "Done.\n";
    }
}
function checkOut($useTag=true) {
    global $SRCDIR, $BASEDIR, $SVNURL, $TAG, $SKIP_CHECKOUT, $QUIET;

    print 'Checking out code...';

    req_chdir($SRCDIR);
    if ($SKIP_CHECKOUT) {
	if (!$QUIET) {
	    print "Skipping checkout...\n";
	}
    } else {
	$cmd = 'svn checkout '
		. ($QUIET ? '-q ' : '')
		. $SVNURL
		. ($useTag ? "tags/$TAG" : 'trunk')
		. '/gallery2';
	req_system($cmd, "Checkout failed.");
    }
    req_chdir($BASEDIR);
    if (!$QUIET) {
	print "done.\n";
    }
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
	$packages['recommended']['modules'][$id] =
	    in_array($id, array('imagemagick', 'netpbm', 'gd', 'ffmpeg', 'rating',
				'archiveupload', 'comment', 'exif', 'icons', 'migrate',
				'rearrange', 'rewrite', 'search', 'shutterfly', 'slideshow'));
	$packages['core']['modules'][$id] =
	    in_array($id, array('imagemagick', 'netpbm', 'gd'));
    }

    foreach (glob("$SRCDIR/gallery2/themes/*/theme.inc") as $path) {
	$id = basename(dirname($path));
	$code = file_get_contents($path);

	/* Get the version */
	preg_match('/\$this->setVersion\(\'(.*?)\'\)/', $code, $matches);
	$packages['themes'][$id]['version'] = $matches[1];

	$packages['all']['themes'][$id] = true;
	$packages['recommended']['themes'][$id] = true;
	$packages['core']['themes'][$id] = in_array($id, array('matrix', 'siriux'));
    }

    return $packages;
}

function buildPluginPackage($type, $id, $version) {
    global $BASEDIR, $SRCDIR, $TMPDIR, $DISTDIR, $QUIET;
    if (!$QUIET) {
	print "Build plugin $id ($version)...\n";
    }
    req_chdir("$SRCDIR/gallery2");

    $relative = "${type}s/$id";
    $files = explode("\n", `find $relative -name .svn -prune -o -type f -print`);

    /* Dump the list to a tmp file */
    $fd = fopen("$TMPDIR/files.txt", 'w+');
    fwrite($fd, join("\n", $files));
    fclose($fd);

    /* Tar and zip it */
    $cmd = "tar czf $DISTDIR/g2-$type-$id-$version.tar.gz --files-from=$TMPDIR/files.txt";
    req_system($cmd, "Tar Failed");
    escapePatterns("$TMPDIR/files.txt", "$TMPDIR/escapedFiles.txt");
    $cmd = "zip -9 -q -r $DISTDIR/g2-$type-$id-$version.zip ${type}s/$id -i@$TMPDIR/escapedFiles.txt";
    req_system($cmd, "Zip failed.");

    unlink("$TMPDIR/files.txt");
    unlink("$TMPDIR/escapedFiles.txt");
    req_chdir($BASEDIR);
    if (!$QUIET) {
	print "done\n";
    }
}

function buildPackage($version, $tag, $packages, $developer) {
    global $BASEDIR, $SRCDIR, $TMPDIR, $DISTDIR, $QUIET;
    if (!$QUIET) {
	print "Build $tag of $version...\n";
    }

    /* Get all files */
    req_chdir($SRCDIR);
    $originalFiles = $files = explode("\n", `find gallery2 -name .svn -prune -o -type f -print`);

    /* Pull all non developer files, if necessary */
    if (!$developer) {
	$files = preg_grep('|gallery2/modules/\w+/test/|', $files, PREG_GREP_INVERT);
	$files = preg_grep('|gallery2/lib/tools/|', $files, PREG_GREP_INVERT);
    }

    /* Pull all modules that shouldn't be in this distro */
    foreach ($packages['modules'] as $id => $include) {
	if (!$include) {
	    $files = preg_grep("|gallery2/modules/$id/|", $files, PREG_GREP_INVERT);
	}
    }

    /* Pull all themes that shouldn't be in this distro */
    foreach ($packages['themes'] as $id => $include) {
	if (!$include) {
	    $files = preg_grep("|gallery2/themes/$id/|", $files, PREG_GREP_INVERT);
	}
    }

    /* Dump the list to a tmp file */
    $fd = fopen("$TMPDIR/files.txt", 'w+');
    fwrite($fd, join("\n", $files));
    fclose($fd);

    /* Copy our chosen files to our tmp dir */
    if (file_exists("$TMPDIR/gallery2")) {
	req_system("rm -rf $TMPDIR/gallery2");
    }
    req_mkdir("$TMPDIR/gallery2");

    $cmd = "(cd $SRCDIR && tar cf - --files-from=$TMPDIR/files.txt) | "
	    . "(cd $TMPDIR && tar xf -)";
    req_system($cmd, "Temporary copy via tar failed.");

    /* Update manifests to reflect files we've removed */
    req_chdir($TMPDIR);
    filterManifests($originalFiles, $files);

    /* Tar and zip it */
    $cmd = "tar czf $DISTDIR/gallery-$version-$tag.tar.gz --files-from=$TMPDIR/files.txt";
    req_system($cmd, "Tar failed.");

    escapePatterns("$TMPDIR/files.txt", "$TMPDIR/escapedFiles.txt");
    $cmd = "zip -q -r $DISTDIR/gallery-$version-$tag.zip gallery2 -i@$TMPDIR/escapedFiles.txt";
    req_system($cmd, "Zip failed");

    unlink("$TMPDIR/files.txt");
    unlink("$TMPDIR/escapedFiles.txt");
    req_chdir($BASEDIR);
    if (!$QUIET) {
	print "done\n";
    }
}

function filterManifests($originalFiles, $files) {
    foreach (preg_grep('|/MANIFEST$|', $files) as $manifest) {
	if (!($fd = fopen("$manifest.new", "w"))) {
	    die("Error opening $manifest.new for write");
	}
	foreach (file($manifest) as $line) {
	    if (!preg_match("{^(#|R\t)}", $line)) {
		$split = explode("\t", $line);
		$file = 'gallery2/' . $split[0];
		if (!in_array($file, $originalFiles)) {
		    die("Unexpected file <$file>");
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

function buildManifest() {
    global $SRCDIR, $BASEDIR, $QUIET;
    req_chdir("$SRCDIR/gallery2");
    if (!$QUIET) {
	req_system("perl lib/tools/bin/makeManifest.pl", "Build Manifest Failed.");
    } else {
	req_system("perl lib/tools/bin/makeManifest.pl -q", "Build Quiet Manifest Failed.");
    }
    req_chdir($BASEDIR);
}

function buildPatch($patchFromTag) {
    global $TMPDIR, $SRCDIR, $BASEDIR, $TAG, $QUIET;
    if (!$QUIET) {
	print "Build patch for $patchFromTag...\n";
    }
    $finalPackage = array();

    $fromVersionTag = extractVersionTag($patchFromTag);
    $toVersionTag = extractVersionTag($TAG);

    ob_start();
    include(dirname(__FILE__) . '/patch-README.txt.inc');
    $readmeText = ob_get_contents();
    ob_end_clean();

    $patchDir = "$TMPDIR/$fromVersionTag";
    @req_mkdir($patchDir);
    $patchTmp = "$patchDir/patch-$fromVersionTag.txt";

    $readme = fopen("$patchDir/README.txt", "wb");
    fwrite($readme, $readmeText);
    fclose($readme);
    $finalPackage['README.txt'] = 1;

    /*
     * We want to drop all XxxTest.class related lines from the diff because the user may not have
     * unit tests so we can't patch them.  This means that we also need to drop those lines from
     * the MANIFEST diffs.  Generate the diff and then postprocess for these changes.
     */
    $SVN_DIFF = 'svn diff https://svn.sourceforge.net/svnroot/gallery/tags/' . $patchFromTag
	. '/gallery2 https://svn.sourceforge.net/svnroot/gallery/tags/' . $TAG . '/gallery2';
    req_system("$SVN_DIFF > $patchTmp.raw", 'Making raw patch failed.');

    $manifest = array();
    foreach ($patchLines = file("$patchTmp.raw") as $i => $line) {
	if (substr($line, 0, 7) == 'Index: ' && substr($patchLines[$i + 1], 0, 7) == '=======') {
	    $changedFile = rtrim(substr($line, 7));
	    $isManifest = (substr($changedFile, -8) == 'MANIFEST');
	    $skipDiff = (!strncmp($changedFile, 'lib/tools/', 10)
		    || preg_match('{(?:Test.class|po/.*\.po|locale/.*\.mo)$}', $changedFile));

	    preg_match('{^(?:modules|themes)/(.*?)/}', $changedFile, $matches);
	    $patchToken = empty($matches) ? 'core' : $matches[1];
	    if (!$skipDiff) {
		if (!isset($patchFD[$patchToken])) {
		    $patchFD[$patchToken] = fopen("$patchDir/patch-$patchToken.txt", 'w');
		    $finalPackage["patch-$patchToken.txt"] = 1;
		}
		$fd = $patchFD[$patchToken];
	    }

	    req_system('mkdir -p ' . ($dir = "$patchDir/$patchToken/" . dirname($changedFile)));
	    if ($isManifest) {
		/* Filter out test files so we don't pollute non-dev dists with dev data */
		$lines = preg_grep('{^(modules/\w+/test/|lib/tools)}',
				   file("$SRCDIR/gallery2/$changedFile"), PREG_GREP_INVERT);
		$new = fopen("$patchDir/$patchToken/$changedFile", 'wb');
		fwrite($new, implode('', $lines));
		fclose($new);
	    } else {
		req_system("cp $SRCDIR/gallery2/$changedFile $dir");
	    }
	}
	if ($skipDiff) {
	    continue;
	}
	if ($isManifest) {
	    $manifest[] = $line;
	    if (($line{0} == '-' || $line{0} == '+') && count($manifest) > 5) {
		if (preg_match('{^[-+](?:lib/tools/|.*Test.class\s)}', $line)) {
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
			print "\nWARNING: Unable to automatically process $changedFile diffs\n\n";
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
	req_system("zip -q -r ../changed-files-$plugin.zip *", "Making zip for $plugin failed.");
	$finalPackage["changed-files-$plugin.zip"] = 1;
    }
    @unlink($patchTmp);

    /*
     * Due to some weirdness in the way that we deal with modules/exif/lib/JPEG/JPEG.inc
     * caused (I think) by the fact that it gained a -kb sticky bit, we generate a
     * MANIFEST-only patch for the exif module that leaves the expected size of this file
     * in the MANIFEST out of sync with the actual file size unless we replace it.  The
     * easiest thing to do is to just drop those changes from releases that don't need it.
     */
    unset($finalPackage["changed-files-exif.zip"]);
    unset($finalPackage["patch-exif.txt"]);

    req_chdir($patchDir);
    req_system(sprintf("zip -q -r ../update-$fromVersionTag-to-$toVersionTag.zip %s",
		       implode(' ', array_keys($finalPackage))));

    #system("/bin/rm -rf $patchDir");
    if (!$QUIET) {
	print "done\n";
    }
}

function extractVersionTag($input) {
    $input = preg_replace('/RELEASE_(.*)/', '$1', $input);
    return str_replace('_', '.', $input);
}

function buildPreinstaller() {
    global $DISTDIR;

    $results = preg_grep('/^ \* @versionId (.*)/', file("preinstaller/preinstall.php"));
    $results = array_values($results);
    $result = $results[0];
    preg_match('/versionId ([0-9\.]+)/', $result, $matches);
    $VERSION = $matches[1];
    req_system("zip -j -q $DISTDIR/preinstaller-$VERSION.zip " .
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
    global $SRCDIR;
    req_system("rm -rf $SRCDIR");
}

/**
 * Moved into a function so that we could force a scrub and clean before the nightly.
 */
function clean() {
    global $TMPDIR, $DISTDIR;
    req_system("rm -rf $TMPDIR $DISTDIR");
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
	    die("Cannot read $dir\n");
	}
	if (!is_dir($dir)) {
	    die("$dir is not a directory.");
	}
    }
}

if ($argc < 2) {
    die(usage());
}

switch ($argv[1]) {
case 'preinstaller':
    verify_dirs();
    buildPreinstaller();
    break;
case 'quietnightly':
    /* We just set the quiet flag and fall through to the nightly case */
    $QUIET = true;
case 'nightly':
    clean();
    scrub();
    verify_dirs();
    checkOut(false);
    buildManifest();
    $packages = getPackages();
    buildPackage($packages['version'], 'nightly', $packages['all'], true);
    buildPreinstaller();
    break;

case 'release':
    /*
     * Note: Don't build the manifests for final releases.  When we do a
     * release, the manifests should be up to date in SVN.  If something
     * has gone wrong and we're divergent from SVN then building the
     * MANIFESTs here will obscure that.
     */
    verify_dirs();
    checkOut();
    $packages = getPackages();
    buildPackage($packages['version'], 'minimal', $packages['core'], false);
    buildPackage($packages['version'], 'typical', $packages['recommended'], false);
    buildPackage($packages['version'], 'full', $packages['all'], false);
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
    if (!empty($PATCH_FOR)) {
	foreach ($PATCH_FOR as $patchFromTag) {
	    buildPatch($patchFromTag);
	}
    }
    break;

case 'export':
    verify_dirs();
    foreach (glob("$DISTDIR/*.{tar.gz,zip}", GLOB_BRACE) as $file) {
	$files[] = basename($file);
    }

    req_chdir($DISTDIR);
    $cmd = 'ncftpput -u anonymous -p gallery@ upload.sourceforge.net /incoming '
	    . join(' ', $files);
    req_system($cmd, "Export failed.");
    req_chdir($BASEDIR);
    break;

case 'scrub':
    scrub();
    /* Fall through to the 'clean' target */
case 'clean':
    clean();
    break;

default:
    die(usage());
}

?>
