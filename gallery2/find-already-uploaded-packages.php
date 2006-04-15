#!/usr/bin/php -f
<?php
$DELETE = false;

$argv = $_SERVER['argv'];
array_shift($argv);
while (count($argv) > 0) {
    $arg = array_shift($argv);
    if ($arg == '--delete') {
	$DELETE = true;
    }
}

$dist_dir = 'dist';
$downloads_html = 'tmp/downloads.html';
if (!file_exists($downloads_html)) {
    system("wget -O $downloads_html http://prdownloads.sf.net/gallery");
}

$lines = file($downloads_html);
$conflicts = 0;
foreach ($lines as $line) {
    if (preg_match('|HREF="/gallery/(.*?)"|', $line, $matches)) {
	$file = $matches[1];
	if ($file == '..') {
	    continue;
	}
	$path = $dist_dir . '/' . $file;
	if (file_exists($path)) {
	    print "$file already uploaded";
	    $conflicts++;
	    if ($DELETE) {
		unlink($path);
		print " (deleted)";
	    }
	    print "\n";
	}
    }
}

print "$conflicts conflicts\n";
?>
