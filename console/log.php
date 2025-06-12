<?php

declare(strict_types=1);

/**
 * Show log for a certain transaction from console
 *
 * @author Lionel Laffineur <lionel@tigron.be>
 */

namespace Skeleton\Console\Command;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Transaction_Log extends \Skeleton\Console\Command {
	/**
	 * Configure the command
	 *
	 * @access protected
	 */
	protected function configure(): void {
		$this->setName('transaction:log');
		$this->setDescription('Display the log for a certain transaction');
		$this->addArgument('id_or_classname', InputArgument::REQUIRED, 'Specify an transaction id or name');
	}

	/**
	 * Execute the Command
	 *
	 * @access protected
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$arg = $input->getArgument('id_or_classname');
		$transaction = null;
		$transaction_log = null;

		if (is_numeric($arg)) {
			$transaction = \Skeleton\Transaction\Transaction::get_by_id(intval($arg));
			$transaction_log = \Skeleton\Transaction\Log::get_last_by_transaction($transaction);
		} else {
			$transactions = \Skeleton\Transaction\Transaction::get_by_classname($arg, 1);
			foreach ($transactions as $transaction) {
				$transaction_log = \Skeleton\Transaction\Log::get_last_by_transaction($transaction);
			}
		}

		$table = new Table($output);

		$rows = [];
		$rows[] = [ 'Class name', $transaction->classname ];
		$rows[] = [ 'Transaction', $transaction->id ];
		$rows[] = [ 'Log', $transaction_log->id ];

		if (trim($transaction_log->output) !== '') {
			$rows[] = new Tableseparator();
			$rows[] = [ 'Output', $transaction_log->output ];
		}

		if (trim($transaction_log->exception) !== '') {
			$rows[] = new Tableseparator();
			$rows[] = [ 'Exception', $transaction_log->exception ];
		}

		$table->setRows($rows);
		$table->render();

		return 0;
	}
}
