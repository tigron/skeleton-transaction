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
	 * Get last transaction_log
	 *
	 * @access public
	 * @return Transaction_Log $transaction_log
	 */
	public function get_last_transaction_log() {
		try {
			return Log::get_last_by_transaction($this);
		} catch (\Exception $e) {
			return null;
		}
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
		if (!$this->recurring) {
			throw new \Exception('Not allowed to schedule a recurring transaction manually');
		}

		if ($this->scheduled_at == '0000-00-00 00:00:00' OR $time === null) {
			$this->scheduled_at = date('Y-m-d H:i:s');
		}

		if (strtotime($this->scheduled_at) < strtotime($time, strtotime($this->scheduled_at))) {
			$this->scheduled_at = date('Y-m-d H:i:s');
		}

		if ($time !== null) {
			$this->scheduled_at = date('Y-m-d H:i:s', strtotime($time, strtotime($this->scheduled_at)));
		}

		$this->save();
	}

	/**
	 * Is scheduled
	 *
	 * @access public
	 * @return bool $scheduled
	 */
	public function is_scheduled() {
		if ($this->completed) {
			return false;
		} elseif (strtotime($this->scheduled_at) >= time() AND !$this->locked) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Mark locked
	 *
	 * @access public
	 * @param string $date
	 */
	public function lock() {
		$this->locked = true;
		$this->save();
	}

	/**
	 * Mark unlocked
	 *
	 * @access public
	 * @param string $date
	 */
	public function unlock() {
		$this->locked = false;
		$this->save();
	}

	/**
	 * Mark this transaction as failed
	 *
	 * @param string exception that is thrown
	 * @access public
	 */
	public function mark_failed($output, $exception, $date = null) {
		$transaction_log = new \Skeleton\Transaction\Log();
		$transaction_log->transaction_id = $this->id;
		$transaction_log->output = $output;
		$transaction_log->failed = true;
		$transaction_log->exception = print_r($exception, true);
		if ($date !== null) {
			$transaction_log->created = date('Y-m-d H:i:s', strtotime($date));
		}
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
		if ($date !== null) {
			$transaction_log->created = date('Y-m-d H:i:s', strtotime($date));
		}
		$transaction_log->save();

		$this->failed = false;
		if (!$this->recurring) {
			$this->completed = true;
		}

		$this->save();
	}

	/**
	 * Get transaction logs
	 *
	 * @access public
	 * @return array $transaction_logs
	 */
	public function get_transaction_logs($limit = null) {
		return Transaction_Log::get_by_transaction($this, $limit);
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

		$transactions = [];
		$trans = $db->get_column('SELECT id FROM transaction WHERE scheduled_at < NOW() AND completed=0 AND frozen=0 AND failed=0 AND locked=0');
		foreach ($trans as $id) {
			$transactions[] = self::get_by_id($id);
		}
		return $transactions;
	}
}
