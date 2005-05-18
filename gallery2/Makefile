# $Id$

BRANCH = BRANCH_2_0_BETA_3
TMPDIR = tmp
DISTDIR = dist
CVS = cvs -z3 -d ${CVSROOT}
VERSION = $(shell /bin/sh getVersion.sh)

TARBALL = gallery-${VERSION}.tar.gz
ZIPBALL = gallery-${VERSION}.zip

package: ${TMPDIR} ${TMPDIR}/gallery2
	(cd ${TMPDIR} && tar czf ../${DISTDIR}/${TARBALL} --exclude CVS gallery2) && \
	(cd ${TMPDIR} && zip -r ../${DISTDIR}/${ZIPBALL} gallery2 -x \*CVS\*)

${TMPDIR}/gallery2:
	@if [ -z "${CVSROOT}" ]; then \
		echo "CVSROOT needs to be set for this script to run."; \
		exit 1; \
	fi
	(cd ${TMPDIR} && ${CVS} checkout -r ${BRANCH} gallery2)

# Note: Don't build the manifests here.  They should already be perfect in CVS at
#       this point, and if something has gone wrong and we're divergent from CVS then
#       building the MANIFESTs here will obscure that.

${TMPDIR}:
	mkdir ${TMPDIR}

${DISTDIR}:
	mkdir ${DISTDIR}

export:
	ncftpput -u anonymous -p gallery@ upload.sourceforge.net /incoming ${DISTDIR}/${TARBALL} ${DISTDIR}/${ZIPBALL}

clean:
	rm -rf ${TMPDIR}

version:
	echo ${VERSION}

.PHONY: cvsroot
