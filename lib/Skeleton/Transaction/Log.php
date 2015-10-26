<?php
/**
 * Transaction_Log class
 *
 * @package %%Package%%
 * @author Christophe Gosiau <christophe@tigron.be>
 * @author Gerry Demaret <gerry@tigron.be>
 * @version $Id$
 */

namespace Skeleton\Transaction;

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
	public static function get_by_transaction(Transaction $transaction) {
		$db = Database::Get();
		$ids = $db->getCol('SELECT id FROM transaction_log WHERE transaction_id=?', array($transaction->id));
		$transaction_logs = array();
		foreach ($ids as $id) {
			$transaction_logs[] = Transaction_Log::get_by_id($id);
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
		$id = $db->getOne('SELECT id FROM transaction_log WHERE transaction_id=? ORDER BY created DESC LIMIT 1', array($transaction->id));
		if ($id === null) {
			throw new Exception('No transaction_log yet');
		}
		return Transaction_Log::get_by_id($id);;
	}
}
