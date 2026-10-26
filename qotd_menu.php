<?php
if (!defined('e107_INIT')) { exit; }

require_once(e_PLUGIN . 'qotd_menu/includes/qotd_manager.php');

e107::css('qotd_menu', 'css/qotd_menu.css');
e107::js('qotd_menu', 'js/qotd_menu.js', 'jquery');

$settings = e107_qotd_manager::getSettings();
$caption = $settings['header'] !== '' ? $settings['header'] : 'Quote of the Day';
$body = e107_qotd_manager::renderWidget(null, false);

if ((int)$settings['rendertype'] === 1) {
	echo $body;
} else {
	e107::getRender()->tablerender($caption, $body, 'qotd_menu');
}
