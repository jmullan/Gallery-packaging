#!/bin/sh
# Find which plugins need a version bump for the next release.
# Set CURRENT to where the current files for the next release are located
# (branches/BRANCH_2_X for patch release, trunk for major release unless
#  a branch for it has already been created)
LAST_TAG=RELEASE_2_2_4
CURRENT=branches/BRANCH_2_2

for pluginFile in `ls modules/*/module.inc themes/*/theme.inc`; do
  plugin=`echo $pluginFile | awk -F/ '{ print $2 }'`
  if [ $plugin = core ]; then continue; fi
  lastVersion=`svn cat https://gallery.svn.sourceforge.net/svnroot/gallery/tags/${LAST_TAG}/gallery2/${pluginFile} 2>&1 | awk -F"'" '/setVersion/ { print $2; exit }'`
  currentVersion=`svn cat https://gallery.svn.sourceforge.net/svnroot/gallery/${CURRENT}/gallery2/${pluginFile} 2>&1 | awk -F"'" '/setVersion/ { print $2; exit }'`
  if [ "$currentVersion" != "$lastVersion" ]; then
    printf '%-15s %7s -> %7s'"\n" $plugin "$lastVersion" "$currentVersion"
  else
    # Check if any files changed for this plugin
    svn diff --old=https://gallery.svn.sourceforge.net/svnroot/gallery/tags/${LAST_TAG}/gallery2 \
	     --new=https://gallery.svn.sourceforge.net/svnroot/gallery/${CURRENT}/gallery2 \
	     `dirname $pluginFile` 2>/dev/null \
	| awk '$1=="Index:" {if($2=="MANIFEST"){next} if($2~/^(po|locale)\//){S=2;next} S=1;exit} \
	       END { exit S }'
    S=$?
    if [ $S -eq 0 ]; then
	echo "$plugin unchanged"
    else
	if [ $S -eq 2 ]; then
	    echo "$plugin $currentVersion has language changes";
	else
	    echo "$plugin $currentVersion needs version BUMP";
	fi
    fi
  fi
done

