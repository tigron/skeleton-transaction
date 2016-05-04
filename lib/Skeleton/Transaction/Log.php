<?php
/**
 * Transaction_Log class
 *
 * @author Christophe Gosiau <christophe@tigron.be>
 * @author Gerry Demaret <gerry@tigron.be>
 * @author David Vandemaele <david@tigron.be>
 */

namespace Skeleton\Transaction;

use \Skeleton\Database\Database;

class Log {
	use \Skeleton\Object\Model;
	use \Skeleton\Object\Get;
	use \Skeleton\Object\Save;
	use \Skeleton\Object\Delete;

	private static $class_configuration = [
		'database_table' => 'transaction_log'
	];

	/**
	 * Get by transaction
	 *
	 * @access public
	 * @param Transaction $transaction
	 * @return array $transaction_logs
	 */
	public static function get_by_transaction(Transaction $transaction, $limit = null) {
		$db = Database::Get();
		if (is_null($limit)) {
			$ids = $db->get_column('SELECT id FROM transaction_log WHERE transaction_id=?', [ $transaction->id ]);
		} else {
			$ids = $db->get_column('SELECT id FROM transaction_log WHERE transaction_id=? ORDER BY created DESC LIMIT ' . $limit, [ $transaction->id ]);
			$ids = array_reverse($ids);
		}

		$transaction_logs = [];
		foreach ($ids as $id) {
			$transaction_logs[] = self::get_by_id($id);
		}

		return $transaction_logs;
	}

	/**
	 * Get last by transaction
	 *
	 * @access public
	 * @param Transaction $transaction
	 * @return array $transaction_logs
	 */
	public static function get_last_by_transaction(Transaction $transaction) {
		$db = Database::Get();
		$id = $db->get_one('SELECT id FROM transaction_log WHERE transaction_id=? ORDER BY created DESC LIMIT 1', [ $transaction->id ]);
		if ($id === null) {
			throw new \Exception('No transaction_log yet');
		}

		return self::get_by_id($id);;
	}
}
