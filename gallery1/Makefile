# $Id$

BRANCH = HEAD
TARDIR = tardir

CVS = cvs -z3 -d ${CVSROOT}
TARBALL = cat gallery/Version.php | perl -ne 'if (/\$$gallery->version.*"(.*)"/) { ($$x = $$1) =~ s/ //g; print "gallery-$$x.tar.gz"; }'
ZIPBALL = cat gallery/Version.php | perl -ne 'if (/\$$gallery->version.*"(.*)"/) { ($$x = $$1) =~ s/ //g; print "gallery-$$x.zip"; }'

package:
	@if [ -z "${CVSROOT}" ]; then \
		echo "CVSROOT needs to be set for this script to run."; \
		exit 1; \
	fi

	mkdir -p ${TARDIR} && \
	(cd ${TARDIR} && ${CVS} checkout -r ${BRANCH} docs) && \
	(cd ${TARDIR}/docs && ./gen g1package) && \
	(cd ${TARDIR} && ${CVS} export -r ${BRANCH} gallery) && \
	mv ${TARDIR}/docs/dist/g1package ${TARDIR}/gallery/docs && \
	(cd ${TARDIR} && tar czf ../`${TARBALL}` gallery) && \
	(cd ${TARDIR} && zip ../`${ZIPBALL}` gallery) && \
	/bin/rm -rf ${TARDIR}

export:
	ncftpput -u anonymous -p gallery@ upload.sourceforge.net /incoming `${TARBALL}` `${ZIPBALL}`

clean:
	rm -fr ${TARDIR} `${TARBALL}` `${ZIPBALL}`
