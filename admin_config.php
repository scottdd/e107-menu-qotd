<?php
/**
 * Quote of the Day (qotd_menu) v2.6.0 — Admin Configuration UI
 *
 * @package     qotd_menu
 * @version     2.6.0
 * @author      cameron / Scott Dowlen <https://github.com/scottdd/e107-qotd-menu>
 * @license     GPL-2.0-or-later
 * @note        Imagined by human, built with AI.
 */
require_once('../../class2.php');
if (!getperms('P')) {
	e107::redirect('admin');
	exit;
}

require_once(e_ADMIN . 'auth.php');
require_once(e_PLUGIN . 'qotd_menu/includes/qotd_manager.php');

function qotd_menu_render_admin(): void
{
	e107_qotd_manager::ensureSchema();
$sql  = e107::getDb();
$tp   = e107::getParser();
$mes  = e107::getMessage();
$cfg  = e107::getConfig();

$activeTab = isset($_GET['tab']) ? (string)$_GET['tab'] : 'quotes';
if (!in_array($activeTab, array('quotes', 'settings', 'sync'), true)) {
	$activeTab = 'quotes';
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!empty($_POST['qotd_save_settings'])) {
		$activeTab = 'settings';
		$cols = isset($_POST['qotd_collections']) && is_array($_POST['qotd_collections'])
			? array_values(array_filter(array_map('trim', $_POST['qotd_collections'])))
			: array('default');
		if (empty($cols)) {
			$cols = array('default');
		}

		$cfg->set('qotd_header', trim((string)($_POST['qotd_header'] ?? 'Quote of the Day')));
		$cfg->set('qotd_rotation_mode', in_array($_POST['qotd_rotation_mode'] ?? 'daily', array('daily', 'random'), true) ? $_POST['qotd_rotation_mode'] : 'daily');
		$cfg->set('qotd_source_mode', in_array($_POST['qotd_source_mode'] ?? 'db', array('db', 'file'), true) ? $_POST['qotd_source_mode'] : 'db');
		$cfg->set('qotd_table', ($_POST['qotd_source_mode'] ?? 'db') === 'db' ? 1 : 0);
		$cfg->set('qotd_collections', implode(',', $cols));
		$cfg->set('qotd_file', trim((string)($_POST['qotd_file'] ?? 'quotes/default.json')));
		$cfg->set('qotd_show_tags', !empty($_POST['qotd_show_tags']) ? 1 : 0);
		$cfg->set('qotd_show_source', !empty($_POST['qotd_show_source']) ? 1 : 0);
		$cfg->set('qotd_show_next_btn', !empty($_POST['qotd_show_next_btn']) ? 1 : 0);
		$cfg->set('qotd_rendertype', !empty($_POST['qotd_rendertype']) ? 1 : 0);
		$cfg->save(false, true, false);
		$mes->addSuccess('Display & Rotation settings saved.');
	}

	if (!empty($_POST['qotd_save_quote'])) {
		$activeTab = 'quotes';
		$editId = isset($_POST['qotd_id']) ? (int)$_POST['qotd_id'] : 0;
		$quoteText = trim((string)($_POST['qotd_quote'] ?? ''));
		$attr = substr(trim((string)($_POST['qotd_attribution'] ?? '')), 0, 150);
		$info = substr(trim((string)($_POST['qotd_info'] ?? '')), 0, 255);
		$tags = substr(trim((string)($_POST['qotd_tags'] ?? '')), 0, 255);
		$url  = substr(trim((string)($_POST['qotd_source_url'] ?? '')), 0, 255);
		$col  = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_POST['qotd_collection'] ?? 'default'));
		if ($col === '') {
			$col = 'default';
		}
		$pinStart = trim((string)($_POST['qotd_pin_start'] ?? ''));
		$pinEnd   = trim((string)($_POST['qotd_pin_end'] ?? ''));
		$pinStartVal = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $pinStart)) ? $pinStart : null;
		$pinEndVal   = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $pinEnd)) ? $pinEnd : null;
		if ($pinStartVal !== null && $pinEndVal === null) {
			$pinEndVal = $pinStartVal;
		}
		if ($pinEndVal !== null && $pinStartVal === null) {
			$pinStartVal = $pinEndVal;
		}
		$active = !empty($_POST['qotd_active']) ? 1 : 0;

		if ($quoteText === '') {
			$mes->addError('Quote text cannot be empty.');
		} else {
			$prefix = MPREFIX;
			$table = $prefix . 'qotd';
			$qEsc = $sql->escape($quoteText, false);
			$aEsc = $sql->escape($attr, false);
			$iEsc = $sql->escape($info, false);
			$tEsc = $sql->escape($tags, false);
			$uEsc = $sql->escape($url, false);
			$cEsc = $sql->escape($col, false);
			$psSql = $pinStartVal !== null ? "'" . $sql->escape($pinStartVal, false) . "'" : "NULL";
			$peSql = $pinEndVal !== null ? "'" . $sql->escape($pinEndVal, false) . "'" : "NULL";

			if ($editId > 0) {
				$updSql = "UPDATE `{$table}` SET
					`qotd_quote` = '{$qEsc}',
					`qotd_attribution` = '{$aEsc}',
					`qotd_info` = '{$iEsc}',
					`qotd_tags` = '{$tEsc}',
					`qotd_source_url` = '{$uEsc}',
					`qotd_collection` = '{$cEsc}',
					`qotd_pin_start` = {$psSql},
					`qotd_pin_end` = {$peSql},
					`qotd_active` = {$active}
					WHERE `qotd_id` = {$editId}";
				$sql->gen($updSql);
				$mes->addSuccess("Updated quote #{$editId}.");
			} else {
				$insSql = "INSERT INTO `{$table}` (`qotd_quote`, `qotd_attribution`, `qotd_info`, `qotd_tags`, `qotd_source_url`, `qotd_collection`, `qotd_pin_start`, `qotd_pin_end`, `qotd_active`)
					VALUES ('{$qEsc}', '{$aEsc}', '{$iEsc}', '{$tEsc}', '{$uEsc}', '{$cEsc}', {$psSql}, {$peSql}, {$active})";
				$sql->gen($insSql);
				$mes->addSuccess('Added new quote to database.');
			}
		}
	}

	if (!empty($_POST['qotd_delete_id'])) {
		$activeTab = 'quotes';
		$delId = (int)$_POST['qotd_delete_id'];
		if ($delId > 0) {
			$sql->delete('qotd', 'qotd_id=' . $delId);
			$mes->addSuccess("Deleted quote #{$delId}.");
		}
	}

	if (!empty($_POST['qotd_pin_week_id'])) {
		$activeTab = 'quotes';
		$pinId = (int)$_POST['qotd_pin_week_id'];
		if ($pinId > 0) {
			$start = date('Y-m-d');
			$end = date('Y-m-d', strtotime('+6 days'));
			$prefix = MPREFIX;
			$sql->gen("UPDATE `{$prefix}qotd` SET `qotd_pin_start` = '{$start}', `qotd_pin_end` = '{$end}' WHERE `qotd_id` = {$pinId}");
			$mes->addSuccess("Pinned quote #{$pinId} for {$start} through {$end}.");
		}
	}

	if (!empty($_POST['qotd_unpin_id'])) {
		$activeTab = 'quotes';
		$unpinId = (int)$_POST['qotd_unpin_id'];
		if ($unpinId > 0) {
			$prefix = MPREFIX;
			$sql->gen("UPDATE `{$prefix}qotd` SET `qotd_pin_start` = NULL, `qotd_pin_end` = NULL WHERE `qotd_id` = {$unpinId}");
			$mes->addSuccess("Cleared pinned date range for quote #{$unpinId}.");
		}
	}

	if (!empty($_POST['qotd_import_bundled'])) {
		$activeTab = 'sync';
		$replace = !empty($_POST['qotd_replace_existing']);
		$res = e107_qotd_manager::importFromBundled(array('default', 'personal-qotd', 'spiceworks-wisdom'), $replace);
		$upd = (int)($res['updated'] ?? 0);
		$mes->addSuccess("Bundled import complete: {$res['added']} added, {$upd} updated, {$res['skipped']} unchanged.");
	}

	if (!empty($_POST['qotd_sync_github'])) {
		$activeTab = 'sync';
		$repo = trim((string)($_POST['qotd_github_repo'] ?? 'scottdd/qotd-collections'));
		$branch = trim((string)($_POST['qotd_github_branch'] ?? 'main'));
		$cfg->set('qotd_github_repo', $repo);
		$cfg->set('qotd_github_branch', $branch);
		$cfg->save(false, true, false);

		$res = e107_qotd_manager::syncFromGitHub($repo, $branch, array('default', 'personal-qotd', 'spiceworks-wisdom'));
		if (!empty($res['errors'])) {
			foreach ($res['errors'] as $err) {
				$mes->addWarning($err);
			}
		}
		$fetchedStr = !empty($res['fetched']) ? implode(', ', $res['fetched']) : 'none';
		$upd = (int)($res['updated'] ?? 0);
		$mes->addSuccess("GitHub sync ({$repo}@{$branch}) fetched [{$fetchedStr}]: {$res['added']} new quotes imported, {$upd} existing quotes updated, {$res['skipped']} already up to date.");
	}

	if (!empty($_POST['qotd_export_files'])) {
		$activeTab = 'sync';
		$written = e107_qotd_manager::exportDatabaseToFiles();
		$parts = array();
		foreach ($written as $col => $cnt) {
			$parts[] = "{$col} ({$cnt} quotes)";
		}
		$mes->addSuccess('Exported database quotes to structured JSON & fortune files: ' . implode(', ', $parts));
	}
}

