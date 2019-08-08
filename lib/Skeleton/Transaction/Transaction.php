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
		__get as trait_get;
	}
	use \Skeleton\Object\Get;
	use \Skeleton\Object\Save {
		save as trait_save;
	}
	use \Skeleton\Object\Delete;

	/**
	 * Non-persistent rescheduled flag
	 *
	 * @var boolean
	 */
	private $rescheduled = false;

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
		$this->parallel = $this->parallel();

		$this->trait_construct($id);
	}

	/**
	 * __get()
	 *
	 * @access public
	 * @param string $key
	 * @param return mixed
	 */
	public function __get($key) {
		if ($key === 'data') {
			// If the value in 'data' can be json_decoded, do so before returning
			// it. It will be encoded again in the save() method.
			if (is_string($this->details['data']) and json_decode($this->details['data']) !== null) {
				$this->details['data'] = json_decode($this->details['data'], true);
			}

			return $this->details['data'];
		} else {
			$return = $this->trait_get($key);
			return $return;
		}
	}

	/**
	 * save(): ensures 'data' is json_encoded before saving.
	 *
	 * @access public
	 */
	public function save() {
		// If 'data' can not be decoded, it means it has been decoded already
		// and we should encode it again before saving it.
		if (isset($this->details['data']) and (is_array($this->details['data']) or json_decode($this->details['data']) === null)) {
			$this->details['data'] = json_encode($this->details['data']);
		}

		$this->trait_save();
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
	 * parallel()
	 * Defaults to true, it can be overriden in any transaction and return false
	 * if the transaction should not be run in parallel.
	 *
	 * @access public
	 * @return bool
	 */
	public function parallel() {
		return true;
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

		// Keep a non-persistent flag, so we know not to mark this as completed
		// later on.
		$this->rescheduled = true;
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

		// Don't mark this transaction as completed if it has been rescheduled.
		if ($this->rescheduled or $this->frozen) {
			return;
		}

		$this->failed = false;
		if (!$this->recurring) {
			$this->completed = true;
		}
		$this->retry_attempt = 0;
		$this->save();
	}

	/**
	 * Get transaction logs
	 *
	 * @access public
	 * @return array $transaction_logs
	 */
	public function get_transaction_logs($limit = null) {
		return \Skeleton\Transaction\Log::get_by_transaction($this, $limit);
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
	 * Get transactions by classname
	 *
	 * @param $classname
	 * @param $limit
	 * @access public
	 */
	public static function get_by_classname($classname, $limit = null) {
		$db = \Skeleton\Database\Database::Get();

		$transactions = [];
		if (is_null($limit)) {
			$ids = $db->get_column('SELECT id FROM transaction WHERE classname=?', [ $classname ]);
		} else {
			$ids = $db->get_column('SELECT id FROM transaction WHERE classname=? ORDER BY id DESC LIMIT ?', [ $classname, $limit ]);
		}
		foreach ($ids as $id) {
			$transactions[] = self::get_by_id($id);
		}
		return $transactions;
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
		$ids = $db->get_column('
			SELECT id FROM
				(SELECT id, frozen, failed, locked, created FROM transaction WHERE scheduled_at < NOW() AND completed=0) AS transaction
			WHERE 1
			AND frozen = 0
			AND failed = 0
			AND locked = 0
		');

		foreach ($ids as $id) {
			$transactions[] = self::get_by_id($id);
		}

		return $transactions;
	}

	/**
	 * Get runnable transactions
	 *
	 * @return array
	 * @access public
	 */
	public static function count_runnable() {
		$db = \Skeleton\Database\Database::Get();

		$transactions = [];
		$count = $db->get_one('
			SELECT count(*) FROM
				(SELECT id, frozen, failed, locked, created FROM transaction WHERE scheduled_at < NOW() AND completed=0) AS transaction
			WHERE 1
			AND frozen = 0
			AND failed = 0
			AND locked = 0
		');

		return $count;
	}

	/**
	 * Get scheduled transactions
	 *
	 * @return array
	 * @access public
	 */
	public static function get_scheduled() {
		$db = \Skeleton\Database\Database::Get();

		$transactions = [];
		$ids = $db->get_column('SELECT id FROM transaction WHERE scheduled_at > NOW()', []);
		foreach ($ids as $id) {
			$transactions[] = self::get_by_id($id);
		}
		return $transactions;
	}

	/**
	 * Get running
	 *
	 * @return array
	 * @access public
	 */
	public static function get_running() {
		$db = \Skeleton\Database\Database::Get();
		$ids = $db->get_column('SELECT id FROM transaction WHERE locked=1 AND completed=0 ORDER BY parallel ASC', []);

		$transactions = [];
		foreach ($ids as $id) {
			$transactions[] = self::get_by_id($id);
		}
		return $transactions;
	}

	/**
	 * unlock_all()
	 *
	 * @access public
	 */
	public static function unlock_all() {
		$db = \Skeleton\Database\Database::Get();
		$db->query("UPDATE transaction SET locked=0 WHERE locked=1;", []);
	}

	/**
	 * Get failed recurring
	 *
	 * @access public
	 * @return array $transactions
	 */
	public static function get_failed_recurring() {
		$db = \Skeleton\Database\Database::get();
		$ids = $db->get_column('SELECT id FROM transaction WHERE recurring=1 AND (failed=1 OR completed=1)', []);
		$transactions = [];
		foreach ($ids as $id) {
			$transactions[] = self::get_by_id($id);
		}
		return $transactions;
	}
}
