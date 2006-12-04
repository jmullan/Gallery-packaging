#!/usr/bin/perl
use strict;
use Cwd;
my $cwd = &Cwd::cwd();

my $src_dir = $cwd . '/src/';
my $target_dir = $cwd . '/dist/';
my $svn_url = 'https://gallery.svn.sourceforge.net/svnroot/gallery/';
my $module = 'integration';
my @args;

if (-e $src_dir) {
    @args = ('/bin/rm', '-rf', $src_dir);
    system(@args) == 0 or die "@args failed: $?";
}
if (-e $target_dir) {
    @args = ('/bin/rm', '-rf', $target_dir);
    system(@args) == 0 or die "@args failed: $?";
}
if (-e $module) {
    @args = ('/bin/rm', '-rf', "$src_dir/$module");
    system(@args) == 0 or die "@args failed: $?";
}
@args = ('mkdir', $src_dir);
system(@args) == 0 or die "@args failed: $?";
@args = ('mkdir', $target_dir);
system(@args) == 0 or die "@args failed: $?";

chdir($src_dir) or die "Can't cd to $src_dir $!\n";
@args = ('svn', 'checkout', '-q', $svn_url . '/trunk/' . $module);
system(@args) == 0 or die "@args failed: $?";

chdir ("$module/gallery2") or die "Can't cd to $module/gallery2 $!\n";
opendir(DIR, '.') || die "I can't read this directory: $module/gallery2 $!";
my @integrations = readdir(DIR);
closedir DIR;
foreach my $integration (sort (@integrations))  {
    if ($integration ne '.'
        && $integration ne '..'
        && $integration ne '.svn') {
        @args = ("/bin/tar","--exclude", ".svn", "-czf", $target_dir . "$integration.tar.gz", "$integration");
        system(@args) == 0 or die "@args failed: $?". ($? >> 8) . ($? & 127);
        @args = ("/bin/chmod", "644", $target_dir . "$integration.tar.gz");
        system(@args) == 0 or die "@args failed: $?";
        @args = ("/usr/bin/zip", "-r9q", $target_dir . "$integration.zip", "$integration", "-x", ".svn");
        system(@args) == 0 or die "@args failed: $?". ($? >> 8) . ($? & 127);
        @args = ("/bin/chmod", "644", $target_dir . "$integration.zip");
        system(@args) == 0 or die "@args failed: $?";
    }
}
