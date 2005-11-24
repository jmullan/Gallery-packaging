<?php
/* This cvs should be current for: BRANCH_2_0 */
$LAST_RELEASE = 'RELEASE_2_0_1';
$PATCH = '201-to-202';

$patchFile = 'patch-' . $PATCH . '.txt';
system("cvs -q diff -Nur $LAST_RELEASE > $patchFile");

foreach (file($patchFile) as $line) {
    $str = substr($line, 0, 7);
    if ($str == 'Index: ') {
        $changedFile = rtrim(substr($line, 7));
    } else if ($str == '=======' && !empty($changedFile)) {
        $changedFiles[] = $changedFile;
    } else {
        $changedFile = '';
    }
}

$dir = 'changedFiles-' . $PATCH;
mkdir($dir);
chdir($dir);
foreach ($changedFiles as $changedFile) {
    system('mkdir -p ' . dirname($changedFile));
    system("cp ../$changedFile $changedFile");
}
?>
