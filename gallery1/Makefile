
VERSION = `cat gallery/Version.php | perl -ne 'if (/\$$gallery->version.*"(.*)"/) { ($$x = $$1) =~ s/ //g; print $$x; }'`

TARBALL = gallery-${VERSION}.tgz
package:
	cd docs; ./gen g1package
	cd gallery ; cvs update -r HEAD
	cvs -n update gallery 2>&1 | egrep '^\?' | perl -pe 's/^..//' > /tmp/make.gallery.exclude
	mv docs/dist/g1package gallery/docs
	tar --exclude-from /tmp/make.gallery.exclude \
	    --exclude 'CVS' \
	    --exclude '*.#*' \
	    --exclude '*~' \
	    --gzip -cvf ${TARBALL} gallery
	rm /tmp/make.gallery.exclude

export:
	ncftpput -u anonymous -p gallery@ upload.sourceforge.net /incoming ${TARBALL}
