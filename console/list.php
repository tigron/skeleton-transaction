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
use Symfony\Component\Console\Helper\Table;

class Transaction_List extends \Skeleton\Console\Command {

	/**
	 * Configure the Create command
	 *
	 * @access protected
	 */
	protected function configure() {
		$this->setName('transaction:list');
		$this->setDescription('List transactions that are schedules to be executed');
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

		$table = new Table($output);

		$table->setHeaders(['ID', 'Type', 'Scheduled at']);

		$rows = [];

		foreach ($transactions as $transaction) {
			$rows[] = [ $transaction->id, $transaction->classname, $transaction->scheduled_at ];
		}
		$table->setRows($rows);
		$table->render();

		return 0;
	}

}
