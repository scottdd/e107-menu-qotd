<?php
/**
 * Quote of the Day (qotd_menu) v2.6.0 — Manager & Sync Helper
 *
 * @package     qotd_menu
 * @version     2.6.0
 * @author      cameron / Scott Dowlen <https://github.com/scottdd/e107-qotd-menu>
 * @license     GPL-2.0-or-later
 * @note        Imagined by human, built with AI.
 */
if (!defined('e107_INIT')) { exit; }

class e107_qotd_manager
{
	private static $schemaChecked = false;
	private static $lastSyncStats = null;

	public static function getLastSyncStats()
	{
		return self::$lastSyncStats;
	}

	public static function getSettings(): array
	{
		$pref = e107::getPref();
		$sourceMode = isset($pref['qotd_source_mode']) ? (string)$pref['qotd_source_mode'] : '';
		if ($sourceMode === '') {
			$sourceMode = (!empty($pref['qotd_table']) || !isset($pref['qotd_table'])) ? 'db' : 'file';
		}

		$rotationMode = isset($pref['qotd_rotation_mode']) ? (string)$pref['qotd_rotation_mode'] : 'daily';
		if (!in_array($rotationMode, array('daily', 'random'), true)) {
			$rotationMode = 'daily';
		}

		$collectionsRaw = isset($pref['qotd_collections']) ? (string)$pref['qotd_collections'] : 'default';
		$collections = array_values(array_filter(array_map('trim', explode(',', $collectionsRaw))));
		if (empty($collections)) {
			$collections = array('default');
		}

		return array(
			'header'         => !empty($pref['qotd_header']) ? (string)$pref['qotd_header'] : 'Quote of the Day',
			'source_mode'    => $sourceMode,
			'rotation_mode'  => $rotationMode,
			'collections'    => $collections,
			'file'           => !empty($pref['qotd_file']) ? (string)$pref['qotd_file'] : 'quotes/default.json',
			'show_tags'      => isset($pref['qotd_show_tags']) ? (int)$pref['qotd_show_tags'] : 1,
			'show_source'    => isset($pref['qotd_show_source']) ? (int)$pref['qotd_show_source'] : 1,
			'show_next_btn'  => isset($pref['qotd_show_next_btn']) ? (int)$pref['qotd_show_next_btn'] : 1,
			'rendertype'     => isset($pref['qotd_rendertype']) ? (int)$pref['qotd_rendertype'] : 0,
			'github_repo'    => !empty($pref['qotd_github_repo']) ? (string)$pref['qotd_github_repo'] : 'scottdd/qotd-collections',
			'github_branch'  => !empty($pref['qotd_github_branch']) ? (string)$pref['qotd_github_branch'] : 'main',
		);
	}

	public static function ensureSchemaAndSeed(): void
	{
		self::ensureSchema();
	}

