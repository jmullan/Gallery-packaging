#!/usr/bin/php -f
<?php
$TAG = 'RELEASE_2_1';
// $PATCH_FOR = array('RELEASE_2_0_4', 'RELEASE_2_0_3',
//		      'RELEASE_2_0_2', 'RELEASE_2_0_1', 'RELEASE_2_0');
$CVSROOT = ":ext:$_SERVER[USER]@cvs.sf.net:/cvsroot/gallery";
$BASEDIR = dirname(__FILE__);
$SRCDIR = $BASEDIR . '/src';
$TMPDIR = $BASEDIR . '/tmp';
$DISTDIR = $BASEDIR . '/dist';
$CVS = 'cvs -f -Q -z3 -d ' . $CVSROOT;
$SKIP_CHECKOUT = false;


function checkOut($useTag=true) {
    global $SRCDIR, $BASEDIR, $CVS, $TAG, $SKIP_CHECKOUT;

    print 'Checking out code...';

    chdir($SRCDIR);
    if ($SKIP_CHECKOUT) {
	print 'Skipping checkout...';
    } else {
	$cmd = "$CVS checkout" . ($useTag ? " -r $TAG" : '') . ' -P gallery2';
	system($cmd, $result);
	if ($result) {
	    die('Checkout failed');
	}
    }
    chdir($BASEDIR);
    print "done.\n";
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
    global $BASEDIR, $SRCDIR, $TMPDIR, $DISTDIR;

    print "Build plugin $id ($version)...";
    chdir("$SRCDIR/gallery2");

    $relative = "${type}s/$id";
    $files = explode("\n", `find $relative -type f`);

    /* Exclude CVS */
    $files = preg_grep('|CVS|', $files, PREG_GREP_INVERT);

    /* Dump the list to a tmp file */
    $fd = fopen("$TMPDIR/files.txt", 'w+');
    fwrite($fd, join("\n", $files));
    fclose($fd);

    /* Tar and zip it */
    system("tar czf $DISTDIR/g2-$type-$id-$version.tar.gz --files-from=$TMPDIR/files.txt", $return);
    if ($return) {
	die('Tar failed');
    }

    escapePatterns("$TMPDIR/files.txt", "$TMPDIR/escapedFiles.txt");
    system("zip -9 -q -r $DISTDIR/g2-$type-$id-$version.zip ${type}s/$id -i@$TMPDIR/escapedFiles.txt", $return);
    if ($return) {
	die('Zip failed');
    }

    unlink("$TMPDIR/files.txt");
    unlink("$TMPDIR/escapedFiles.txt");
    chdir($BASEDIR);

    print "done\n";
}

function buildPackage($version, $tag, $packages, $developer) {
    global $BASEDIR, $SRCDIR, $TMPDIR, $DISTDIR;

    print "Build $tag of $version...";

    /* Get all files */
    chdir($SRCDIR);
    $files = explode("\n", `find gallery2 -type f`);

    /* Exclude CVS */
    $originalFiles = $files = preg_grep('|CVS|', $files, PREG_GREP_INVERT);

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
    system("rm -rf $TMPDIR/gallery2");
    mkdir("$TMPDIR/gallery2");
    system("(cd $SRCDIR && tar cf - --files-from=$TMPDIR/files.txt) | " .
	   "(cd $TMPDIR && tar xf -)", $return);
    if ($return) {
	die('Temporary copy via tar failed');
    }

    /* Update manifests to reflect files we've removed */
    chdir($TMPDIR);
    filterManifests($originalFiles, $files);

    /* Tar and zip it */
    system("tar czf $DISTDIR/gallery-$version-$tag.tar.gz --files-from=$TMPDIR/files.txt", $return);
    if ($return) {
	die('Tar failed');
    }

    escapePatterns("$TMPDIR/files.txt", "$TMPDIR/escapedFiles.txt");
    system("zip -q -r $DISTDIR/gallery-$version-$tag.zip gallery2 -i@$TMPDIR/escapedFiles.txt", $return);
    if ($return) {
	die('Zip failed');
    }

    unlink("$TMPDIR/files.txt");
    unlink("$TMPDIR/escapedFiles.txt");
    chdir($BASEDIR);

    print "done\n";
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
    global $SRCDIR, $BASEDIR;
    chdir("$SRCDIR/gallery2");
    system("perl lib/tools/bin/makeManifest.pl");
    chdir($BASEDIR);
}

