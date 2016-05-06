<?php
/**
 * Database migration class
 *
 * @author Gerry Demaret <gerry@tigron.be>
 * @author Christophe Gosiau <christophe@tigron.be>
 * @author David Vandemaele <david@tigron.be>
 */
namespace Skeleton\Transaction;

use \Skeleton\Database\Database;

class Migration_20160506_151821_Recurring extends \Skeleton\Database\Migration {

	/**
	 * Migrate up
	 *
	 * @access public
	 */
	public function up() {
		$db = Database::get();
		$db->query("ALTER TABLE `transaction` DROP `executed_at`;");
		$db->query("ALTER TABLE `transaction` ADD `recurring` tinyint(4) NOT NULL AFTER `data`;");
	}

	/**
	 * Migrate down
	 *
	 * @access public
	 */
	public function down() {

	}
}