	public static function ensureSchema(): void
	{
		if (self::$schemaChecked) {
			return;
		}
		self::$schemaChecked = true;

		$sql = e107::getDb();
		$prefix = MPREFIX;
		$table = $prefix . 'qotd';

		$createSql = "CREATE TABLE IF NOT EXISTS `{$table}` (
			`qotd_id` int(11) unsigned NOT NULL auto_increment,
			`qotd_quote` text NOT NULL,
			`qotd_attribution` varchar(150) NOT NULL default '',
			`qotd_info` varchar(255) NOT NULL default '',
			`qotd_tags` varchar(255) NOT NULL default '',
			`qotd_source_url` varchar(255) NOT NULL default '',
			`qotd_collection` varchar(64) NOT NULL default 'personal-qotd',
			`qotd_pin_start` date DEFAULT NULL,
			`qotd_pin_end` date DEFAULT NULL,
			`qotd_active` tinyint(1) unsigned NOT NULL default '1',
			PRIMARY KEY (`qotd_id`),
			KEY `qotd_collection` (`qotd_collection`),
			KEY `qotd_active` (`qotd_active`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
		$sql->gen($createSql);

		// Check if upgrading from legacy v2.5 schema (qotd_name, qotd_text)
		$cols = array();
		if ($sql->gen("SHOW COLUMNS FROM `{$table}`")) {
			while ($r = $sql->fetch()) {
				if (!empty($r['Field'])) {
					$cols[$r['Field']] = true;
				}
			}
		}

		$alterParts = array();
		if (!isset($cols['qotd_quote'])) {
			$alterParts[] = "ADD COLUMN `qotd_quote` text NOT NULL";
		}
		if (!isset($cols['qotd_attribution'])) {
			$alterParts[] = "ADD COLUMN `qotd_attribution` varchar(150) NOT NULL default ''";
		}
		if (!isset($cols['qotd_info'])) {
			$alterParts[] = "ADD COLUMN `qotd_info` varchar(255) NOT NULL default ''";
		}
		if (!isset($cols['qotd_tags'])) {
			$alterParts[] = "ADD COLUMN `qotd_tags` varchar(255) NOT NULL default ''";
		}
		if (!isset($cols['qotd_source_url'])) {
			$alterParts[] = "ADD COLUMN `qotd_source_url` varchar(255) NOT NULL default ''";
		}
		if (!isset($cols['qotd_collection'])) {
			$alterParts[] = "ADD COLUMN `qotd_collection` varchar(64) NOT NULL default 'default'";
		}
		if (!isset($cols['qotd_pin_start'])) {
			$alterParts[] = "ADD COLUMN `qotd_pin_start` date DEFAULT NULL";
		}
		if (!isset($cols['qotd_pin_end'])) {
			$alterParts[] = "ADD COLUMN `qotd_pin_end` date DEFAULT NULL";
		}
		if (!isset($cols['qotd_active'])) {
			$alterParts[] = "ADD COLUMN `qotd_active` tinyint(1) unsigned NOT NULL default '1'";
		}

		if (!empty($alterParts)) {
			$sql->gen("ALTER TABLE `{$table}` " . implode(', ', $alterParts));
			if (isset($cols['qotd_text'])) {
				$sql->gen("UPDATE `{$table}` SET `qotd_quote` = `qotd_text`, `qotd_attribution` = IFNULL(`qotd_name`, '') WHERE (`qotd_quote` = '' OR `qotd_quote` IS NULL)");
			}
		}

		// Auto-seed bundled collections if table is completely empty, or auto-sync when bundled collections on disk have new quotes/fixes
		$count = (int)$sql->count('qotd');
		if ($count === 0) {
			self::$lastSyncStats = self::importFromBundled(array('default', 'personal-qotd', 'spiceworks-wisdom'), false);
		} else {
			$hasOldTypo = (int)$sql->count('qotd', '(*)', "qotd_quote LIKE '%My meory is nearly gone%' OR qotd_quote LIKE '%<i>not</i> making one%' OR qotd_attribution = 'Scott Adams ()' OR qotd_attribution = 'Plato Live, Laugh, Love' OR (qotd_quote LIKE 'An ill-chosen word is the fool%' AND qotd_attribution = '') OR (qotd_quote LIKE 'The wise man never argues with the fool%' AND (qotd_attribution = '' OR qotd_attribution = 'Unknown')) OR (qotd_quote LIKE 'He has read everything%' AND qotd_info = '')");
			$needSync = ($hasOldTypo > 0);
			if (!$needSync) {
				$baseDir = e_PLUGIN . 'qotd_menu/quotes/';
				if (is_file($baseDir . 'personal-qotd.json')) {
					$pCount = (int)$sql->count('qotd', '(*)', "qotd_collection = 'personal-qotd'");
					if ($pCount < 233) {
						$needSync = true;
					}
				}
				if (!$needSync && is_file($baseDir . 'default.json')) {
					$dCount = (int)$sql->count('qotd', '(*)', "qotd_collection = 'default'");
					if ($dCount < 176) {
						$needSync = true;
					}
				}
			}
			if ($needSync) {
				self::$lastSyncStats = self::importFromBundled(array('default', 'personal-qotd', 'spiceworks-wisdom'), false);
			}
		}
	}

	public static function importFromBundled(array $collections = array('default', 'personal-qotd', 'spiceworks-wisdom'), bool $replace = false): array
	{
		$sql = e107::getDb();
		$prefix = MPREFIX;
		$table = $prefix . 'qotd';

		if ($replace) {
			$sql->gen("TRUNCATE TABLE `{$table}`");
		}

		$existingByKey = array();
		$existingByCol = array();
		$matchedIds = array();

		$rows = $sql->retrieve('qotd', 'qotd_id, qotd_quote, qotd_attribution, qotd_info, qotd_tags, qotd_source_url, qotd_collection', 'ORDER BY qotd_id ASC', true);
		if (is_array($rows)) {
			foreach ($rows as $r) {
				$id = (int)($r['qotd_id'] ?? 0);
				$c = (string)($r['qotd_collection'] ?? 'default');
				$norm = self::normKey((string)($r['qotd_quote'] ?? ''));
				if ($id > 0 && $norm !== '') {
					$r['_norm'] = $norm;
					$existingByKey[$c . ':' . $norm] = $r;
					$existingByCol[$c][$id] = $r;
				}
			}
		}

		$added = 0;
		$updated = 0;
		$skipped = 0;
		$baseDir = e_PLUGIN . 'qotd_menu/quotes/';

		foreach ($collections as $col) {
			$col = preg_replace('/[^a-zA-Z0-9_-]/', '', $col);
			$jsonFile = $baseDir . $col . '.json';
			if (!is_file($jsonFile)) {
				continue;
			}
			$items = json_decode((string)file_get_contents($jsonFile), true);
			if (!is_array($items)) {
				continue;
			}

			// Build set of incoming normalized keys so we know which existing DB rows are exact matches
			$incomingNorms = array();
			foreach ($items as $item) {
				$qTmp = trim((string)($item['quote'] ?? ''));
				if ($qTmp !== '') {
					$incomingNorms[self::normKey($qTmp)] = true;
				}
			}

			foreach ($items as $item) {
				$q = trim((string)($item['quote'] ?? ''));
				if ($q === '') {
					continue;
				}
				$attr = substr(trim((string)($item['attribution'] ?? '')), 0, 150);
				$info = substr(trim((string)($item['attribution_info'] ?? '')), 0, 255);
				$tagsArr = isset($item['tags']) && is_array($item['tags']) ? $item['tags'] : array();
				$tagsStr = substr(implode(', ', array_filter(array_map('trim', $tagsArr))), 0, 255);
				$url = substr(trim((string)($item['source_url'] ?? '')), 0, 255);

				$norm = self::normKey($q);
				$k = $col . ':' . $norm;

				if (isset($existingByKey[$k])) {
					$ex = $existingByKey[$k];
					$exId = (int)$ex['qotd_id'];
					$matchedIds[$exId] = true;
					if (
						(string)$ex['qotd_quote'] !== $q ||
						(string)$ex['qotd_attribution'] !== $attr ||
						(string)$ex['qotd_info'] !== $info ||
						(string)$ex['qotd_tags'] !== $tagsStr ||
						(string)$ex['qotd_source_url'] !== $url
					) {
						$sql->update('qotd', array(
							'data' => array(
								'qotd_quote'       => $q,
								'qotd_attribution' => $attr,
								'qotd_info'        => $info,
								'qotd_tags'        => $tagsStr,
								'qotd_source_url'  => $url,
							),
							'WHERE' => 'qotd_id = ' . $exId,
						));
						$updated++;
					} else {
						$skipped++;
					}
					continue;
				}

				// Check if this incoming quote is a typo/spelling fix of an unmatched existing row in the same collection
				$bestMatchId = 0;
				$bestPct = 0.0;
				if (!empty($existingByCol[$col])) {
					foreach ($existingByCol[$col] as $candId => $cand) {
						if (isset($matchedIds[$candId]) || isset($incomingNorms[$cand['_norm']])) {
							continue;
						}
						$pct = 0.0;
						similar_text($cand['_norm'], $norm, $pct);
						if ($pct >= 85.0 && $pct > $bestPct) {
							$bestPct = $pct;
							$bestMatchId = (int)$candId;
						}
					}
				}

				if ($bestMatchId > 0) {
					$matchedIds[$bestMatchId] = true;
					$sql->update('qotd', array(
						'data' => array(
							'qotd_quote'       => $q,
							'qotd_attribution' => $attr,
							'qotd_info'        => $info,
							'qotd_tags'        => $tagsStr,
							'qotd_source_url'  => $url,
						),
						'WHERE' => 'qotd_id = ' . $bestMatchId,
					));
					$existingByKey[$k] = array('qotd_id' => $bestMatchId);
					$updated++;
					continue;
				}

				$insert = array(
					'qotd_quote'       => $q,
					'qotd_attribution' => $attr,
					'qotd_info'        => $info,
					'qotd_tags'        => $tagsStr,
					'qotd_source_url'  => $url,
					'qotd_collection'  => $col,
					'qotd_active'      => 1,
				);
				if ($sql->insert('qotd', $insert)) {
					$existingByKey[$k] = array('qotd_id' => 0);
					$added++;
				}
			}
		}

		return array('added' => $added, 'updated' => $updated, 'skipped' => $skipped);
	}

	public static function syncFromGitHub(string $repo = 'scottdd/qotd-collections', string $branch = 'main', array $collections = array('default', 'personal-qotd', 'spiceworks-wisdom')): array
	{
		self::ensureSchema();
		$repo = preg_replace('#[^a-zA-Z0-9_./-]#', '', $repo);
		$branch = preg_replace('#[^a-zA-Z0-9_.-]#', '', $branch);
		$baseDir = e_PLUGIN . 'qotd_menu/quotes/';
		if (!is_dir($baseDir)) {
			@mkdir($baseDir, 0775, true);
		}

		$fetchedCollections = array();
		$errors = array();

		foreach ($collections as $col) {
			$col = preg_replace('/[^a-zA-Z0-9_-]/', '', $col);
			$url = "https://raw.githubusercontent.com/{$repo}/{$branch}/structured/{$col}.json";
			$ctx = stream_context_create(array(
				'http' => array(
					'timeout' => 10,
					'user_agent' => 'e107-qotd-menu/2.6.0 (+https://dowlen.net)',
				),
			));
			$raw = @file_get_contents($url, false, $ctx);
			if ($raw === false || trim($raw) === '') {
				$errors[] = "Could not fetch {$url}";
				continue;
			}
			$decoded = json_decode($raw, true);
			if (!is_array($decoded)) {
				$errors[] = "Invalid JSON from {$url}";
				continue;
			}
			@file_put_contents($baseDir . $col . '.json', $raw);
			$fetchedCollections[] = $col;
		}

		$importStats = array('added' => 0, 'updated' => 0, 'skipped' => 0);
		if (!empty($fetchedCollections)) {
			$importStats = self::importFromBundled($fetchedCollections, false);
			self::exportDatabaseToFiles();
		}

		return array(
			'fetched' => $fetchedCollections,
			'added'   => $importStats['added'],
			'updated' => $importStats['updated'] ?? 0,
			'skipped' => $importStats['skipped'],
			'errors'  => $errors,
		);
	}

	public static function exportDatabaseToFiles(): array
	{
		self::ensureSchema();
		$sql = e107::getDb();
		$rows = $sql->retrieve('qotd', '*', 'ORDER BY qotd_collection ASC, qotd_id ASC', true);
		if (!is_array($rows)) {
			$rows = array();
		}

		$byCol = array();
		foreach ($rows as $r) {
			$col = !empty($r['qotd_collection']) ? (string)$r['qotd_collection'] : 'default';
			$tags = array_values(array_filter(array_map('trim', explode(',', (string)($r['qotd_tags'] ?? '')))));
			$byCol[$col][] = array(
				'id'               => $col . '-' . str_pad((string)(count($byCol[$col] ?? array()) + 1), 3, '0', STR_PAD_LEFT),
				'quote'            => (string)$r['qotd_quote'],
				'attribution'      => (string)$r['qotd_attribution'],
				'attribution_info' => (string)$r['qotd_info'],
				'tags'             => $tags,
				'source_url'       => (string)$r['qotd_source_url'],
			);
		}

		$baseDir = e_PLUGIN . 'qotd_menu/quotes/';
		if (!is_dir($baseDir)) {
			@mkdir($baseDir, 0775, true);
		}

		$written = array();
		foreach ($byCol as $col => $items) {
			$jsonPath = $baseDir . $col . '.json';
			$fortunePath = $baseDir . $col . '.fortune';
			$txtPath = $baseDir . $col . '.txt';

			@file_put_contents($jsonPath, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");

			$fortuneBuf = ($col === 'spiceworks-wisdom') ? "// Spiceworks wisdom - Fortune file\n%\n" : "";
			$txtBuf = "";
			foreach ($items as $it) {
				$qPlain = strip_tags($it['quote']);
				if ($col === 'spiceworks-wisdom') {
					$line1 = $qPlain . ($it['attribution'] !== '' ? ' - ' . $it['attribution'] : '');
					$fortuneBuf .= $line1 . "\n";
					if ($it['source_url'] !== '') {
						$fortuneBuf .= $it['source_url'] . "\n";
					}
					$fortuneBuf .= "%\n";
				} else {
					$fortuneBuf .= $qPlain . "\n";
					if ($it['attribution'] !== '') {
						$attrLine = "    — " . $it['attribution'];
						if ($it['attribution_info'] !== '') {
							$attrLine .= ", " . $it['attribution_info'];
						}
						$fortuneBuf .= $attrLine . "\n";
					}
					$fortuneBuf .= "%\n";
				}

				$qHtml = str_replace("\n", "<br/>\n", $it['quote']);
				if ($it['attribution'] !== '') {
					$txtBuf .= $qHtml . "<br/>\n—&nbsp;<b>" . htmlspecialchars($it['attribution'], ENT_QUOTES, 'UTF-8') . "</b>";
					if ($it['attribution_info'] !== '') {
						$txtBuf .= ", " . htmlspecialchars($it['attribution_info'], ENT_QUOTES, 'UTF-8');
					}
					$txtBuf .= "\n%\n";
				} else {
					$txtBuf .= $qHtml . "\n%\n";
				}
			}
			@file_put_contents($fortunePath, $fortuneBuf);
			@file_put_contents($txtPath, $txtBuf);
			$written[$col] = count($items);
		}

		return $written;
	}

	public static function parseQuoteFile(string $relPath): array
	{
		$relPath = ltrim(str_replace('..', '', $relPath), '/');
		$fullPath = e_PLUGIN . 'qotd_menu/' . $relPath;
		if (!is_file($fullPath)) {
			return array();
		}

		$raw = (string)file_get_contents($fullPath);
		if (function_exists('mb_check_encoding') && function_exists('mb_convert_encoding') && !mb_check_encoding($raw, 'UTF-8')) {
			$raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
		}

		$ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
		if ($ext === 'json') {
			$decoded = json_decode($raw, true);
			if (!is_array($decoded)) {
				return array();
			}
			$out = array();
			$i = 1;
			foreach ($decoded as $item) {
				$tagsArr = isset($item['tags']) && is_array($item['tags']) ? $item['tags'] : array();
				$out[] = array(
					'qotd_id'          => $i++,
					'qotd_quote'       => (string)($item['quote'] ?? ''),
					'qotd_attribution' => (string)($item['attribution'] ?? ''),
					'qotd_info'        => (string)($item['attribution_info'] ?? ''),
					'qotd_tags'        => implode(', ', $tagsArr),
					'qotd_source_url'  => (string)($item['source_url'] ?? ''),
					'qotd_collection'  => basename($fullPath, '.json'),
					'is_pinned'        => false,
				);
			}
			return $out;
		}

		// Check if fortune format (% delimited)
		if (preg_match('/^%\s*$/m', $raw)) {
			$blocks = preg_split('/^%\s*$/m', $raw);
			$out = array();
			$i = 1;
			foreach ($blocks as $b) {
				$b = trim($b);
				if ($b === '' || strpos($b, '//') === 0) {
					continue;
				}
				$lines = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $b))));
				$attr = '';
				$info = '';
				$url = '';
				$last = end($lines);
				if ($last !== false && preg_match('/^(?:—|--)\s*(?:&nbsp;|\s)*(.*)$/u', $last, $m)) {
					array_pop($lines);
					$attrRaw = strip_tags($m[1]);
					if (strpos($attrRaw, ',') !== false) {
						list($attr, $info) = array_map('trim', explode(',', $attrRaw, 2));
					} else {
						$attr = trim($attrRaw);
					}
					$quoteText = trim(str_replace(array('<br/>', '<br />', '<br>'), "\n", implode("\n", $lines)));
				} elseif ($last !== false && strpos($last, 'http') === 0 && count($lines) >= 2) {
					$url = array_pop($lines);
					$body = implode(' ', $lines);
					$pos = strrpos($body, ' - ');
					if ($pos !== false) {
						$quoteText = trim(substr($body, 0, $pos));
						$attr = trim(substr($body, $pos + 3));
						$info = 'Spiceworks Community';
					} else {
						$quoteText = trim($body);
					}
				} else {
					$quoteText = trim(str_replace(array('<br/>', '<br />', '<br>'), "\n", implode("\n", $lines)));
				}
				$out[] = array(
					'qotd_id'          => $i++,
					'qotd_quote'       => $quoteText,
					'qotd_attribution' => $attr,
					'qotd_info'        => $info,
					'qotd_tags'        => '',
					'qotd_source_url'  => $url,
					'qotd_collection'  => basename($fullPath),
					'is_pinned'        => false,
				);
			}
			return $out;
		}

		// Legacy 1-line quote.txt format
		$lines = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $raw))));
		$out = array();
		$i = 1;
		foreach ($lines as $line) {
			$attr = '';
			$info = '';
			$quoteText = $line;
			if (preg_match('#^(.*?)\s+(?:-|—)\s*<b>(.*?)</b>\s*$#u', $line, $m)) {
				$quoteText = trim($m[1]);
				$attrRaw = trim(strip_tags($m[2]));
				if (strpos($attrRaw, ',') !== false) {
					list($attr, $info) = array_map('trim', explode(',', $attrRaw, 2));
				} else {
					$attr = $attrRaw;
				}
			}
			$quoteText = str_replace(array('<br />', '<br/>', '<br>'), "\n", $quoteText);
			$out[] = array(
				'qotd_id'          => $i++,
				'qotd_quote'       => $quoteText,
				'qotd_attribution' => $attr,
				'qotd_info'        => $info,
				'qotd_tags'        => 'wisdom',
				'qotd_source_url'  => '',
				'qotd_collection'  => 'quote.txt',
				'is_pinned'        => false,
			);
		}
		return $out;
	}

	public static function getQuote(bool $forceRandom = false, int $excludeId = 0): array
	{
		self::ensureSchema();
		$settings = self::getSettings();
		$sql = e107::getDb();
		$today = date('Y-m-d');

		// 1. Check active pinned quote date range first (unless user clicked "Another Quote")
		if (!$forceRandom) {
			$pinQuery = "qotd_active = 1 AND qotd_pin_start IS NOT NULL AND qotd_pin_end IS NOT NULL AND qotd_pin_start <= '{$today}' AND qotd_pin_end >= '{$today}' ORDER BY qotd_pin_start DESC, qotd_id DESC LIMIT 1";
			$pinned = $sql->retrieve('qotd', '*', $pinQuery);
			if (is_array($pinned) && !empty($pinned['qotd_quote'])) {
				$pinned['is_pinned'] = true;
				return $pinned;
			}
		}

		// 2. Build pool from either DB or File
		$pool = array();
		if ($settings['source_mode'] === 'file') {
			$pool = self::parseQuoteFile($settings['file']);
		} else {
			$cols = $settings['collections'];
			$escapedCols = array();
			foreach ($cols as $c) {
				$escapedCols[] = "'" . preg_replace('/[^a-zA-Z0-9_-]/', '', $c) . "'";
			}
			$where = "qotd_active = 1";
			if (!empty($escapedCols) && !in_array("'all'", $escapedCols, true)) {
				$where .= " AND qotd_collection IN (" . implode(',', $escapedCols) . ")";
			}
			$rows = $sql->retrieve('qotd', '*', $where . " ORDER BY qotd_id ASC", true);
			if (is_array($rows) && !empty($rows)) {
				$pool = $rows;
			} else {
				// Fallback to any active quote in DB
				$rows = $sql->retrieve('qotd', '*', "qotd_active = 1 ORDER BY qotd_id ASC", true);
				if (is_array($rows)) {
					$pool = $rows;
				}
			}
		}

		if (empty($pool)) {
			return array(
				'qotd_id'          => 0,
				'qotd_quote'       => 'No quotes available.',
				'qotd_attribution' => '',
				'qotd_info'        => '',
				'qotd_tags'        => '',
				'qotd_source_url'  => '',
				'qotd_collection'  => 'personal-qotd',
				'is_pinned'        => false,
			);
		}

		// Filter out $excludeId when clicking "Another Quote" if more than 1 quote is in pool
		if ($forceRandom && $excludeId > 0 && count($pool) > 1) {
			$filtered = array();
			foreach ($pool as $item) {
				if ((int)($item['qotd_id'] ?? 0) !== $excludeId) {
					$filtered[] = $item;
				}
			}
			if (!empty($filtered)) {
				$pool = $filtered;
			}
		}

		$count = count($pool);
		if ($forceRandom || $settings['rotation_mode'] === 'random') {
			$idx = random_int(0, $count - 1);
		} else {
			// Deterministic daily Quote of the Day based on YYYY-MM-DD
			$seed = abs((int)crc32('dowlen-qotd-' . $today));
			$idx = $seed % $count;
		}

		$chosen = $pool[$idx];
		$chosen['is_pinned'] = false;
		return $chosen;
	}

	public static function formatQuoteBodyHtml(string $quote): string
	{
		// Escape HTML except intentional <i>...</i> emphasis
		$escaped = htmlspecialchars($quote, ENT_QUOTES, 'UTF-8');
		$escaped = str_replace(array('&lt;i&gt;', '&lt;/i&gt;'), array('<em>', '</em>'), $escaped);
		return nl2br($escaped, false);
	}

	public static function renderWidget(?array $quoteRow = null, bool $innerOnly = false): string
	{
		$settings = self::getSettings();
		if ($quoteRow === null) {
			$quoteRow = self::getQuote(false, 0);
		}

		$id = (int)($quoteRow['qotd_id'] ?? 0);
		$quoteHtml = self::formatQuoteBodyHtml((string)($quoteRow['qotd_quote'] ?? ''));
		$attr = trim((string)($quoteRow['qotd_attribution'] ?? ''));
		$info = trim((string)($quoteRow['qotd_info'] ?? ''));
		$url  = trim((string)($quoteRow['qotd_source_url'] ?? ''));
		$tagsRaw = trim((string)($quoteRow['qotd_tags'] ?? ''));
		$isPinned = !empty($quoteRow['is_pinned']);

		$tags = array_values(array_filter(array_map('trim', explode(',', $tagsRaw))));
		$ajaxUrl = e_PLUGIN_ABS . 'qotd_menu/qotd_ajax.php';

		$inner = '<figure class="qotd-widget" data-qotd-id="' . $id . '">';
		if ($isPinned) {
			$inner .= '<div class="qotd-pinned-row"><span class="qotd-pinned-badge" title="Pinned quote for today">&#128204; Featured Quote</span></div>';
		}
		$inner .= '<blockquote class="qotd-quote-body">';
		$inner .= '<span class="qotd-quote-text">' . $quoteHtml . '</span>';
		$inner .= '</blockquote>';

		if ($attr !== '' || $info !== '' || ($settings['show_source'] && $url !== '')) {
			$inner .= '<figcaption class="qotd-attribution">';
			$inner .= '<span class="qotd-dash" aria-hidden="true">&mdash;&nbsp;</span>';
			if ($attr !== '') {
				$inner .= '<strong class="qotd-author">' . htmlspecialchars($attr, ENT_QUOTES, 'UTF-8') . '</strong>';
			}
			if ($info !== '') {
				if ($attr !== '') {
					$inner .= '<span class="qotd-sep">, </span>';
				}
				$inner .= '<cite class="qotd-info">' . htmlspecialchars($info, ENT_QUOTES, 'UTF-8') . '</cite>';
			}
			if ($settings['show_source'] && $url !== '' && preg_match('#^https?://#i', $url)) {
				$inner .= ' <a class="qotd-source-link" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer" title="View quote source">&#8599; Source</a>';
			}
			$inner .= '</figcaption>';
		}

		$hasTags = ($settings['show_tags'] && !empty($tags));
		$hasNext = !empty($settings['show_next_btn']);

		if ($hasTags || $hasNext) {
			$inner .= '<div class="qotd-footer">';
			if ($hasTags) {
				$inner .= '<div class="qotd-tags" aria-label="Quote tags">';
				foreach ($tags as $t) {
					$inner .= '<span class="qotd-tag">#' . htmlspecialchars($t, ENT_QUOTES, 'UTF-8') . '</span>';
				}
				$inner .= '</div>';
			} else {
				$inner .= '<div class="qotd-tags"></div>';
			}
			if ($hasNext) {
				$inner .= '<button type="button" class="qotd-next-btn" data-qotd-action="next" title="Show another quote">';
				$inner .= '<span class="qotd-next-icon" aria-hidden="true">&#8635;</span> ';
				$inner .= '<span class="qotd-next-label">Another Quote</span>';
				$inner .= '</button>';
			}
			$inner .= '</div>';
		}

		$inner .= '</figure>';

		if ($innerOnly) {
			return $inner;
		}

		return '<div class="qotd-widget-wrap" data-qotd-ajax-url="' . htmlspecialchars($ajaxUrl, ENT_QUOTES, 'UTF-8') . '" data-qotd-current-id="' . $id . '">' . $inner . '</div>';
	}

	private static function normKey(string $s): string
	{
		$s = strip_tags($s);
		$s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$s = strtolower((string)preg_replace('/[^a-zA-Z0-9]+/', '', $s));
		return substr($s, 0, 80);
	}
}

if (!class_exists('qotd_manager', false)) {
	class_alias('e107_qotd_manager', 'qotd_manager');
}
