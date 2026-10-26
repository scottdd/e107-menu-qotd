<?php
if (!defined('e107_INIT')) { exit; }

require_once(e_PLUGIN . 'qotd_menu/includes/qotd_manager.php');

class qotd_menu_shortcodes extends e_shortcode
{
	public function sc_qotd($parm = null)
	{
		e107::css('qotd_menu', 'css/qotd_menu.css');
		e107::js('qotd_menu', 'js/qotd_menu.js', 'jquery');
		return e107_qotd_manager::renderWidget(null, false);
	}

	public function sc_qotd_quote($parm = null)
	{
		$row = e107_qotd_manager::getQuote(false, 0);
		return e107_qotd_manager::formatQuoteBodyHtml((string)($row['qotd_quote'] ?? ''));
	}
}
