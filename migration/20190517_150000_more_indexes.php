<?php
/**
 * Database migration class
 *
 * @author Gerry Demaret <gerry@tigron.be>
 */
namespace Skeleton\Transaction;

use \Skeleton\Database\Database;

class Migration_20190517_150000_More_Indexes extends \Skeleton\Database\Migration {

	/**
	 * Migrate up
	 *
	 * @access public
	 */
	public function up() {
		$db = Database::get();

		$db->query("
			ALTER TABLE `transaction`
			ADD INDEX `completed_failed_frozen_locked` (`completed`, `failed`, `frozen`, `locked`);"
		);

	}

	/**
	 * Migrate down
	 *
	 * @access public
	 */
	public function down() {

	}
}
