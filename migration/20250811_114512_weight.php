<?php
/**
 * Database migration class
 *
 * @author David Vandemaele <david@tigron.be>
 *
 */

namespace Skeleton\Transaction;

use \Skeleton\Database\Database;

class Migration_20250811_114512_Weight extends \Skeleton\Database\Migration {

	/**
	 * Migrate up.
	 */
	public function up(): void {
		$db = Database::get();
		$db->query("ALTER TABLE `transaction` ADD `weight` tinyint NOT NULL DEFAULT '10' AFTER `parallel`");
	}
}
