<?php
if (!defined('e107_INIT')) { exit; }

class qotd_menu_setup
{
	public function install_pre($var)
	{
		$legacyPhp = __DIR__ . '/plugin.php';
		if (is_file($legacyPhp) && is_file(__DIR__ . '/plugin.xml'))
		{
			@unlink($legacyPhp);
		}
	}

	public function install_post($var)
	{
		require_once(__DIR__ . '/includes/qotd_manager.php');
		e107_qotd_manager::ensureSchema();
	}

	public function upgrade_post($var)
	{
		$legacyPhp = __DIR__ . '/plugin.php';
		if (is_file($legacyPhp) && is_file(__DIR__ . '/plugin.xml'))
		{
			@unlink($legacyPhp);
		}
		require_once(__DIR__ . '/includes/qotd_manager.php');
		e107_qotd_manager::ensureSchema();
	}
}
