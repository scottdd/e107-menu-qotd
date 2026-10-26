<?php
if (!defined('e107_INIT')) { exit; }

require_once(__DIR__ . '/includes/qotd_manager.php');

$_qotdFolder = e107_qotd_manager::getPluginFolder();
e107::css($_qotdFolder, 'css/qotd_menu.css');
e107::js($_qotdFolder, 'js/qotd_menu.js', 'jquery');

$settings = e107_qotd_manager::getSettings();
$defaultCaption = defined('LAN_QOTD_DEFAULT_HEADER') ? LAN_QOTD_DEFAULT_HEADER : 'Quote of the Day';
$caption = $settings['header'] !== '' ? $settings['header'] : $defaultCaption;
$body = e107_qotd_manager::renderWidget(null, false);

if ((int)$settings['rendertype'] === 1) {
	echo $body;
} else {
	e107::getRender()->tablerender($caption, $body, 'qotd_menu');
}
