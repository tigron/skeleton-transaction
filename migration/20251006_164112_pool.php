<?php
/**
 * Database migration class
 *
 * @author David Vandemaele <david@tigron.be>
 *
 */

namespace Skeleton\Transaction;

use \Skeleton\Database\Database;

class Migration_20251006_164112_Pool extends \Skeleton\Database\Migration {

	/**
	 * Migrate up.
	 */
	public function up(): void {
		$db = Database::get();
		$db->query("
			ALTER TABLE `transaction`
			ADD `pool` varchar(255) NULL AFTER `parallel`
		");

		$db->query("
			UPDATE `transaction`
			SET pool = 'serial'
			WHERE parallel = 0
		");

		$db->query("
			ALTER TABLE `transaction`
			DROP `parallel`
		");

		$db->query("
			ALTER TABLE `transaction`
			ADD FULLTEXT `pool` (`pool`)
		");
	}
}
