#!/usr/bin/env perl -w
#
use File::Basename;
use Cwd;

$version{"netpbm"} = "1.1";
$version{"gallery"} = "1.0";

$packagedir = "/usr/www/website/menalto.com/dev/gallery/packaging";
foreach $dir (`find netpbm/gallery -name netpbm -type d`) {
  chomp($dir);
  ($pkgname = dirname($dir)) =~ s|/|-|g;
  $pkgname =~ s/netpbm/netpbm$version{netpbm}/;
  $pkgname =~ s/gallery/gallery$version{gallery}/;

  $curdir = getcwd();
  chdir(dirname($dir));
  system("tar -hcf - netpbm | gzip -c > $packagedir/$pkgname.tgz");
  chdir($curdir);
}
