<?php
/**
 * Database migration class
 *
 */
namespace Skeleton\Transaction;


use \Skeleton\Database\Database;

class Migration_20200401_092921_Transaction_log_default_values extends \Skeleton\Database\Migration {

	/**
	 * Migrate up
	 *
	 * @access public
	 */
	public function up() {
		$db = Database::get();
		$db->query("
			ALTER TABLE `transaction_log`
			CHANGE `exception` `exception` longtext NULL AFTER `failed`
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