$settings = e107_qotd_manager::getSettings();
$today = date('Y-m-d');

// Gather stats
$totalCount = (int)$sql->count('qotd');
$defaultCount = (int)$sql->count('qotd', '(*)', "WHERE qotd_collection = 'default'");
$personalCount = (int)$sql->count('qotd', '(*)', "WHERE qotd_collection = 'personal-qotd'");
$spiceworksCount = (int)$sql->count('qotd', '(*)', "WHERE qotd_collection = 'spiceworks-wisdom'");
$pinnedTodayCount = (int)$sql->count('qotd', '(*)', "WHERE qotd_active = 1 AND qotd_pin_start IS NOT NULL AND qotd_pin_end IS NOT NULL AND qotd_pin_start <= '{$today}' AND qotd_pin_end >= '{$today}'");

// Check if editing a specific quote
$editRow = array(
	'qotd_id'          => 0,
	'qotd_quote'       => '',
	'qotd_attribution' => '',
	'qotd_info'        => '',
	'qotd_tags'        => '',
	'qotd_source_url'  => '',
	'qotd_collection'  => 'default',
	'qotd_pin_start'   => '',
	'qotd_pin_end'     => '',
	'qotd_active'      => 1,
);
if (isset($_GET['edit']) && (int)$_GET['edit'] > 0) {
	$activeTab = 'quotes';
	$found = $sql->retrieve('qotd', '*', 'qotd_id=' . (int)$_GET['edit']);
	if (is_array($found) && !empty($found['qotd_id'])) {
		$editRow = $found;
	}
}

