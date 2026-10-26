<?php
if (!defined('e107_INIT')) { exit; }

if ( defined('USER_AREA') && USER_AREA === true ) {
	require_once(__DIR__ . '/includes/qotd_manager.php');
	$_qotdFolder = e107_qotd_manager::getPluginFolder();
	e107::css($_qotdFolder, 'css/qotd_menu.css');
	e107::js($_qotdFolder, 'js/qotd_menu.js', 'jquery');
}
