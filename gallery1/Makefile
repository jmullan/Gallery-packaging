# $Id$

# branches/{branchname} or tags/{tagname} or trunk
BRANCH = trunk
TARDIR = tardir
SVNURL = https://gallery.svn.sourceforge.net/svnroot/gallery/${BRANCH}
VERSION_PHP = ${TARDIR}/gallery/Version.php
VERSION_CODE = php -r 'include("${VERSION_PHP}"); print $$gallery->version;'
TARBALL = gallery-$(shell ${VERSION_CODE}).tar.gz
ZIPBALL = gallery-$(shell ${VERSION_CODE}).zip

package: ${TARDIR} ${TARDIR}/gallery
	(cd $(TARDIR) && mv gallery gallery-$(shell ${VERSION_CODE})) && \
	(cd $(TARDIR) && ln -s gallery-$(shell ${VERSION_CODE}) gallery) && \
	(cd $(TARDIR)/gallery-$(shell ${VERSION_CODE}) && php -f tools/build_manifest_1_4.php) && \
	(cd ${TARDIR} && tar czf ${TARBALL} gallery-$(shell ${VERSION_CODE})) && \
	(cd ${TARDIR} && zip -r ${ZIPBALL} gallery-$(shell ${VERSION_CODE}))

# Export the gallery module, generate the docs and copy them into the exported gallery
#
${TARDIR}/gallery: ${TARDIR}/docs
	(cd ${TARDIR} && svn export -q ${SVNURL}/gallery) && \
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
	(cd ${TARDIR} && svn export -q ${SVNURL}/docs)

${TARDIR}:
	mkdir ${TARDIR}

export:
	ncftpput -u anonymous -p gallery@ upload.sourceforge.net /incoming ${TARDIR}/${TARBALL} ${TARDIR}/${ZIPBALL}

clean:
	rm -rf ${TARDIR}

version:
	echo ${VERSION}

