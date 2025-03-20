<?php

declare(strict_types=1);

/**
 * Monitor class
 *
 * @author Christophe Gosiau <christophe@tigron.be>
 */

namespace Skeleton\Transaction;

use Skeleton\Database\Database;

class Monitor {
	/**
	 * @access private
	 */
	private array $result;

	/**
	 * Run the monitoring
	 *
	 * @access public
	 */
	public function run(): void {
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
	private function handle_database(): void {
		try {
			$db = Database::get();

			$db->get_columns('transaction');

			$result = [
				'result' => true,
				'message' => 'Database connection ok',
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
	private function handle_recurring(): void {
		$transactions = Transaction::get_failed_recurring();

		$classnames = [];
		foreach ($transactions as $transaction) {
			$classnames[] = $transaction->classname;
		}

		$this->result['recurring'] = [
			'result' => count($transactions),
			'message' => implode(', ', $classnames),
		];
	}

	/**
	 * Check last update
	 *
	 * @access private
	 */
	private function handle_last_update(): void {
		$this->result['last_update'] = [
			'result' => (new \DateTime())->format('Y-m-d H:i:s'),
			'message' => '',
		];
	}

	/**
	 * Check runnable
	 *
	 * @access private
	 */
	private function handle_runnable(): void {
		$this->result['runnable'] = [
			'result' => Transaction::count_runnable(),
			'message' => '',
		];
	}

	/**
	 * Check last successful
	 *
	 * @access private
	 */
	private function handle_last_successful(): void {
		try {
			$log = \Skeleton\Transaction\Log::get_last_successful();
		} catch (\Exception $e) {
			// If the transaction log is completely empty, you are probably running
			// the daemon for the first time.
			$this->result['last_successful'] = [
				'result' => '0000-00-00 00:00:00',
				'message' => 'Transaction log is empty',
			];

			return;
		}

		$transaction = Transaction::get_by_id($log->transaction_id);

		$this->result['last_successful'] = [
			'result' => $log->created,
			'message' => 'Transaction ' . $log->transaction_id . ': ' . $transaction->classname,
		];
	}

	/**
	 * Write monitor file
	 *
	 * @access private
	 */
	private function write(): void {
		file_put_contents(Config::$monitor_file, json_encode($this->result));
	}
}
