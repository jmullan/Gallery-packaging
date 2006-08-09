#!/bin/sh
# Find which plugins need a version bump for the next release.
# Set CURRENT to where the current files for the next release are located
# (branches/BRANCH_2_X for patch release, trunk for major release unless
#  a branch for it has already been created)
LAST_TAG=RELEASE_2_1_1
CURRENT=trunk

for pluginFile in `ls modules/*/module.inc themes/*/theme.inc`; do
  plugin=`echo $pluginFile | awk -F/ '{ print $2 }'`
  if [ $plugin = core ]; then continue; fi
  lastVersion=`wget -q -O - https://svn.sourceforge.net/svnroot/gallery/tags/${LAST_TAG}/gallery2/${pluginFile} | awk -F"'" '/setVersion/ { print $2; exit }'`
  currentVersion=`wget -q -O - https://svn.sourceforge.net/svnroot/gallery/${CURRENT}/gallery2/${pluginFile} | awk -F"'" '/setVersion/ { print $2; exit }'`
  if [ "$currentVersion" != "$lastVersion" ]; then
    printf '%-15s %7s -> %7s'"\n" $plugin "$lastVersion" "$currentVersion"
  else
    # Check if any files changed for this plugin
    svn diff --old=https://svn.sourceforge.net/svnroot/gallery/tags/${LAST_TAG}/gallery2 \
	     --new=https://svn.sourceforge.net/svnroot/gallery/${CURRENT}/gallery2 \
	     `dirname $pluginFile` 2>/dev/null | grep -q '^Index: '
    if [ $? -eq 0 ]; then
      echo "$plugin $currentVersion needs version bump"
    else
      echo "$plugin unchanged"
    fi
  fi
done

