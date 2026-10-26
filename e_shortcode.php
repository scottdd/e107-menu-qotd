<?php
if (!defined('e107_INIT')) { exit; }

require_once(__DIR__ . '/includes/qotd_manager.php');

class qotd_menu_shortcodes extends e_shortcode
{
	public function sc_qotd($parm = null)
	{
		$folder = e107_qotd_manager::getPluginFolder();
		e107::css($folder, 'css/qotd_menu.css');
		e107::js($folder, 'js/qotd_menu.js', 'jquery');
		return e107_qotd_manager::renderWidget(null, false);
	}

	public function sc_qotd_quote($parm = null)
	{
		$row = e107_qotd_manager::getQuote(false, 0);
		return e107_qotd_manager::formatQuoteBodyHtml((string)($row['qotd_quote'] ?? ''));
	}
}

// 1) Support alternate valid PHP identifier folder names (e.g., e107_qotd_menu)
// so e107's shortcode_handler.php ($key . '_shortcodes') always resolves the class on PHP 7.4 and PHP 8.x.
$_qotdFolder = basename(__DIR__);
$_qotdDynamicClass = preg_replace('/[^a-zA-Z0-9_]/', '_', $_qotdFolder) . '_shortcodes';
if ($_qotdDynamicClass !== 'qotd_menu_shortcodes' && preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $_qotdDynamicClass) && !class_exists($_qotdDynamicClass, false))
{
	class_alias('qotd_menu_shortcodes', $_qotdDynamicClass);
}

// 2) Support hyphenated folder names (e.g., e107-qotd-menu or e107-menu-qotd-main)
// in e107_handlers/shortcode_handler.php::loadPluginShortcodes() scope before registerClassMethods() runs.
if (isset($classFunc) && is_string($classFunc) && !class_exists($classFunc, false))
{
	$classFunc = 'qotd_menu_shortcodes';
}
