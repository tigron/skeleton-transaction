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
		$this->setDescription('Run transactions that are schedules to be executed');
	}

	/**
	 * Execute the Command
	 *
	 * @access protected
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$transactions = \Skeleton\Transaction\Transaction::get_runnable();
		foreach ($transactions as $transaction) {
			\Skeleton\Transaction\Runner::run_transaction($transaction);
			if ($transaction->failed) {
				$output->writeln($transaction->id . "\t" . $transaction->classname . "\t" . '<error>error</error>');
			} else {
				$output->writeln($transaction->id . "\t" . $transaction->classname . "\t" . '<info>done</info>');
			}
		}

		return 0;
	}

}
