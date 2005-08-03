#!/usr/bin/php -f
<?php
$BRANCH = 'HEAD';
$CVSROOT = ":ext:$_SERVER[USER]@cvs.sf.net:/cvsroot/gallery";
$BASEDIR = dirname(__FILE__);
$TMPDIR = $BASEDIR . '/tmp';
$DISTDIR = $BASEDIR . '/dist';
$CVS = 'cvs -Q -z3 -d ' . $CVSROOT;
$SKIP_CHECKOUT = false;

function checkOut() {
    global $TMPDIR, $BASEDIR, $CVS, $BRANCH, $SKIP_CHECKOUT;

    print 'Checking out code...';

    chdir($TMPDIR);
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

class GalleryModule {
    function isRecommendedDuringInstall() { return false; }
}

function getPackages() {
    global $TMPDIR;

    foreach (glob("$TMPDIR/gallery2/modules/*/module.inc") as $path) {
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

	/* Hack out the constructor and anything that'll mess up eval */
	$code = str_replace('<?php', '', $code);
	$code = str_replace('?>', '', $code);
	$code = preg_replace("/function {$id}module\(\) {.*?}/si", "function ${id}module() { }",
			     $code);

	eval($code);
	$pluginClass = "${id}module";
	$plugin = new $pluginClass;

	/* Run it and find out if it's recommended */
	$packages['recommended']['modules'][$id] = $plugin->isRecommendedDuringInstall();
	$packages['all']['modules'][$id] = true;
	$packages['core']['modules'][$id] = ($id == 'netpbm' || $id == 'imagemagick' || $id == 'gd');
    }

    foreach (glob("$TMPDIR/gallery2/themes/*/theme.inc") as $path) {
	$id = basename(dirname($path));
	$code = file_get_contents($path);

	/* Get the version */
	preg_match('/\$this->setVersion\(\'(.*?)\'\)/', $code, $matches);
	$packages['themes'][$id]['version'] = $matches[1];

	$packages['recommended']['themes'][$id] = true;
	$packages['all']['themes'][$id] = true;
	$packages['core']['themes'][$id] = ($id == 'matrix' || $id == 'siriux');
    }

    return $packages;
}

function buildPluginPackage($type, $id, $version) {
    global $BASEDIR, $TMPDIR, $DISTDIR;

    print "Build plugin $id ($version)...";
    chdir("$TMPDIR/gallery2");

    $relative = "${type}s/$id";
    $files = explode("\n", `find $relative -type f`);

    /* Exclude CVS */
    $files = preg_grep('|CVS|', $files, PREG_GREP_INVERT);

    /* Dump the list to a tmp file */
    $fd = fopen("$TMPDIR/files.txt", 'w+');
    fwrite($fd, join("\n", $files));
    fclose($fd);

    /* Tar and zip it */
    system("tar czf $DISTDIR/$type-$version-$id.tar.gz --files-from=$TMPDIR/files.txt", $return);
    if ($return) {
	die('Tar failed');
    }

    system("zip -q -r $DISTDIR/$type-$version-$id.zip ${type}s/$id -i@$TMPDIR/files.txt", $return);
    if ($return) {
	die('Zip failed');
    }

    print "done\n";

    chdir($BASEDIR);
}

function buildPackage($version, $tag, $packages, $developer) {
    global $BASEDIR, $TMPDIR, $DISTDIR;

    print "Build $tag of $version";
    if ($developer) {
	print ' (developer)';
    }
    print '...';

    /* Get all files */
    chdir($TMPDIR);
    $files = explode("\n", `find gallery2 -type f`);

    /* Exclude CVS */
    $files = preg_grep('|CVS|', $files, PREG_GREP_INVERT);

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
    $fd = fopen('files.txt', 'w+');
    fwrite($fd, join("\n", $files));
    fclose($fd);

    /* Tar and zip it */
    system("tar czf $DISTDIR/gallery-$version-$tag.tar.gz --files-from=files.txt", $return);
    if ($return) {
	die('Tar failed');
    }

    system("zip -q -r $DISTDIR/gallery-$version-$tag.zip gallery2 -i@files.txt", $return);
    if ($return) {
	die('Zip failed');
    }

    unlink('files.txt');
    chdir($BASEDIR);

    print "done\n";
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

foreach (array($TMPDIR, $DISTDIR) as $dir) {
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

case 'clean':
    system("rm -rf $TMPDIR $DISTDIR");
    break;
}

?>
