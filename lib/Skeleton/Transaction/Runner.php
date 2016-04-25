<?php
/**
 * Transaction Runner
 *
 * This library fetches the runnable transaction and runs them
 */

namespace Skeleton\Transaction;

class Runner {

	/**
	 * Constructor
	 *
	 * @access public
	 */
	public function __construct() { }

	/**
	 * Run transactions
	 *
	 * @access public
	 */
	 public function run() {
		$transactions = Transaction::get_runnable();
		foreach ($transactions as $transaction) {
			self::run_transaction($transaction);
		}
	}

	/**
	 * Run transaction, acquire one if none set
	 *
	 * @access private
	 * @param Transaction
	 */
	public static function run_transaction(Transaction $transaction) {
		ob_start();
		echo date('[r] ') . 'Running ' . $transaction->classname . ' - ' . $transaction->id . "\n";

		$failed = false;
		try {
			$transaction->run();
		} catch (\Exception $e) {
			$failed = true;
		}
		echo "\n";
		echo date('[r] ') . 'Finished ' . $transaction->classname . ' - ' . $transaction->id . "\n";

		$content = ob_get_contents();
		ob_get_clean();

		if ($failed) {
			$transaction->mark_failed($content, $e);
		} else {
			$transaction->mark_completed($content);
		}
	}
}
