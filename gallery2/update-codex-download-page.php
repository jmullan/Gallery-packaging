#!/usr/bin/php -f
<?php
if (!empty($_SERVER['SERVER_NAME'])) {
    print "You must run this from the command line\n";
    exit(1);
}

class GalleryStatusStub {
    function isError() {
	return false;
    }
}

class GalleryTranslatorStub {
    function translateDomain($domain, $data) {
	return array(new GalleryStatusStub(), $data['text']);
    }
}

class GalleryStub {
    function i18n($string) {
	return $string;
    }

    function getTranslator() {
	return new GalleryTranslatorStub();
    }
}
$gallery = new GalleryStub();

require_once(dirname(__FILE__) . '/../../../modules/core/classes/GalleryCoreApi.class');
require_once(dirname(__FILE__) . '/../../../modules/core/classes/GalleryModule.class');
require_once(dirname(__FILE__) . '/../../../modules/core/classes/GalleryTheme.class');

$RELEASE = 'gallery-2.0-rc-2';
$PACKAGE_TYPES = array('Typical', 'Full', 'Minimal', 'Developer');
$PACKAGE_DIR = '/usr/home/bharat/public_html/packaging/gallery2/combined/';
$DOWNLOAD_PREFIX = 'http://prdownloads.sourceforge.net/gallery/';

function updateDownloadPage() {
    $packages = getPackageHtml();
    $themes = getThemeHtml();
    $modules = getModuleHtml();

    /* Fetch current version */
    $body = `wget -q -O- http://codex.gallery2.org/index.php/Special:Export/Gallery2:Download`;

    /* Pull out the content we want and tokenize the parts we'll replace */
    $body = preg_replace('|.*<text>(.*)</text>.*|s', '$1', $body);
    $body = html_entity_decode($body);
    foreach (array('PACKAGES' => $packages, 'MODULES' => $modules, 'THEMES' => $themes)
	     as $key => $value) {
	$body = preg_replace("|(<!--${key}_START-->).*(<!--${key}_END-->)|s",
			     '$1' . "\n" . $value . '$2', $body);
    }

    print $body;
}

function getPackageHtml() {
    global $DOWNLOAD_PREFIX, $RELEASE, $PACKAGE_TYPES;

    $packages = array();
    foreach ($PACKAGE_TYPES as $type) {
	$package['type'] = $type;
	$package['release'] = $RELEASE;

	/* Get the filesize of the packages */
	$fileName = sprintf('%s-%s', $package['release'], strtolower($type));
	$package['fileName'] = $fileName;
	foreach (array('zip', 'tar.gz') as $fileType) {
	    $size = getFilesize($fileName . '.' . $fileType);
	    if (empty($size)) {
		$errors[] = 'could not get filesize of ' . $fileType . ' for ' . $fileName;
	    }
	    $package['size'][$fileType] = $size;
	    $package['url'][$fileType] = $DOWNLOAD_PREFIX . $package['fileName'] . '.' . $fileType;
	}
	$packages[] = $package;
    }

    ob_start();
    include(dirname(__FILE__) . '/codex-download.tpl');
    $html = ob_get_contents();
    ob_end_clean();

    return $html;
}

function getModuleHtml() {
    global $DOWNLOAD_PREFIX;
    $moduleFilePrefix = 'g2-module';

    foreach (glob(dirname(__FILE__) . '/../../../modules/*/module.inc') as $moduleInc) {
	$moduleId = basename(dirname($moduleInc));
	if ($moduleId == 'core') {
	    continue;
	}

	include($moduleInc);
	$moduleClass = "${moduleId}Module";
	$module = new $moduleClass;

	$entry = array();
	$entry['name'] = $module->translate($module->getName());
	$entry['api']['required']['module'] = join('.', $module->getRequiredModuleApi());
	$entry['api']['required']['core'] = join('.', $module->getRequiredCoreApi());
	$entry['description'] = $module->translate($module->getDescription());
	$entry['version'] = $module->getVersion();
	$entry['id'] = $module->getId();
	$group = $module->getGroup();
	$entry = array_merge($entry,
			     empty($group)
			     ? array('group' => 'other', 'groupLabel' => 'Other')
			     : $group);

	/* Get the filesize */
	$fileName = sprintf('%s-%s-%s', $moduleFilePrefix, $entry['id'], $entry['version']);
	$entry['fileName'] = $fileName;
	foreach (array('zip', 'tar.gz') as $fileType) {
	    $size = getFilesize($fileName . '.' . $fileType);
	    if (empty($size)) {
		$errors[] = 'could not get filesize of ' . $fileType . ' for ' . $fileName;
	    }
	    $entry['size'][$fileType] = $size;
	    $entry['url'][$fileType] = $DOWNLOAD_PREFIX . $fileName . '.' . $fileType;
	}
	$modules[] = $entry;
    }

    usort($modules, 'moduleSort');

    ob_start();
    include(dirname(__FILE__) . '/codex-download.tpl');
    $html = ob_get_contents();
    ob_end_clean();

    return $html;
}

