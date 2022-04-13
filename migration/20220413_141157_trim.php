<?php
/**
 * Database migration class
 *
 * @author David Vandemaele <david@tigron.be>
 *
 */

namespace Skeleton\Transaction;

use \Skeleton\Database\Database;

class Migration_20220413_141157_Trim extends \Skeleton\Database\Migration {

	/**
	 * Migrate up.
	 */
	public function up(): void {
		$db = Database::get();
		$db->query('UPDATE transaction_log SET exception = SUBSTRING(exception, 1, 16777215);');
		$db->query('UPDATE transaction_log SET output = SUBSTRING(output, 1, 16777215);');
		$db->query('ALTER TABLE `transaction_log` MODIFY `output` mediumtext, MODIFY `exception` mediumtext;');
	}

}
