#!/bin/sh
CVSROOT=":ext:`whoami`@cvs.sf.net:/cvsroot/gallery"
BRANCH="HEAD"
TMPDIR="`pwd`/tmp"
DISTDIR="`pwd`/dist"
CVS="cvs -Q -z3 -d $CVSROOT"

function getVersion {
    MODULEINC=tmp/gallery2/modules/core/module.inc
    if [ -f $MODULEINC ]; then
	VERSION=`grep 'this->setGalleryVersion' $MODULEINC | perl -pe 's/.*\(.(.*).\).*/$1/'`
    else
	VERSION="unknown"
    fi
}

function checkout {
    echo -n "Checking out code..."
    (cd $TMPDIR && $CVS checkout -r $BRANCH gallery2 || error "Checkout failed")
    echo "done."
}

function manifest {
    echo "Building manifest..."
    (cd $TMPDIR/gallery2 && perl lib/tools/bin/makeManifest.pl 2>&1 | perl -pe 's/^/> /')
}

function package {
    getPackageNames
    (cd $TMPDIR && tar czf $TARBALL --exclude CVS gallery2 || error "Error building tarball")
    (cd $TMPDIR && zip -q -r $ZIPBALL gallery2 -x \*CVS\* || error "Error building zipball")
}

function error {
    echo "ERROR: $1";
    exit 1;
}

function getPackageNames {
    getVersion
    TARBALL="$DISTDIR/gallery-$VERSION.tar.gz"
    ZIPBALL="$DISTDIR/gallery-$VERSION.zip"
}

if [ ! -d $TMPDIR ]; then mkdir $TMPDIR;  fi
if [ ! -d $DISTDIR ]; then mkdir $DISTDIR; fi

case $1 in
    nightly)
	checkout
	manifest
	package
	;;

    release)
	# Note: Don't build the manifests for final releases.  When we do a
	# release, the manifests should be up to date in CVS.  If something
	# has gone wrong and we're divergent from CVS then building the
	# MANIFESTs here will obscure that.
	checkout
	package
	;;

    export)
	ncftpput -u anonymous -p gallery@ upload.sourceforge.net /incoming $TARBALL $ZIPBALL
	;;

    clean)
	/bin/rm -rf $TMPDIR
	;;

    *)
	echo "Invalid action.  Choices: nightly release export clean"
	;;
esac
