<?php
define('e_TOKEN_DISABLE', true);
require_once('../../class2.php');
require_once(e_PLUGIN . 'qotd_menu/includes/qotd_manager.php');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$excludeId = isset($_GET['exclude_id']) ? (int)$_GET['exclude_id'] : 0;
$row = e107_qotd_manager::getQuote(true, $excludeId);
$html = e107_qotd_manager::renderWidget($row, true);

echo json_encode(array(
	'ok'          => true,
	'id'          => (int)($row['qotd_id'] ?? 0),
	'quote'       => (string)($row['qotd_quote'] ?? ''),
	'attribution' => (string)($row['qotd_attribution'] ?? ''),
	'info'        => (string)($row['qotd_info'] ?? ''),
	'tags'        => (string)($row['qotd_tags'] ?? ''),
	'source_url'  => (string)($row['qotd_source_url'] ?? ''),
	'collection'  => (string)($row['qotd_collection'] ?? ''),
	'html'        => $html,
), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
