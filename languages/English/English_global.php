<?php
if (!defined('e107_INIT')) { exit; }

define('LAN_PLUGIN_QOTD_NAME', 'Quote of the Day');
define('LAN_PLUGIN_QOTD_SUMMARY', 'Quote of the Day widget with structured attributions, topical tags, pinned date ranges, daily or random rotation, and GitHub collection sync.');
define('LAN_PLUGIN_QOTD_DESC', 'Displays a daily or random quote with structured attribution, source context, theme-inheriting tag pills, pinned quote date ranges, an interactive AJAX "Another Quote" button, and dual-format JSON/fortune synchronization with scottdd/qotd-collections. Imagined by human, built with AI.');

if (defined('e_ADMIN_AREA') && e_ADMIN_AREA && class_exists('e107'))
{
	(function () {
		$pluginDir = dirname(dirname(__DIR__));
		$folder = basename($pluginDir);
		$legacyPhp = $pluginDir . '/plugin.php';
		if (is_file($legacyPhp) && is_file($pluginDir . '/plugin.xml'))
		{
			@unlink($legacyPhp);
		}
		try
		{
			$sql = e107::getDb();
			if ($sql && $sql->select('plugin', 'plugin_id, plugin_version, plugin_installflag, plugin_category', "plugin_path = '" . $sql->escape($folder) . "' LIMIT 1"))
			{
				$row = $sql->fetch();
				if ($row && ($row['plugin_category'] === 'menu' || ((int)$row['plugin_installflag'] === 0 && $row['plugin_version'] !== '2.6.1')))
				{
					$sql->update('plugin', "plugin_category = 'content', plugin_version = '2.6.1' WHERE plugin_path = '" . $sql->escape($folder) . "'");
				}
			}
		}
		catch (\Throwable $e) {}
	})();
}
