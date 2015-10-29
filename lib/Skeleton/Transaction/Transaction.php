<?php
/**
 * Transaction class
 *
 * Each action that takes place is defined in a specific transaction
 *
 * @abstract
 * @author Gerry Demaret <gerry@tigron.be>
 * @author Christophe Gosiau <christophe@tigron.be>
 * @author David Vandemaele <david@tigron.be>
 */

namespace Skeleton\Transaction;

abstract class Transaction {
	use \Skeleton\Object\Model {
		__construct as trait_construct;
	}
	use \Skeleton\Object\Get;
	use \Skeleton\Object\Save;
	use \Skeleton\Object\Delete;

	/**
	 * Run transaction
	 *
	 * @abstract
	 */
	abstract function run();

	/**
	 * Transaction
	 *
	 * @access public
	 * @param int $id
	 */
	public function __construct($id = null) {
		$classname = get_called_class();
		$this->classname = substr($classname, strpos($classname, '_') + 1);

		$this->trait_construct($id);
	}

	/**
	 * Freeze the transaction
	 *
	 * @access public
	 */
	public function freeze() {
		$this->frozen = true;
		$this->save();
	}

	/**
	 * Unfreeze the transaction
	 *
	 * @access public
	 */
	public function unfreeze() {
		$this->frozen = false;
		$this->save();
	}

	/**
	 * Schedule now
	 *
	 * @access public
	 */
	public function schedule($time = null) {
		if ($time === null) {
			$time = time();
		} else {
			$time = strtotime($time);
		}

		$this->scheduled_at = date('Y-m-d H:i:s', $time);
		$this->save();
	}

	/**
	 * Mark this transaction as failed
	 *
	 * @param string exception that is thrown
	 * @access public
	 */
	public function mark_failed($output, $exception) {
		$transaction_log = new \Skeleton\Transaction\Log();
		$transaction_log->transaction_id = $this->id;
		$transaction_log->output = $output;
		$transaction_log->failed = true;
		$transaction_log->exception = print_r($exception, true);
		$transaction_log->save();

		$this->failed = true;
		$this->completed = true;
		$this->save();
	}

	/**
	 * Mark completed
	 *
	 * @access public
	 * @param string $date
	 */
	public function mark_completed($output, $date = null) {
		$transaction_log = new \Skeleton\Transaction\Log();
		$transaction_log->transaction_id = $this->id;
		$transaction_log->output = $output;
		$transaction_log->failed = false;
		$transaction_log->save();

		$this->failed = false;
		$this->save();
	}

	/**
	 * Get transaction logs
	 *
	 * @access public
	 * @return array $transaction_logs
	 */
	public function get_transaction_logs() {
		return Transaction_Log::get_by_transaction($this);
	}

	/**
	 * Get transaction by id
	 *
	 * @param Transaction id
	 * @access public
	 */
	public static function get_by_id($id) {
		$db = \Skeleton\Database\Database::Get();
		$classname = $db->get_one('SELECT classname FROM transaction WHERE id=?', [ $id ]);
		$classname = 'Transaction_' . $classname;
		$transaction = new $classname($id);
		return $transaction;
	}

	/**
	 * Get runnable transactions
	 *
	 * @return array
	 * @access public
	 */
	public static function get_runnable() {
		$db = \Skeleton\Database\Database::Get();

		$transactions = array();
		$trans = $db->get_column('SELECT id FROM transaction WHERE scheduled_at < NOW() AND completed=0 AND frozen=0 AND failed=0 AND locked=0');
		foreach ($trans as $id) {
			$transactions[] = Transaction::get_by_id($id);
		}
		return $transactions;
	}
}
