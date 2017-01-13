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
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Helper\TableStyle;

class Transaction_List extends \Skeleton\Console\Command {

	/**
	 * Configure the Create command
	 *
	 * @access protected
	 */
	protected function configure() {
		$this->setName('transaction:list');
		$this->setDescription('List transactions that are scheduled to be executed');
	}

	/**
	 * Execute the Command
	 *
	 * @access protected
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$trn_running = \Skeleton\Transaction\Transaction::get_running();
		$trn_runnable = \Skeleton\Transaction\Transaction::get_runnable();
		$trn_scheduled = \Skeleton\Transaction\Transaction::get_scheduled();

		$table = new Table($output);

		$table->setHeaders(['ID', 'Type', 'Scheduled at', '//']);

		$rows = [];

		$rows[] = [ new TableCell('Running', array('colspan' => 4)) ];
		$rows[] = new TableSeparator();
		foreach ($trn_running as $transaction) {
			$rows[] = [ $transaction->id, $transaction->classname, $transaction->scheduled_at, $this->show_parallel($transaction) ];
		}
		if (sizeof($trn_running) == 0) {
			$rows[] = [ '/', '/', '/', '/' ];
		}
		$rows[] = new TableSeparator();

		$rows[] = [ new TableCell('Ready to run', array('colspan' => 3)) ];
		$rows[] = new TableSeparator();
		foreach ($trn_runnable as $transaction) {
			$rows[] = [ $transaction->id, $transaction->classname, $transaction->scheduled_at, $this->show_parallel($transaction) ];
		}
		if (sizeof($trn_runnable) == 0) {
			$rows[] = [ '/', '/', '/', '/' ];
		}

		$rows[] = new TableSeparator();
		$rows[] = [ new TableCell('Scheduled', array('colspan' => 3)) ];
		$rows[] = new TableSeparator();
		foreach ($trn_scheduled as $transaction) {
			$rows[] = [ $transaction->id, $transaction->classname, $transaction->scheduled_at, $this->show_parallel($transaction) ];
		}
		if (sizeof($trn_scheduled) == 0) {
			$rows[] = [ '/', '/', '/', '/' ];
		}
		$table->setRows($rows);
		$table->render();

		return 0;
	}

	/**
	 * show_parallel()
	 *
	 * @access private
	 * @param Transaction
	 * @return string
	 */
	private function show_parallel($transaction) {
		if ($transaction->parallel == 0) {
			return "NO";
		} else {
			return "";
		}
	}
}
