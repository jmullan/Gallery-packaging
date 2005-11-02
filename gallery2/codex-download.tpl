<?php if (isset($packages)): ?>
{| class="gallery" style="background-color: #eee; width: 560px"
! align="left" | Package
! align="left" | Version
! align="left" | Zip
! align="left" | Tar.gz
|-
<?php foreach ($packages as $package): ?>
| <?php print $package['release'] ?>

| '''<?php print $package['type'] ?>'''
| [<?php print $package['url']['zip'] . " " . $package['size']['zip'] ?>]
| [<?php print $package['url']['tar.gz']. " " . $package['size']['tar.gz'] ?>]
|-
<?php endforeach; ?>
|}
<?php endif; ?>
<?php if (isset($themes)): ?>
{| class="gallery" style="background-color: #eee"
! align="left" | Theme
! align="left" | Description
! Version
! Needs
! Zip
! Tar.gz
|-
<?php $first = 1; ?>
<?php foreach ($themes as $theme): ?>
| <?php print $theme['name'] ?>

| <?php print $theme['description'] ?>

| <?php print $theme['version'] ?>

<?php if ($first): $first = 0 ?>
| Core&nbsp;<?php print $theme['api']['required']['core']?><br>Theme&nbsp;<?php print $theme['api']['required']['theme']?>

<?php else: ?>
| <?php print $theme['api']['required']['core']?> / <?php print $theme['api']['required']['theme']?>

<?php endif; ?>
| [<?php print $theme['url']['zip']?> <?php print $theme['size']['zip']?>]
| [<?php print $theme['url']['tar.gz']?> <?php print $theme['size']['tar.gz']?>]
|-
<?php endforeach; ?>
|}
<?php endif; ?>
<?php if (isset($modules)): ?>
{| class="gallery" style="background-color: #eee"
<?php $lastGroup = null; ?>
<?php foreach ($modules as $module): ?>
<?php if ($lastGroup != $module['group']): ?>
! colspan="6" align="left" | <?php print $module['groupLabel']; $lastGroup = $module['group']; $first = 1;?>

|-
! align="left" | Module Name
! align="left" | Description
! Version
! Needs
! Zip
! Tar.gz
|-
<?php endif; ?>
| [[Gallery2:Modules:<?php print $module['id'] ?>|<?php print $module['name'] ?>]]
| <?php print $module['description'] ?>

| <?php print $module['version'] ?>

<?php if ($first): $first = 0 ?>
| Core&nbsp;<?php print $module['api']['required']['core']?><br>Module&nbsp;<?php print $module['api']['required']['module']?>

<?php else: ?>
| <?php print $module['api']['required']['core']?> / <?php print $module['api']['required']['module']?>

<?php endif; ?>
| [<?php print $module['url']['zip']?> <?php print $module['size']['zip']?>]
| [<?php print $module['url']['tar.gz']?> <?php print $module['size']['tar.gz']?>]
|-
<?php endforeach; ?>
|}
<?php endif; ?>
