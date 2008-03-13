<?php
$TRUNK = 'https://gallery.svn.sourceforge.net/svnroot/gallery/trunk/gallery2';
$VENDOR = 'vendor';

myExec("find $VENDOR -name .svn -prune -o -type f -print | xargs rm");
myExec("svn export --force $TRUNK $VENDOR");

$proc = proc_open("svn st $VENDOR", array(1 => array('pipe', 'w')), $pipe);
while ($line = fgets($pipe[1])) {
    if (preg_match('/^([^M])\s*(.*)/', $line, $match)) {
	if ($match[1] == '!') {
	    myExec("svn rm $match[2]");
	} else if ($match[1] == '?') {
	    myExec("svn add $match[2]");
	}
    }
}
fclose($pipe[1]);
proc_close($proc);

function myExec($cmd) {
    print "$cmd\n";
    $result = system($cmd, $return);
    if ($return) {
	print "Failed ($result)!";
	exit(1);
    }
}
?>
