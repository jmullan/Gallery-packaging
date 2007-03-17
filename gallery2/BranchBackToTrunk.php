<?php
/**
 * Prepare a series of svn merge and commit commands to merge a development branch
 * back into trunk, adding log messages from the branch into the trunk history.
 */
$BRANCH='DEV_2_3';
$STARTREV=15949;
$BASEURL='https://gallery.svn.sourceforge.net/svnroot/gallery';

$data = $files = array();
$proc = proc_open("svn log -vr $STARTREV:HEAD $BASEURL/branches/$BRANCH/gallery2",
		  array(1 => array('pipe', 'w')), $pipe);
if ($proc) {
    while ($line = fgets($pipe[1])) {
	if (!strncmp($line, '----------------------------------------', 40)) {
	    foreach ($files as $file) {
		$data[$file] .= $buf . "\n";
	    }
	    $files = array();
	    $buf = '';
	}
	else if (preg_match('#^\s*[MAD]\s/branches/' . $BRANCH . '/gallery2/(.*)$#',
			    $line, $match)) {
	    $files[] = $match[1];
	}
	else if (preg_match('/^Merge.*from trunk/i', $line)) {
	    // Skip revisions merging changes from trunk
	    $files = array();
	}
	else if (!preg_match('/^(Changed paths:)?$/', $line)) {
	    $buf .= $line;
	}
    }
    fclose($pipe[1]);
    proc_close($proc);
}
echo "\nsvn merge $BASEURL/trunk/gallery2 $BASEURL/branches/$BRANCH/gallery2 .\n";

foreach ($data as $file => $log) {
    $out[$log][] = $file;
}
foreach ($out as $log => $files) {
    $f = fopen('/tmp/svnlog.' . ++$c, 'w');
    fwrite($f, "Merge $BRANCH branch back to trunk.  Changes from branch:\n\n" . $log);
    fclose($f);
    echo "\nsvn ci -F /tmp/svnlog.$c ", implode(' ', $files), "\n";
}
echo "\nrm /tmp/svnlog.*\n\n";
?>