function buildPatch($patchFromTag) {
    global $TMPDIR, $SRCDIR, $BASEDIR, $TAG;
    $CVS_DIFF = 'cvs -f -q diff -Nur';

    print "Build patch for $patchFromTag...";
    $finalPackage = array();

    $fromVersionTag = extractVersionTag($patchFromTag);
    $toVersionTag = extractVersionTag($TAG);

    ob_start();
    include(dirname(__FILE__) . '/patch-README.txt.inc');
    $readmeText = ob_get_contents();
    ob_end_clean();

    $patchVersion = strtr(str_replace('RELEASE_', '', $patchFromTag), '_', '.');
    $patchDir = "$TMPDIR/$patchVersion";
    @mkdir($patchDir);
    $patchTmp = "$patchDir/patch-$patchVersion.txt";

    $readme = fopen("$patchDir/README.txt", "wb");
    fwrite($readme, $readmeText);
    fclose($readme);
    $finalPackage['README.txt'] = 1;

    /*
     * We want to drop all XxxTest.class related lines from the diff because the user may not have
     * unit tests so we can't patch them.  This means that we also need to drop those lines from
     * the MANIFEST diffs.  CVS will let us elide those lines, but only if we diff the MANIFEST
     * files individually.  So the plan is to run one diff to get all the files that changed and
     * split them into MANIFEST files vs. everything else (excluding test files) and then diff
     * them in two batches.
     */
    chdir("$SRCDIR/gallery2");

    system("$CVS_DIFF $patchFromTag > $patchTmp.raw");

    $manifestFiles = array();
    $regularFiles = array();
    foreach ($patchLines = file("$patchTmp.raw") as $i => $line) {
	if (substr($line, 0, 7) == 'Index: ' && substr($patchLines[$i+1], 0, 7) == '=======') {
	    $changedFile = rtrim(substr($line, 7));
	    if (preg_match('/MANIFEST/', $changedFile)) {
		$manifestFiles[$changedFile] = 1;
	    } else if ($changedFile != 'docs/LOCALIZING' &&
		       !preg_match('/Test.class$/', $changedFile)) {
		/* We ditched docs/LOCALIZING in 2.0.2 -- don't want it in the patch */
		/* Leave XxxTest.class files out of the patch */
		$regularFiles[$changedFile] = 1;
	    }
	}
    }

    @unlink($patchTmp);
    system(sprintf("$CVS_DIFF $patchFromTag %s >> $patchTmp",
    		   join(' ', array_keys($regularFiles))));
    system(sprintf("$CVS_DIFF $patchFromTag --ignore-matching-lines='.*Test.class' %s >> $patchTmp",
    		   join(' ', array_keys($manifestFiles))));

    /*
     * Now $patchTmp contains only files that we care about, and no extraneous MANIFEST
     * entries.
     * NOTE: docs/LOCALIZING is an exceptional case: we didn't ship the changed file but
     *       we marked it as removed in the manifest in the 2.0.2 patch.  Preserve that
     *       behavior going forward.
     */
    chdir($BASEDIR);
    foreach ($patchLines = file($patchTmp) as $i => $line) {
	if (substr($line, 0, 7) == 'Index: ' && substr($patchLines[$i+1], 0, 7) == '=======') {
	    $changedFile = rtrim(substr($line, 7));
	    $changedFiles[] = $changedFile;
	    preg_match('{^(?:modules|themes)/(.*?)/}', $changedFile, $matches);
	    $patchToken = empty($matches) ? 'core' : $matches[1];
	    if (!isset($patchFD[$patchToken])) {
		$patchFD[$patchToken] = fopen("$patchDir/patch-$patchToken.txt", 'w');
		$finalPackage["patch-$patchToken.txt"] = 1;
	    }
	    $fd = $patchFD[$patchToken];
	}
	fwrite($fd, $line);
    }
    fwrite($fd, $line);

    foreach ($patchFD as $fd) {
	fclose($fd);
    }

    $needToPackage = array();
    foreach ($changedFiles as $changedFile) {
	preg_match('{^(modules|themes)/([^/]*)(.*?)$}', $changedFile, $matches);
	if (empty($matches)) {
	    $patchToken = 'core';
	    $relativePath = $changedFile;
	} else {
	    $patchToken = $matches[2];
	    $relativePath = $matches[1] . "/" . $matches[2] . $matches[3];
	}
	$needToPackage[$patchToken] = 1;

	system("mkdir -p $patchDir/$patchToken/" . dirname($relativePath));
	if (basename($relativePath) == 'MANIFEST') {
	    /* Filter out test files so that we don't pollute non-dev dists with dev data */
	    $lines = file("$SRCDIR/gallery2/$changedFile");
	    $lines = preg_grep('|^modules/\w+/test/|', $lines, PREG_GREP_INVERT);
	    $lines = preg_grep('|^lib/tools|', $lines, PREG_GREP_INVERT);
	    $new = fopen("$patchDir/$patchToken/$relativePath", 'wb');
	    fwrite($new, join("\n", $lines));
	    fclose($new);
	} else {
	    system("cp $SRCDIR/gallery2/$changedFile $patchDir/$patchToken/" . dirname($relativePath));
	}
    }

    foreach (array_keys($needToPackage) as $plugin) {
	chdir("$patchDir/$plugin");
	system("zip -q -r ../changed-files-$plugin.zip *");
	$finalPackage["changed-files-$plugin.zip"] = 1;
    }

    /*
     * Due to some weirdness in the way that we deal with modules/exif/lib/JPEG/JPEG.inc
     * caused (I think) by the fact that it gained a -kb sticky bit, we generate a
     * MANIFEST-only patch for the exif module that leaves the expected size of this file
     * in the MANIFEST out of sync with the actual file size unless we replace it.  The
     * easiest thing to do is to just drop those changes from releases that don't need it.
     */
    unset($finalPackage["changed-files-exif.zip"]);
    unset($finalPackage["patch-exif.txt"]);

    chdir("$patchDir");

    system(sprintf("zip -q -r ../update-$fromVersionTag-to-$toVersionTag.zip %s",
		   join(' ', array_keys($finalPackage))));

    #system("/bin/rm -rf $patchDir");

    print "done\n";
}

