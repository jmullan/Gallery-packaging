# $Id$

BRANCH = BRANCH_1_4

TARDIR = tardir

CVSROOT = :ext:beckettmw@cvs.sourceforge.net:/cvsroot/gallery

CVS = cvs -z3 -d ${CVSROOT}
TARBALL = cat gallery/Version.php | perl -ne 'if (/\$$gallery->version.*"(.*)"/) { ($$x = $$1) =~ s/ //g; print "gallery-$$x.tgz"; }'

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
	/bin/rm -rf ${TARDIR}

export:
	ncftpput -u anonymous -p gallery@ upload.sourceforge.net /incoming `${TARBALL}`

clean:
	rm -fr ${TARDIR} *.tgz
