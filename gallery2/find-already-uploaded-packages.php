#!/usr/bin/php -f
<?php
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
	if (file_exists($dist_dir . '/' . $file)) {
	    print "$file already uploaded\n";
	    $conflicts++;
	}
    }
}

print "$conflicts conflicts\n";
?>
