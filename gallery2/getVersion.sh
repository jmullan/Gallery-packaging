#!/bin/sh
#
grep 'this->setGalleryVersion' src/gallery2/modules/core/module.inc | \
  perl -pe 's/.*\(.(.*).\).*/$1/'

