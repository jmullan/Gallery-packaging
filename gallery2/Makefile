# $Id$

BRANCH = BRANCH_2_0_ALPHA_4
TARDIR = tardir
CVS = cvs -z3 -d ${CVSROOT}
VERSION = 2.0-alpha-4
TARBALL = gallery-${VERSION}.tar.gz
ZIPBALL = gallery-${VERSION}.zip

package: ${TARDIR} ${TARDIR}/gallery2
	(cd ${TARDIR} && tar czf ${TARBALL} --exclude CVS gallery2) && \
	(cd ${TARDIR} && zip -r ${ZIPBALL} gallery2 -x \*CVS\*)

${TARDIR}/gallery2:
	@if [ -z "${CVSROOT}" ]; then \
		echo "CVSROOT needs to be set for this script to run."; \
		exit 1; \
	fi
	(cd ${TARDIR} && ${CVS} checkout -r ${BRANCH} gallery2) && \
	(cd ${TARDIR}/gallery2 && perl lib/tools/bin/makeManifest.pl)

${TARDIR}:
	mkdir ${TARDIR}

export:
	ncftpput -u anonymous -p gallery@ upload.sourceforge.net /incoming ${TARDIR}/${TARBALL} ${TARDIR}/${ZIPBALL}

clean:
	rm -rf ${TARDIR}

version:
	echo ${VERSION}

.PHONY: cvsroot