function extractVersionTag($input) {
    $input = preg_replace('/RELEASE_(.*)/', '$1', $input);
    $input = preg_replace('/_/', '.', $input);
    return $input;
}

function usage() {
    return "usage: build.php <cmd>\n" .
	"command is one of nightly, release, patches, export, scrub, clean\n";
}

if ($argc < 2) {
    die(usage());
}

foreach (array($TMPDIR, $SRCDIR, $DISTDIR) as $dir) {
    if (!file_exists($dir)) {
	mkdir($dir) || die("Unable to mkdir($dir)");
    }
}

switch($argv[1]) {
case 'nightly':
    checkOut(false);
    buildManifest();
    $packages = getPackages();
    buildPackage($packages['version'], 'nightly', $packages['all'], true);
    break;

case 'release':
    /*
     * Note: Don't build the manifests for final releases.  When we do a
     * release, the manifests should be up to date in CVS.  If something
     * has gone wrong and we're divergent from CVS then building the
     * MANIFESTs here will obscure that.
     */
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
    if (!empty($PATCH_FOR)) {
	foreach ($PATCH_FOR as $patchFromTag) {
	    buildPatch($patchFromTag);
	}
    }
    break;

case 'export':
    foreach (glob("$DISTDIR/*.{tar.gz,zip}", GLOB_BRACE) as $file) {
	$files[] = basename($file);
    }

    chdir($DISTDIR);
    $cmd = 'ncftpput -u anonymous -p gallery@ upload.sourceforge.net /incoming ' .
	join(' ', $files);
    system($cmd, $result);
    if ($result) {
	die('Export failed');
    }
    chdir($BASEDIR);
    break;

case 'scrub':
    system("rm -rf $SRCDIR");
    /* Fall through to the 'clean' target */

case 'clean':
    system("rm -rf $TMPDIR $DISTDIR");
    break;

default:
    die(usage());
}

?>
