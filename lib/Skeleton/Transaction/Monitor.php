<?php
/**
 * Monitor class
 *
 * @author Christophe Gosiau <christophe@tigron.be>
 */

namespace Skeleton\Transaction;

use \Skeleton\Database\Database;

class Monitor {

	private $result;

	/**
	 * Run the monitoring
	 *
	 * @access public
	 */
	public function run() {
		$monitor_file = Config::$monitor_file;
		if ($monitor_file === null) {
			return;
		}

		$this->handle_database();
		$this->handle_recurring();
		$this->handle_last_update();
		$this->handle_runnable();
		$this->handle_last_successful();
		$this->write();
	}

	/**
	 * Check database
	 *
	 * @access private
	 */
	private function handle_database() {
		try {
			$db = Database::get();
			$columns = $db->get_columns('transaction');
			$result = [
				'result' => true,
				'message' => 'Database connection ok'
			];
		} catch (\Throwable $e) {
			$result = [
				'result' => false,
				'message' => $e->getMessage(),
			];
		}
		$this->result['database'] = $result;
	}

	/**
	 * Check recurring transactions
	 *
	 * @access private
	 */
	private function handle_recurring() {
		$transactions = Transaction::get_failed_recurring();
		$classnames = [];
		foreach ($transactions as $transaction) {
			$classnames[] = $transaction->classname;
		}
		$result = [
			'result' => count($transactions),
			'message' => implode(', ', $classnames),
		];
		$this->result['recurring'] = $result;
	}

	/**
	 * Check last update
	 *
	 * @access private
	 */
	private function handle_last_update() {
		$this->result['last_update'] = [
			'result' => date('Y-m-d H:i:s'),
			'message' => '',
		];
	}

	/**
	 * Check runnable
	 *
	 * @access private
	 */
	private function handle_runnable() {
		$count = Transaction::count_runnable();
		$result = [
			'result' => $count,
			'message' => '',
		];
		$this->result['runnable'] = $result;
	}

	/**
	 * Check last successful
	 *
	 * @access private
	 */
	private function handle_last_successful() {
		$log = \Skeleton\Transaction\Log::get_last_successful();
		$result = [
			'result' => $log->created,
			'message' => 'Transaction ' . $log->transaction_id . ': ' . $log->transaction->classname,
		];
		$this->result['last_successful'] = $result;
	}

	/**
	 * Write monitor file
	 *
	 * @access private
	 */
	private function write() {
		file_put_contents(Config::$monitor_file, json_encode($this->result));
	}

}
