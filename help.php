<?php
/**
 * Quote of the Day (qotd_menu) - Admin Sidebar Help
 *
 * @package     qotd_menu
 * @version     2.6.3
 * @author      cameron / Scott Dowlen <https://github.com/scottdd/e107-menu-qotd>
 * @license     GPL-2.0-or-later
 * @note        Imagined by human, built with AI.
 */
if (!defined('e107_INIT')) { exit; }

$caption = 'Quote of the Day (v2.6.3) Help';
$text = '<strong>Quote of the Day v2.6.3</strong><br><br>'
	. '• <strong>Quotes Manager:</strong> Add, edit, tag, or pin quotes for a specific date range (Start Date &rarr; End Date).<br>'
	. '• <strong>Display &amp; Rotation Settings:</strong> Choose Daily Quote of the Day or Random on Every Page Load, select active collections (<code>default</code>, <code>personal-qotd</code>, <code>spiceworks-wisdom</code>), and toggle tags or the AJAX "Another Quote" button.<br>'
	. '• <strong>GitHub &amp; File Sync:</strong> Sync structured JSON &amp; fortune collections directly with <code>scottdd/qotd-collections</code>.<br><hr style="margin:10px 0">'
	. '<small class="text-muted"><em>Imagined by human, built with AI.</em></small>';
e107::getRender()->tablerender($caption, $text);
