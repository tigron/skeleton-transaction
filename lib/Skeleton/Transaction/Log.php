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

	/**
	 * Class configuration
	 *
	 * @access private
	 * @var array $class_configuration
	 */
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
		$db = Database::get();
		if (is_null($limit)) {
			$ids = $db->get_column('SELECT id FROM transaction_log WHERE transaction_id = ?', [ $transaction->id ]);
		} else {
			$ids = $db->get_column('SELECT id FROM transaction_log WHERE transaction_id = ? ORDER BY created DESC LIMIT ' . $limit, [ $transaction->id ]);
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
		$db = Database::get();
		$id = $db->get_one('SELECT id FROM transaction_log WHERE transaction_id=? ORDER BY created DESC LIMIT 1;', [ $transaction->id ]);

		if ($id === null) {
			throw new \Exception('No transaction_log yet');
		}

		return self::get_by_id($id);
	}

	/**
	 * Get last successful
	 *
	 * @access public
	 */
	public static function get_last_successful() {
		$db = Database::get();
		$id = $db->get_one('SELECT id FROM transaction_log WHERE failed=0 ORDER BY created DESC LIMIT 1;', []);

		if ($id === null) {
			throw new \Exception('No transaction_log yet');
		}

		return self::get_by_id($id);
	}

	/**
	 * Create log.
	 */
	public static function create(
		\Transaction $transaction, bool $failed, string $output = '', ?\Throwable $t = null, ?string $date = null
		): self {

		$log = new self();
		$log->transaction_id = $transaction->id;
		$log->failed = $failed;
		$log->output = $output;

		if (isset($t) === true) {
			$log->exception = print_r($t, true);
		}

		if ($date !== null) {
			$log->created = (new \DateTime($date))->format('Y-m-d H:i:s');
		}

		$log->save();

		return $log;
	}
}