function getThemeHtml() {
    $themeFilePrefix = 'g2-theme';
    global $DOWNLOAD_PREFIX;

    foreach (glob(dirname(__FILE__) . '/../../../themes/*/theme.inc') as $themeInc) {
	include($themeInc);
	$themeId = basename(dirname($themeInc));
	$themeClass = "{$themeId}Theme";
	$theme = new $themeClass;

	$entry = array();
	$entry['name'] = $theme->getName();
	$entry['id'] = $theme->getId();
	$entry['l10Domain'] = $theme->getL10Domain();
	$entry['api']['required']['theme'] = join('.', $theme->getRequiredThemeApi());
	$entry['api']['required']['core'] = join('.', $theme->getRequiredCoreApi());
	$entry['description'] = $theme->getDescription();
	$entry['version'] = $theme->getVersion();
	$fileName = sprintf('%s-%s-%s', $themeFilePrefix, $entry['id'], $entry['version']);
	$entry['fileName'] = $fileName;
	foreach (array('zip', 'tar.gz') as $fileType) {
	    $size = getFilesize($fileName . '.' . $fileType);
	    if (empty($size)) {
		$errors[] = 'could not get filesize of ' . $fileType . ' for ' . $fileName;
	    }
	    $entry['size'][$fileType] = $size;
	    $entry['url'][$fileType] = $DOWNLOAD_PREFIX . $fileName . '.' . $fileType;
	}
	$themes[] = $entry;
    }

    ob_start();
    include(dirname(__FILE__) . '/codex-download.tpl');
    $html = ob_get_contents();
    ob_end_clean();

    return $html;
}

function getFilesize($fileName) {
    global $PACKAGE_DIR;

    $path = $PACKAGE_DIR . $fileName;

    $size = '';
    if (file_exists($path)) {
	$stat = stat($path);
	if ($stat && isset($stat['size'])) {
	    $size = $stat['size'];
	    if ($size > 1024*1024) {
		$size = round($size / (1024*1024), 1) . 'MB';
	    } elseif ($size > 1024) {
		$size = (int)($size / 1024) . 'KB';
	    } else {
		$size = $size  . 'B';
	    }
	}
    }

    return $size;
}

function moduleSort($a, $b) {
    static $groupOrder;
    if (!isset($groupOrder)) {
	/* gallery first, toolkits second, other last */
	$groupOrder = array('gallery' => 1, 'toolkits' => 2, '' => 3, 'other' => 4);
    }
    $ag = $a['group'];
    $bg = $b['group'];
    if ($ag != $bg) {
	$ao = isset($groupOrder[$ag]) ? $groupOrder[$ag] : $groupOrder[''];
	$bo = isset($groupOrder[$bg]) ? $groupOrder[$bg] : $groupOrder[''];
	if ($ao != $bo) {
	    return ($ao > $bo) ? 1 : -1;
	}
	$ag = isset($a['groupLabel']) ? $a['groupLabel'] : $ag;
	$bg = isset($b['groupLabel']) ? $b['groupLabel'] : $bg;
	return ($ag > $bg) ? 1 : -1;
    } else {
	$an = $a['name'];
	$bn = $b['name'];
	if ($an == $bn) {
	    return 0;
	} else {
	    return ($an > $bn) ? 1 : -1;
	}
    }
}

updateDownloadPage();
?>