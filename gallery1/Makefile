# $Id$

BRANCH = BRANCH_1_4_4
TARDIR = tardir
CVS = cvs -z3 -d ${CVSROOT}
VERSION_PHP = ${TARDIR}/gallery/Version.php
VERSION_CODE = php -r 'include("${VERSION_PHP}"); print $$gallery->version;'
TARBALL = gallery-$(shell ${VERSION_CODE}).tar.gz
ZIPBALL = gallery-$(shell ${VERSION_CODE}).zip

package: ${TARDIR} ${TARDIR}/gallery
	(cd $(TARDIR)/gallery && php -f tools/build_manifest.php) && \
	(cd ${TARDIR} && tar czf ${TARBALL} gallery) && \
	(cd ${TARDIR} && zip -r ${ZIPBALL} gallery)

# Export the gallery module, generate the docs and copy them into the exported gallery
#
${TARDIR}/gallery: ${TARDIR}/docs
	@if [ -z "${CVSROOT}" ]; then \
		echo "CVSROOT needs to be set for this script to run."; \
		exit 1; \
	fi
	(cd ${TARDIR} && ${CVS} export -r ${BRANCH} gallery) && \
	(cp -R ${TARDIR}/gallery/locale/en_US ${TARDIR}/gallery/en_US_temp) && \
	(rm -rf ${TARDIR}/gallery/locale) && \
	(mkdir ${TARDIR}/gallery/locale) && \
	(mv ${TARDIR}/gallery/en_US_temp ${TARDIR}/gallery/locale/en_US) && \
	(cd ${TARDIR}/docs && ./gen g1package) && \
	(mv ${TARDIR}/docs/dist/g1package ${TARDIR}/gallery/docs) && \
	(cd ${TARDIR}) && \
	(wget http://jpmullan.com/galleryupdates/remote/current_gallery_remote_applets.zip) && \
	(unzip current_gallery_remote_applets.zip -d ${TARDIR}/gallery/java) && \
	(rm current_gallery_remote_applets.zip)

# Export the docs module
#
${TARDIR}/docs: 
	@if [ -z "${CVSROOT}" ]; then \
		echo "CVSROOT needs to be set for this script to run."; \
		exit 1; \
	fi
	(cd ${TARDIR} && ${CVS} export -r ${BRANCH} docs)

${TARDIR}:
	mkdir ${TARDIR}

export:
	ncftpput -u anonymous -p gallery@ upload.sourceforge.net /incoming ${TARDIR}/${TARBALL} ${TARDIR}/${ZIPBALL}

clean:
	rm -rf ${TARDIR}

version:
	echo ${VERSION}

.PHONY: cvsroot
