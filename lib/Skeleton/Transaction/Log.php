<?php

declare(strict_types=1);

/**
 * Transaction_Log class
 *
 * @author Christophe Gosiau <christophe@tigron.be>
 * @author Gerry Demaret <gerry@tigron.be>
 * @author David Vandemaele <david@tigron.be>
 */

namespace Skeleton\Transaction;

use Skeleton\Database\Database;

class Log {
	use \Skeleton\Object\Model;
	use \Skeleton\Object\Get;
	use \Skeleton\Object\Save;
	use \Skeleton\Object\Delete;

	/**
	 * Class configuration
	 *
	 * @access private
	 */
	private static array $class_configuration = [
		'database_table' => 'transaction_log',
	];

	/**
	 * Get by transaction
	 *
	 * @access public
	 * @return array $logs
	 */
	public static function get_by_transaction(Transaction $transaction, ?int $limit = null): array {
		$db = Database::get();
		if (is_null($limit)) {
			$ids = $db->get_column('SELECT id FROM transaction_log WHERE transaction_id = ?', [ $transaction->id ]);
		} else {
			$ids = $db->get_column('SELECT id FROM transaction_log WHERE transaction_id = ? ORDER BY created DESC LIMIT ' . $limit, [ $transaction->id ]);
			$ids = array_reverse($ids);
		}

		return self::get_by_ids($ids);
	}

	/**
	 * Get last by transaction
	 *
	 * @access public
	 * @return \Skeleton\Transaction\Log
	 */
	public static function get_last_by_transaction(Transaction $transaction): self {
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
 	 * @return \Skeleton\Transaction\Log
	 */
	public static function get_last_successful(): self {
		$db = Database::get();
		$id = $db->get_one('SELECT id FROM transaction_log WHERE failed=0 ORDER BY created DESC LIMIT 1;', []);

		if ($id === null) {
			throw new \Exception('No transaction_log yet');
		}

		return self::get_by_id($id);
	}

	/**
	 * Create log.
	 *
	 * @access public
	 * @return Log $log
	 */
	public static function create(Transaction $transaction, bool $failed, string $output = '', ?\Throwable $t = null, ?string $date = null): self {
		$log = new self();
		$log->transaction_id = $transaction->id;
		$log->failed = $failed;
		$log->output = substr($output, 0, 16777215);

		if (isset($t) === true) {
			$log->exception = substr(print_r($t, true), 0, 16777215);
		}

		if ($date !== null) {
			$log->created = (new \DateTime($date))->format('Y-m-d H:i:s');
		}

		$log->save();

		return $log;
	}
}
