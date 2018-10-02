<?php
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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Transaction_Run extends \Skeleton\Console\Command {

	/**
	 * Configure the Create command
	 *
	 * @access protected
	 */
	protected function configure() {
		$this->setName('transaction:run');
		$this->setDescription('Run a specific or all transactions that are scheduled to be executed');
		$this->addArgument('id', InputArgument::OPTIONAL, 'If an id is specified, only this transaction will be executed');
	}

	/**
	 * Execute the Command
	 *
	 * @access protected
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$transactions = [];
		if ($input->getArgument('id')) {
			$transactions[] = \Skeleton\Transaction\Transaction::get_by_id($input->getArgument('id'));
		} else {
			$transactions = \Skeleton\Transaction\Transaction::get_runnable();
		}

		foreach ($transactions as $transaction) {
			\Skeleton\Transaction\Runner::run_transaction($transaction);
			$transaction = \Skeleton\Transaction\Transaction::get_by_id($transaction->id);
			if ($transaction->failed) {
				$output->writeln($transaction->id . "\t" . $transaction->classname . "\t" . '<error>error</error>');
			} else {
				$output->writeln($transaction->id . "\t" . $transaction->classname . "\t" . '<info>done</info>');
			}
		}

		return 0;
	}

}