$html = '
<style>
.qotd-admin-stats { display:flex; flex-wrap:wrap; gap:12px; margin-bottom:18px; }
.qotd-stat-card { flex:1 1 180px; background:rgba(30,41,59,0.55); border:1px solid rgba(148,163,184,0.25); border-radius:8px; padding:12px 16px; }
.qotd-stat-label { font-size:12px; text-transform:uppercase; letter-spacing:0.04em; opacity:0.75; margin-bottom:4px; }
.qotd-stat-val { font-size:20px; font-weight:700; }
.qotd-admin-tabs { margin-bottom:18px; border-bottom:1px solid rgba(148,163,184,0.25); display:flex; gap:8px; padding-bottom:8px; }
.qotd-admin-tabs a { padding:7px 14px; border-radius:6px; text-decoration:none; font-weight:600; border:1px solid transparent; }
.qotd-admin-tabs a.active { background:#2563eb; color:#fff; border-color:#3b82f6; }
.qotd-admin-panel { background:rgba(15,23,42,0.35); border:1px solid rgba(148,163,184,0.22); border-radius:8px; padding:16px; margin-bottom:18px; }
.qotd-admin-panel h4 { margin-top:0; margin-bottom:12px; font-weight:600; }
.qotd-badge-col { display:inline-block; padding:2px 8px; border-radius:999px; font-size:11px; font-weight:600; background:rgba(59,130,246,0.18); border:1px solid rgba(59,130,246,0.4); }
.qotd-badge-tag { display:inline-block; padding:1px 7px; border-radius:999px; font-size:11px; margin:1px 2px; background:rgba(148,163,184,0.16); border:1px solid rgba(148,163,184,0.32); }
.qotd-badge-pin { display:inline-block; padding:2px 8px; border-radius:6px; font-size:11px; font-weight:600; background:rgba(245,158,11,0.2); border:1px solid rgba(245,158,11,0.5); }
</style>';

$rotLabel = ($settings['rotation_mode'] === 'daily') ? 'Daily Quote of the Day' : 'Random on Page Load';
$srcLabel = ($settings['source_mode'] === 'db') ? 'MySQL Database (' . implode(', ', $settings['collections']) . ')' : 'File (' . htmlspecialchars($settings['file'], ENT_QUOTES, 'UTF-8') . ')';

$html .= '<div class="qotd-admin-stats">
	<div class="qotd-stat-card">
		<div class="qotd-stat-label">Total Quotes in DB</div>
		<div class="qotd-stat-val">' . $totalCount . '</div>
	</div>
	<div class="qotd-stat-card">
		<div class="qotd-stat-label">Default Collection</div>
		<div class="qotd-stat-val">' . $defaultCount . '</div>
	</div>
	<div class="qotd-stat-card">
		<div class="qotd-stat-label">Personal QotD</div>
		<div class="qotd-stat-val">' . $personalCount . '</div>
	</div>
	<div class="qotd-stat-card">
		<div class="qotd-stat-label">Spiceworks Wisdom</div>
		<div class="qotd-stat-val">' . $spiceworksCount . '</div>
	</div>
	<div class="qotd-stat-card">
		<div class="qotd-stat-label">Pinned Today (' . $today . ')</div>
		<div class="qotd-stat-val">' . $pinnedTodayCount . '</div>
	</div>
	<div class="qotd-stat-card">
		<div class="qotd-stat-label">Active Rotation / Source</div>
		<div class="qotd-stat-val" style="font-size:14px;line-height:1.35;">' . htmlspecialchars($rotLabel, ENT_QUOTES, 'UTF-8') . '<br><small style="opacity:0.75;font-weight:400;">' . $srcLabel . '</small></div>
	</div>
</div>';

$html .= '<div class="qotd-admin-tabs">
	<a href="admin_config.php?tab=quotes" class="' . ($activeTab === 'quotes' ? 'active' : '') . '">&#128221; Quotes Manager &amp; Pinned Dates</a>
	<a href="admin_config.php?tab=settings" class="' . ($activeTab === 'settings' ? 'active' : '') . '">&#9881; Display &amp; Rotation Settings</a>
	<a href="admin_config.php?tab=sync" class="' . ($activeTab === 'sync' ? 'active' : '') . '">&#128259; GitHub &amp; Dual-Format Sync</a>
</div>';

if ($activeTab === 'quotes') {
	$formTitle = ((int)$editRow['qotd_id'] > 0)
		? 'Edit Quote #' . (int)$editRow['qotd_id'] . ' <a href="admin_config.php?tab=quotes" class="btn btn-xs btn-default" style="margin-left:10px;">Cancel Edit</a>'
		: 'Add New Quote (or Pin a Quote by Date Range)';

	$html .= '<div class="qotd-admin-panel">
		<h4>' . $formTitle . '</h4>
		<form method="post" action="admin_config.php?tab=quotes">
			<input type="hidden" name="qotd_id" value="' . (int)$editRow['qotd_id'] . '">
			<div class="row" style="margin-bottom:10px;">
				<div class="col-md-12">
					<label><strong>Quote Text</strong></label>
					<textarea name="qotd_quote" rows="3" class="form-control" style="width:100%;" required placeholder="Enter the quote text...">' . htmlspecialchars((string)$editRow['qotd_quote'], ENT_QUOTES, 'UTF-8') . '</textarea>
				</div>
			</div>
			<div class="row" style="margin-bottom:10px;">
				<div class="col-md-4">
					<label><strong>Attribution (Author / Handle)</strong></label>
					<input type="text" name="qotd_attribution" class="form-control" style="width:100%;" value="' . htmlspecialchars((string)$editRow['qotd_attribution'], ENT_QUOTES, 'UTF-8') . '" placeholder="e.g. C.S. Lewis">
				</div>
				<div class="col-md-4">
					<label><strong>Attribution Info (Book / Role / Context)</strong></label>
					<input type="text" name="qotd_info" class="form-control" style="width:100%;" value="' . htmlspecialchars((string)$editRow['qotd_info'], ENT_QUOTES, 'UTF-8') . '" placeholder="e.g. Mere Christianity (1952)">
				</div>
				<div class="col-md-4">
					<label><strong>Topical Tags (comma-separated)</strong></label>
					<input type="text" name="qotd_tags" class="form-control" style="width:100%;" value="' . htmlspecialchars((string)$editRow['qotd_tags'], ENT_QUOTES, 'UTF-8') . '" placeholder="e.g. faith, wisdom, humor">
				</div>
			</div>
			<div class="row" style="margin-bottom:12px;">
				<div class="col-md-3">
					<label><strong>Collection</strong></label>
					<select name="qotd_collection" class="form-control" style="width:100%;">
						<option value="default"' . ($editRow['qotd_collection'] === 'default' ? ' selected' : '') . '>default (Default — General Wisdom &amp; Humor)</option>
						<option value="personal-qotd"' . ($editRow['qotd_collection'] === 'personal-qotd' ? ' selected' : '') . '>personal-qotd (Personal QotD)</option>
						<option value="spiceworks-wisdom"' . ($editRow['qotd_collection'] === 'spiceworks-wisdom' ? ' selected' : '') . '>spiceworks-wisdom (Spiceworks IT Wisdom)</option>
					</select>
				</div>
				<div class="col-md-3">
					<label><strong>Source URL (optional)</strong></label>
					<input type="url" name="qotd_source_url" class="form-control" style="width:100%;" value="' . htmlspecialchars((string)$editRow['qotd_source_url'], ENT_QUOTES, 'UTF-8') . '" placeholder="https://...">
				</div>
				<div class="col-md-2">
					<label><strong>&#128204; Pin Start Date</strong></label>
					<input type="date" name="qotd_pin_start" class="form-control" style="width:100%;" value="' . htmlspecialchars((string)($editRow['qotd_pin_start'] ?? ''), ENT_QUOTES, 'UTF-8') . '">
				</div>
				<div class="col-md-2">
					<label><strong>&#128204; Pin End Date</strong></label>
					<input type="date" name="qotd_pin_end" class="form-control" style="width:100%;" value="' . htmlspecialchars((string)($editRow['qotd_pin_end'] ?? ''), ENT_QUOTES, 'UTF-8') . '">
				</div>
				<div class="col-md-2" style="padding-top:24px;">
					<label style="margin-right:12px;"><input type="checkbox" name="qotd_active" value="1"' . (!empty($editRow['qotd_active']) ? ' checked' : '') . '> Active</label>
					<button type="submit" name="qotd_save_quote" value="1" class="btn btn-primary">' . ((int)$editRow['qotd_id'] > 0 ? 'Update Quote' : 'Save Quote') . '</button>
				</div>
			</div>
		</form>
	</div>';

	// Filter bar
	$filterCol = isset($_GET['col']) ? (string)$_GET['col'] : 'all';
	$searchQ = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
	$page = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
	$perPage = 25;
	$offset = ($page - 1) * $perPage;

	$whereParts = array('1=1');
	if (in_array($filterCol, array('default', 'personal-qotd', 'spiceworks-wisdom'), true)) {
		$whereParts[] = "qotd_collection = '" . $sql->escape($filterCol, false) . "'";
	} elseif ($filterCol === 'pinned') {
		$whereParts[] = "qotd_pin_start IS NOT NULL AND qotd_pin_end IS NOT NULL";
	}
	if ($searchQ !== '') {
		$qEsc = $sql->escape($searchQ, false);
		$whereParts[] = "(qotd_quote LIKE '%{$qEsc}%' OR qotd_attribution LIKE '%{$qEsc}%' OR qotd_tags LIKE '%{$qEsc}%')";
	}
	$whereSql = implode(' AND ', $whereParts);
	$filteredCount = (int)$sql->count('qotd', '(*)', 'WHERE ' . $whereSql);
	$totalPages = max(1, (int)ceil($filteredCount / $perPage));

	$rows = $sql->retrieve('qotd', '*', "WHERE {$whereSql} ORDER BY (qotd_pin_start IS NOT NULL) DESC, qotd_pin_start DESC, qotd_id ASC LIMIT {$offset}, {$perPage}", true);
	if (!is_array($rows)) {
		$rows = array();
	}

	$html .= '<div class="qotd-admin-panel">
		<form method="get" action="admin_config.php" style="display:flex; flex-wrap:wrap; gap:10px; align-items:center; margin-bottom:14px;">
			<input type="hidden" name="tab" value="quotes">
			<strong>Filter Collection:</strong>
			<select name="col" class="form-control" style="width:auto; display:inline-block;">
				<option value="all"' . ($filterCol === 'all' ? ' selected' : '') . '>All Collections (' . $totalCount . ')</option>
				<option value="default"' . ($filterCol === 'default' ? ' selected' : '') . '>default (' . $defaultCount . ')</option>
				<option value="personal-qotd"' . ($filterCol === 'personal-qotd' ? ' selected' : '') . '>personal-qotd (' . $personalCount . ')</option>
				<option value="spiceworks-wisdom"' . ($filterCol === 'spiceworks-wisdom' ? ' selected' : '') . '>spiceworks-wisdom (' . $spiceworksCount . ')</option>
				<option value="pinned"' . ($filterCol === 'pinned' ? ' selected' : '') . '>&#128204; Pinned Date Range Only</option>
			</select>
			<input type="text" name="q" class="form-control" style="width:240px; display:inline-block;" value="' . htmlspecialchars($searchQ, ENT_QUOTES, 'UTF-8') . '" placeholder="Filter by quote, author, or tag...">
			<button type="submit" class="btn btn-default">Apply</button>
			<span style="margin-left:auto; opacity:0.8;">Showing ' . count($rows) . ' of ' . $filteredCount . ' quotes (Page ' . $page . ' of ' . $totalPages . ')</span>
		</form>

		<table class="table adminlist table-striped">
			<thead>
				<tr>
					<th style="width:50px;">ID</th>
					<th>Quote</th>
					<th style="width:200px;">Attribution &amp; Info</th>
					<th style="width:170px;">Tags &amp; Collection</th>
					<th style="width:175px;">&#128204; Pinned Date Range</th>
					<th style="width:170px; text-align:right;">Actions</th>
				</tr>
			</thead>
			<tbody>';

	foreach ($rows as $r) {
		$rid = (int)$r['qotd_id'];
		$tagsArr = array_values(array_filter(array_map('trim', explode(',', (string)$r['qotd_tags']))));
		$tagsHtml = '';
		foreach ($tagsArr as $t) {
			$tagsHtml .= '<span class="qotd-badge-tag">#' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '</span>';
		}
		$pinHtml = '<span style="opacity:0.5;">—</span>';
		if (!empty($r['qotd_pin_start']) && !empty($r['qotd_pin_end'])) {
			$isActivePin = ($r['qotd_pin_start'] <= $today && $r['qotd_pin_end'] >= $today);
			$pinHtml = '<span class="qotd-badge-pin">' . ($isActivePin ? '&#128204; ACTIVE: ' : '&#128197; ')
				. htmlspecialchars($r['qotd_pin_start'], ENT_QUOTES, 'UTF-8') . ' &rarr; ' . htmlspecialchars($r['qotd_pin_end'], ENT_QUOTES, 'UTF-8') . '</span>';
		}

		$srcHtml = '';
		if (!empty($r['qotd_source_url'])) {
			$srcHtml = '<div><a href="' . htmlspecialchars($r['qotd_source_url'], ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer" style="font-size:11px;">&#8599; Source Link</a></div>';
		}

		$html .= '<tr>
			<td>#' . $rid . '</td>
			<td>' . nl2br(htmlspecialchars((string)$r['qotd_quote'], ENT_QUOTES, 'UTF-8')) . '</td>
			<td>
				<strong>' . htmlspecialchars((string)$r['qotd_attribution'], ENT_QUOTES, 'UTF-8') . '</strong>'
				. (!empty($r['qotd_info']) ? '<div style="font-size:12px;opacity:0.8;font-style:italic;">' . htmlspecialchars((string)$r['qotd_info'], ENT_QUOTES, 'UTF-8') . '</div>' : '')
				. $srcHtml . '
			</td>
			<td>
				<div style="margin-bottom:4px;"><span class="qotd-badge-col">' . htmlspecialchars((string)$r['qotd_collection'], ENT_QUOTES, 'UTF-8') . '</span></div>
				<div>' . $tagsHtml . '</div>
			</td>
			<td>' . $pinHtml . '</td>
			<td style="text-align:right; white-space:nowrap;">
				<a href="admin_config.php?tab=quotes&amp;edit=' . $rid . '" class="btn btn-xs btn-primary" title="Edit quote or set custom pin dates">Edit</a> ';
		if (!empty($r['qotd_pin_start'])) {
			$html .= '<form method="post" action="admin_config.php?tab=quotes" style="display:inline;">
				<input type="hidden" name="qotd_unpin_id" value="' . $rid . '">
				<button type="submit" class="btn btn-xs btn-warning" title="Clear pinned dates">Unpin</button>
			</form> ';
		} else {
			$html .= '<form method="post" action="admin_config.php?tab=quotes" style="display:inline;">
				<input type="hidden" name="qotd_pin_week_id" value="' . $rid . '">
				<button type="submit" class="btn btn-xs btn-info" title="Pin this quote for 7 days starting today">&#128204; Pin 7d</button>
			</form> ';
		}
		$html .= '<form method="post" action="admin_config.php?tab=quotes" style="display:inline;" onsubmit="return confirm(\'Delete quote #' . $rid . '?\');">
				<input type="hidden" name="qotd_delete_id" value="' . $rid . '">
				<button type="submit" class="btn btn-xs btn-danger">Delete</button>
			</form>
			</td>
		</tr>';
	}

	$html .= '</tbody></table>';

	if ($totalPages > 1) {
		$html .= '<div style="display:flex; gap:6px; justify-content:center; margin-top:12px;">';
		for ($p = 1; $p <= $totalPages; $p++) {
			$url = 'admin_config.php?tab=quotes&amp;col=' . urlencode($filterCol) . '&amp;q=' . urlencode($searchQ) . '&amp;p=' . $p;
			$cls = ($p === $page) ? 'btn btn-xs btn-primary' : 'btn btn-xs btn-default';
			$html .= '<a class="' . $cls . '" href="' . $url . '">' . $p . '</a>';
		}
		$html .= '</div>';
	}
	$html .= '</div>';
}

if ($activeTab === 'settings') {
	$activeCols = $settings['collections'];
	$html .= '<div class="qotd-admin-panel">
		<h4>Display &amp; Rotation Configuration</h4>
		<form method="post" action="admin_config.php?tab=settings">
			<table class="table adminform">
				<tr>
					<td style="width:280px;"><strong>Menu Header Caption</strong></td>
					<td><input type="text" name="qotd_header" class="form-control" style="max-width:360px;" value="' . htmlspecialchars($settings['header'], ENT_QUOTES, 'UTF-8') . '"></td>
				</tr>
				<tr>
					<td><strong>Quote Rotation Mode</strong><br><small style="opacity:0.75;">Pinned quotes with an active date range always take priority.</small></td>
					<td>
						<label style="display:block; margin-bottom:6px;">
							<input type="radio" name="qotd_rotation_mode" value="daily"' . ($settings['rotation_mode'] === 'daily' ? ' checked' : '') . '>
							<strong>True Quote of the Day (Daily)</strong> &mdash; Deterministic daily selection so all visitors see the same quote throughout the day
						</label>
						<label style="display:block;">
							<input type="radio" name="qotd_rotation_mode" value="random"' . ($settings['rotation_mode'] === 'random' ? ' checked' : '') . '>
							<strong>Random on Every Page Load</strong> &mdash; Selects a random quote from the active pool on each page request
						</label>
					</td>
				</tr>
				<tr>
					<td><strong>Quote Data Source</strong></td>
					<td>
						<label style="display:block; margin-bottom:6px;">
							<input type="radio" name="qotd_source_mode" value="db"' . ($settings['source_mode'] === 'db' ? ' checked' : '') . '>
							<strong>MySQL Database Table (<code>e107_qotd</code>)</strong> &mdash; Full structured fields, collection filtering, and pinned date ranges
						</label>
						<label style="display:block;">
							<input type="radio" name="qotd_source_mode" value="file"' . ($settings['source_mode'] === 'file' ? ' checked' : '') . '>
							<strong>Direct Quote File (Structured <code>.json</code>, Fortune <code>.fortune</code>/<code>.txt</code>, or Legacy <code>quote.txt</code>)</strong>
						</label>
					</td>
				</tr>
				<tr>
					<td><strong>Active Collections (Database Mode)</strong></td>
					<td>
						<label style="margin-right:18px;">
							<input type="checkbox" name="qotd_collections[]" value="default"' . (in_array('default', $activeCols, true) ? ' checked' : '') . '>
							<strong>default</strong> (General Wisdom &amp; Humor &mdash; ' . $defaultCount . ' quotes)
						</label>
						<label style="margin-right:18px;">
							<input type="checkbox" name="qotd_collections[]" value="personal-qotd"' . (in_array('personal-qotd', $activeCols, true) ? ' checked' : '') . '>
							<strong>personal-qotd</strong> (Personal QOTD &mdash; ' . $personalCount . ' quotes)
						</label>
						<label>
							<input type="checkbox" name="qotd_collections[]" value="spiceworks-wisdom"' . (in_array('spiceworks-wisdom', $activeCols, true) ? ' checked' : '') . '>
							<strong>spiceworks-wisdom</strong> (Spiceworks IT Wisdom &mdash; ' . $spiceworksCount . ' quotes)
						</label>
					</td>
				</tr>
				<tr>
					<td><strong>Quote File Path (File Mode)</strong></td>
					<td>
						<select name="qotd_file" class="form-control" style="max-width:420px;">
							<option value="quotes/personal-qotd.json"' . ($settings['file'] === 'quotes/default.json' ? ' selected' : '') . '>quotes/personal-qotd.json (Structured JSON)</option>
							<option value="quotes/spiceworks-wisdom.json"' . ($settings['file'] === 'quotes/spiceworks-wisdom.json' ? ' selected' : '') . '>quotes/spiceworks-wisdom.json (Structured JSON)</option>
							<option value="quotes/personal-qotd.fortune"' . ($settings['file'] === 'quotes/personal-qotd.fortune' ? ' selected' : '') . '>quotes/personal-qotd.fortune (Standard Fortune %)</option>
							<option value="quotes/spiceworks-wisdom.fortune"' . ($settings['file'] === 'quotes/spiceworks-wisdom.fortune' ? ' selected' : '') . '>quotes/spiceworks-wisdom.fortune (Standard Fortune %)</option>
							<option value="quotes/personal-qotd.txt"' . ($settings['file'] === 'quotes/personal-qotd.txt' ? ' selected' : '') . '>quotes/personal-qotd.txt (HTML Fortune %)</option>
							<option value="quote.txt"' . ($settings['file'] === 'quote.txt' ? ' selected' : '') . '>quote.txt (Legacy 1-Line Format)</option>
						</select>
					</td>
				</tr>
				<tr>
					<td><strong>Frontend Widget Elements</strong></td>
					<td>
						<label style="display:block; margin-bottom:6px;">
							<input type="checkbox" name="qotd_show_tags" value="1"' . (!empty($settings['show_tags']) ? ' checked' : '') . '>
							Show Theme-Inheriting Topical Tag Pills (<code>#faith</code>, <code>#wisdom</code>, <code>#sysadmin</code>, etc.)
						</label>
						<label style="display:block; margin-bottom:6px;">
							<input type="checkbox" name="qotd_show_source" value="1"' . (!empty($settings['show_source']) ? ' checked' : '') . '>
							Show Source URL Link when available
						</label>
						<label style="display:block;">
							<input type="checkbox" name="qotd_show_next_btn" value="1"' . (!empty($settings['show_next_btn']) ? ' checked' : '') . '>
							Show Interactive <strong>&ldquo;&#8635; Another Quote&rdquo;</strong> AJAX Button
						</label>
					</td>
				</tr>
				<tr>
					<td><strong>Menu Wrapper Style</strong></td>
					<td>
						<select name="qotd_rendertype" class="form-control" style="max-width:320px;">
							<option value="0"' . ((int)$settings['rendertype'] === 0 ? ' selected' : '') . '>Standard Theme Box (tablerender)</option>
							<option value="1"' . ((int)$settings['rendertype'] === 1 ? ' selected' : '') . '>Plain / Unboxed Markup</option>
						</select>
					</td>
				</tr>
			</table>
			<div style="margin-top:12px;">
				<button type="submit" name="qotd_save_settings" value="1" class="btn btn-primary">Save Settings</button>
			</div>
		</form>
	</div>';
}

if ($activeTab === 'sync') {
	$html .= '<div class="qotd-admin-panel">
		<h4>&#128259; GitHub Repository Sync (<a href="https://github.com/' . htmlspecialchars($settings['github_repo'], ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">github.com/' . htmlspecialchars($settings['github_repo'], ENT_QUOTES, 'UTF-8') . ' &#8599;</a>)</h4>
		<p>Pulls the latest structured JSON collections (<code>structured/default.json</code>, <code>structured/personal-qotd.json</code>, and <code>structured/spiceworks-wisdom.json</code>) from GitHub and merges any new quotes into <code>e107_qotd</code> without duplicating existing entries.</p>
		<form method="post" action="admin_config.php?tab=sync" style="display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end;">
			<div>
				<label><strong>GitHub Repository</strong></label>
				<input type="text" name="qotd_github_repo" class="form-control" style="width:260px;" value="' . htmlspecialchars($settings['github_repo'], ENT_QUOTES, 'UTF-8') . '">
			</div>
			<div>
				<label><strong>Branch</strong></label>
				<input type="text" name="qotd_github_branch" class="form-control" style="width:120px;" value="' . htmlspecialchars($settings['github_branch'], ENT_QUOTES, 'UTF-8') . '">
			</div>
			<div>
				<button type="submit" name="qotd_sync_github" value="1" class="btn btn-primary">&#128259; Sync Latest Quotes from GitHub</button>
			</div>
		</form>
	</div>

	<div class="qotd-admin-panel">
		<h4>&#128230; Local Bundled Collections Import &amp; Dual-Format Export</h4>
		<div style="display:flex; flex-wrap:wrap; gap:24px; align-items:center;">
			<form method="post" action="admin_config.php?tab=sync">
				<p style="margin-bottom:8px;">Import bundled <code>quotes/*.json</code> collections (<code>default</code>, <code>personal-qotd</code>, <code>spiceworks-wisdom</code>) into MySQL:</p>
				<label style="margin-right:12px;">
					<input type="checkbox" name="qotd_replace_existing" value="1"> Reset &amp; replace all database quotes
				</label>
				<button type="submit" name="qotd_import_bundled" value="1" class="btn btn-info">Import Bundled Collections into DB</button>
			</form>
			<form method="post" action="admin_config.php?tab=sync">
				<p style="margin-bottom:8px;">Export all database quotes back to <code>quotes/*.json</code>, <code>quotes/*.fortune</code>, and <code>quotes/*.txt</code>:</p>
				<button type="submit" name="qotd_export_files" value="1" class="btn btn-success">Export Database to JSON &amp; Fortune Files</button>
			</form>
		</div>
	</div>';
}

	$html .= '<p class="text-muted small" style="margin-top:8px;margin-bottom:0;"><em>Quote of the Day v2.6.0 &mdash; Imagined by human, built with AI.</em></p>';
	e107::getRender()->tablerender('Quote of the Day (v2.6.0) — Administration', $mes->render() . $html);
}

try {
	qotd_menu_render_admin();
} catch (\Throwable $e) {
	e107::getRender()->tablerender(
		'Quote of the Day (v2.6.0) — Administration Error',
		'<div class="alert alert-danger"><strong>Error loading Quote of the Day Admin:</strong> ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>'
	);
}

require_once(e_ADMIN . 'footer.php');
