
VERSION = `cat gallery/Version.php | perl -ne 'if (/\$$gallery->version.*"(.*)"/) { ($$x = $$1) =~ s/ //g; print $$x; }'`

package:
	cd gallery ; cvs update -r HEAD
	cvs -n update gallery 2>&1 | egrep '^\?' | perl -pe 's/^..//' > /tmp/make.gallery.exclude
	tar --exclude-from /tmp/make.gallery.exclude \
	    --exclude 'CVS' \
	    --exclude '*.#*' \
	    --exclude '*~' \
	    --gzip -cvf gallery-${VERSION}.tar.gz gallery
	rm /tmp/make.gallery.exclude

export:
	ncftpput -u anonymous -p gallery@ upload.sourceforge.net /incoming gallery-${VERSION}.tar.gz
