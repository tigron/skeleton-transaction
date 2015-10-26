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
abstract class Transaction {
	use \Skeleton\Object\Model;
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
	 * Get code
	 *
	 * @access public
	 * @return string $code
	 */
	public function get_code() {
		$code = Util::reflection_get_code($this, 'run');
		$code = "\n" . $code;
		return str_replace("\n\t", "\n", $code);
	}

	/**
	 * Next run
	 *
	 * @access public
	 * @param string $next_run
	 */
	public function next_run($next_run) {
		if ($this->running_date == '0000-00-00 00:00:00') {
			$this->running_date = date('Y-m-d H:i:s');
		}

		$this->running_date = date('Y-m-d H:i:s', strtotime('+ ' . $next_run, strtotime($this->running_date)));
		$this->recurring = true;
		$this->recurring_interval = $next_run;
		$this->save();

		echo 'scheduling next run for ' . $next_run . ', next run on ' . $this->running_date . "\n";
	}

	/**
	 * Schedule now
	 *
	 * @access public
	 */
	public function schedule_now() {
		if (!$this->recurring) {
			throw new Exception('Not allowed to schedule a non-recurring transaction manually');
		}
		$this->running_date = date('Y-m-d H:i:s');
		$this->save();
	}

	/**
	 * Mark this transaction as failed
	 *
	 * @param string exception that is thrown
	 * @access public
	 */
	public function mark_failed($output, $exception, $date = null) {
		Util::log_transaction("\n" . 'Transaction ' . $this->id . ' failed: ' . date('Y-m-d H:i:s') . "\n");
		Util::alarm('Exception in transaction ' . $this->id, $exception);
		$transaction_log = new Transaction_Log();
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
		$transaction_log = new Transaction_Log();
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
	 * Cleanup transaction_logs
	 *
	 * @access private
	 */
	private function cleanup_transaction_logs() {
		Transaction_Log::cleanup_for_transaction($this);
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
		$db = Database::Get();
		$trans = $db->getRow('SELECT id, type FROM transaction WHERE id=? ORDER BY id ASC LIMIT 1', array($id));
		if ($trans === null) {
			return null;
		}
		require_once LIB_PATH . '/model/Transaction/'. str_replace('_', '/', $trans['type']) . '.php';
		$classname = 'Transaction_'.$trans['type'];
		$transaction = new $classname($trans['id']);
		return $transaction;
	}

	/**
	 * Get runnable transactions
	 *
	 * @return array
	 * @access public
	 */
	public static function get_runnable() {
		$db = Database::Get();

		$transactions = array();
		$trans = $db->getCol('SELECT id FROM transaction WHERE running_date < NOW() AND completed=0 AND frozen=0 AND failed=0');
		foreach ($trans as $id) {
			$transactions[] = Transaction::get_by_id($id);
		}
		return $transactions;
	}
}
