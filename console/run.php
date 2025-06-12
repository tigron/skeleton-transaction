<?php

declare(strict_types=1);

/**
 * migration:create command for Skeleton Console
 *
 * @author Gerry Demaret <gerry@tigron.be>
 * @author Christophe Gosiau <christophe@tigron.be>
 * @author David Vandemaele <david@tigron.be>
 */

namespace Skeleton\Console\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Transaction_Run extends \Skeleton\Console\Command {
	/**
	 * Configure the Create command
	 *
	 * @access protected
	 */
	protected function configure(): void {
		$this->setName('transaction:run');
		$this->setDescription('Run a specific or all transactions that are scheduled to be executed');
		$this->addArgument('id', InputArgument::OPTIONAL, 'If an id is specified, only this transaction will be executed');
	}

	/**
	 * Execute the Command
	 *
	 * @access protected
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$transactions = [];
		if ($input->getArgument('id')) {
			$transactions[] = \Skeleton\Transaction\Transaction::get_by_id((int)$input->getArgument('id'));
		} else {
			$transactions = \Skeleton\Transaction\Transaction::get_runnable();
		}

		foreach ($transactions as $transaction) {
			\Skeleton\Transaction\Runner::run_transaction($transaction);
			$transaction = \Skeleton\Transaction\Transaction::get_by_id($transaction->id);

			try {
				$transaction_log = $transaction->get_last_transaction_log();
				if ($transaction_log->failed) {
					$output->writeln($transaction->id . "\t" . $transaction->classname . "\t" . '<error>error</error>');
				} else {
					$output->writeln($transaction->id . "\t" . $transaction->classname . "\t" . '<info>done</info>');
				}
			} catch (Exception $e) {
				if ($transaction->failed) {
					$output->writeln($transaction->id . "\t" . $transaction->classname . "\t" . '<error>error</error>');
				} else {
					$output->writeln($transaction->id . "\t" . $transaction->classname . "\t" . '<info>done</info>');
				}
			}
		}

		return 0;
	}
}
