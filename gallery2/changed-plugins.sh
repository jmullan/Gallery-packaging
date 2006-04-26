#!/bin/sh
# Find which plugins need a version bump for the next release.
# Set CURRENT to where the current files for the next release are located
# (branches/BRANCH_2_X for patch release, trunk for major release unless
#  a branch for it has already been created)
LAST_TAG=RELEASE_2_1
CURRENT=branches/BRANCH_2_1

for pluginFile in `ls modules/*/module.inc themes/*/theme.inc`; do
  plugin=`echo $pluginFile | awk -F/ '{ print $2 }'`
  if [ $plugin = core ]; then continue; fi
  lastVersion=`GET https://svn.sourceforge.net/svnroot/gallery/tags/${LAST_TAG}/gallery2/${pluginFile} | awk -F"'" '/setVersion/ { print $2; exit }'`
  #currentVersion=`awk -F"'" '/setVersion/ { print $2; exit }' $pluginFile`
  currentVersion=`GET https://svn.sourceforge.net/svnroot/gallery/${CURRENT}/gallery2/${pluginFile} | awk -F"'" '/setVersion/ { print $2; exit }'`
  if [ "$currentVersion" = "$lastVersion" ]; then
    # Check if any files changed for this plugin
    svn diff --old=https://svn.sourceforge.net/svnroot/gallery/tags/${LAST_TAG}/gallery2 --new=https://svn.sourceforge.net/svnroot/gallery/trunk `dirname $pluginFile` | grep -q '^Index: '
    if [ $? -eq 0 ]; then
      echo $plugin
    fi
  fi
done

