<?php
/**
 * Database migration class
 *
 * @author Gerry Demaret <gerry@tigron.be>
 */
namespace Skeleton\Transaction;

use \Skeleton\Database\Database;

class Migration_20190301_141220_Indexes extends \Skeleton\Database\Migration {

	/**
	 * Migrate up
	 *
	 * @access public
	 */
	public function up() {
		$db = Database::get();

		$database = $db->get_one('SELECT DATABASE()');

		$indexes = ['completed', 'frozen', 'failed'];

		foreach ($indexes as $index) {
			if ($db->get_one('SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE 1 AND TABLE_SCHEMA = "' . $database . '" AND TABLE_NAME="transaction" AND INDEX_NAME="' . $index . '"') === null) {
				$db->query('ALTER TABLE `transaction` ADD INDEX `' . $index . '` (`' . $index . '`)');
			}
		}
	}

	/**
	 * Migrate down
	 *
	 * @access public
	 */
	public function down() {

	}
}
