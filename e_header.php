<?php
if (!defined('e107_INIT')) { exit; }

if ( defined('USER_AREA') && USER_AREA === true ) {
	e107::css('qotd_menu', 'css/qotd_menu.css');
	e107::js('qotd_menu', 'js/qotd_menu.js', 'jquery');
}
