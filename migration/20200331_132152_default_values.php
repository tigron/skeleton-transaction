<?php
/**
 * Database migration class
 *
 */
namespace Skeleton\Transaction;


use \Skeleton\Database\Database;

class Migration_20200331_132152_Default_values extends \Skeleton\Database\Migration {

	/**
	 * Migrate up
	 *
	 * @access public
	 */
	public function up() {
		$db = Database::get();
		$db->query("
			ALTER TABLE `transaction`
			CHANGE `scheduled_at` `scheduled_at` datetime NULL AFTER `created`,
			CHANGE `data` `data` text NULL AFTER `scheduled_at`,
			CHANGE `retry_attempt` `retry_attempt` int(11) NOT NULL DEFAULT '0' AFTER `data`,
			CHANGE `recurring` `recurring` tinyint(4) NOT NULL DEFAULT '0' AFTER `retry_attempt`,
			CHANGE `completed` `completed` tinyint(4) NOT NULL DEFAULT '0' AFTER `recurring`,
			CHANGE `failed` `failed` tinyint(4) NOT NULL DEFAULT '0' AFTER `completed`,
			CHANGE `locked` `locked` tinyint(4) NOT NULL DEFAULT '0' AFTER `failed`,
			CHANGE `frozen` `frozen` tinyint(4) NOT NULL DEFAULT '0' AFTER `locked`,
			CHANGE `parallel` `parallel` tinyint(4) NOT NULL DEFAULT '1' AFTER `frozen`
		");

	}

	/**
	 * Migrate down
	 *
	 * @access public
	 */
	public function down() {

	}
}
