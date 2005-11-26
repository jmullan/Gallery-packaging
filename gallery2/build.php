#!/usr/bin/php -f
<?php
$BRANCH = 'BRANCH_2_0';
$CVSROOT = ":ext:$_SERVER[USER]@cvs.sf.net:/cvsroot/gallery";
$BASEDIR = dirname(__FILE__);
$SRCDIR = $BASEDIR . '/src';
$TMPDIR = $BASEDIR . '/tmp';
$DISTDIR = $BASEDIR . '/dist';
$CVS = 'cvs -Q -z3 -d ' . $CVSROOT;
$SKIP_CHECKOUT = false;

function checkOut() {
    global $SRCDIR, $BASEDIR, $CVS, $BRANCH, $SKIP_CHECKOUT;

    print 'Checking out code...';

    chdir($SRCDIR);
    if ($SKIP_CHECKOUT) {
	print 'Skipping checkout...';
    } else {
	$cmd = "$CVS checkout -r $BRANCH gallery2";
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
	    in_array($id, array('imagemagick', 'netpbm', 'gd', 'ffmpeg',
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
    global $TMPDIR, $BASEDIR;
    chdir("$TMPDIR/gallery2");
    system("perl lib/tools/bin/makeManifest.pl");
    chdir($BASEDIR);
}

function usage() {
    return "usage: build.php <cmd>\n" .
	"\n" .
	"command is one of nightly, release, export, clean\n";
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
    checkOut();
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
