<?php

declare(strict_types=1);

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
	public function __construct() {
	}

	/**
	 * Run transaction, acquire one if none set
	 *
	 * @access private
	 * @param Transaction
	 */
	public static function run_transaction(Transaction $transaction): void {
		$log = '[' . date('d/m/Y H:i:s') . '] Transaction ' . $transaction->id . ' started (' . $transaction->classname . ') ' . "\n";

		ob_start();
		echo date('[r] ') . 'Running ' . $transaction->classname . ' - ' . $transaction->id . "\n";

		$failed = false;
		try {
			$transaction->run();
		} catch (\Throwable $e) {
			$failed = true;
		}

		echo date('[r] ') . 'Finished ' . $transaction->classname . ' - ' . $transaction->id . "\n";
		$content = ob_get_contents();
		ob_get_clean();

		$log .= '[' . date('d/m/Y H:i:s') . '] Transaction ' . $transaction->id . ' output: ' . "\n";
		$log .= print_r($content, true) . "\n";

		if ($failed) {
			$log .= '[' . date('d/m/Y H:i:s') . '] Transaction ' . $transaction->id . ' error: ' . "\n";
			$log .= print_r($e, true) . "\n";
		}

		$log .= '[' . date('d/m/Y H:i:s') . '] Finished ' . $transaction->classname . ' ' . $transaction->id . "\n";

		if (\Skeleton\Transaction\Config::$log_file !== null) {
			file_put_contents(\Skeleton\Transaction\Config::$log_file, $log, FILE_APPEND);
		}

		if ($failed) {
			$transaction->mark_failed($content, $e);
		} else {
			$transaction->mark_completed($content);
		}
	}
}
