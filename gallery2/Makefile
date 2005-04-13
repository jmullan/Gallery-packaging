# $Id$

BRANCH = BRANCH_2_0_BETA_2
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
	(cd ${TMPDIR} && ${CVS} checkout -r ${BRANCH} gallery2) && \
	(cd ${TMPDIR}/gallery2 && perl lib/tools/bin/makeManifest.pl)

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
