# $Id$

# branches/{branchname} or tags/{tagname} or trunk
BRANCH = branches/BRANCH_1_4_4
TARDIR = tardir
SVNURL = https://svn.sourceforge.net/svnroot/gallery/${BRANCH}
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
	(cd ${TARDIR} && svn export ${SVNURL}/gallery) && \
	(cp -R ${TARDIR}/gallery/locale/en_US ${TARDIR}/gallery/en_US_temp) && \
	(rm -rf ${TARDIR}/gallery/locale) && \
	(mkdir ${TARDIR}/gallery/locale) && \
	(mv ${TARDIR}/gallery/en_US_temp ${TARDIR}/gallery/locale/en_US) && \
	(cd ${TARDIR}/docs && ./gen g1package) && \
	(mv ${TARDIR}/docs/dist/g1package ${TARDIR}/gallery/docs) && \
	(cd ${TARDIR})

# Export the docs module
#
${TARDIR}/docs: 
	(cd ${TARDIR} && svn export ${SVNURL}/docs)

${TARDIR}:
	mkdir ${TARDIR}

export:
	ncftpput -u anonymous -p gallery@ upload.sourceforge.net /incoming ${TARDIR}/${TARBALL} ${TARDIR}/${ZIPBALL}

clean:
	rm -rf ${TARDIR}

version:
	echo ${VERSION}

