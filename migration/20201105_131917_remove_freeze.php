<?php
/**
 * Database migration class
 *
 * @author David Vandemaele <david@tigron.be>
 */

namespace Skeleton\Transaction;

use \Skeleton\Database\Database;

class Migration_20201105_131917_Remove_freeze extends \Skeleton\Database\Migration {

	/**
	 * Migrate up
	 *
	 * @access public
	 */
	public function up() {
		$db = Database::get();
		$db->query("UPDATE transaction SET scheduled_at = NULL WHERE frozen = 1 AND completed = 0;", []);
		$db->query("ALTER TABLE `transaction` DROP `frozen`, ADD `updated` datetime NULL;", []);
	}

	/**
	 * Migrate down
	 *
	 * @access public
	 */
	public function down() {

	}
}
