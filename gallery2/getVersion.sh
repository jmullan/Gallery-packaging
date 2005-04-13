#!/bin/sh
#
grep 'this->setGalleryVersion' tmp/gallery2/modules/core/module.inc | \
  perl -pe 's/.*\(.(.*).\).*/$1/'

