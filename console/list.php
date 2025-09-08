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

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableCell;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Transaction_List extends \Skeleton\Console\Command {
	/**
	 * Configure the Create command
	 *
	 * @access protected
	 */
	protected function configure(): void {
		$this->setName('transaction:list');
		$this->setDescription('List transactions that are scheduled to be executed');
	}

	/**
	 * Execute the Command
	 *
	 * @access protected
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$trn_running = \Skeleton\Transaction\Transaction::get_running();
		$trn_runnable = \Skeleton\Transaction\Transaction::get_runnable();
		$trn_scheduled = \Skeleton\Transaction\Transaction::get_scheduled();

		$table = new Table($output);

		$table->setHeaders(['ID', 'Type', 'Scheduled at', '//']);

		$rows = [];

		$rows[] = [ new TableCell('Running', ['colspan' => 4]) ];
		$rows[] = new TableSeparator();
		$running_count = 0;
		foreach ($trn_running as $transaction) {
			if ((bool)$transaction->parallel === false) {
				$rows[] = [ $transaction->id, $transaction->classname, $transaction->scheduled_at, $this->show_parallel($transaction) ];
				$running_count++;
			}
		}
		if ($running_count === 0) {
			$rows[] = [ 'FREE', 'FREE', 'FREE', 'NO' ];
			$running_count++;
		}
		foreach ($trn_running as $transaction) {
			if ((bool)$transaction->parallel === true) {
				$rows[] = [ $transaction->id, $transaction->classname, $transaction->scheduled_at, $this->show_parallel($transaction) ];
				$running_count++;
			}
		}
		for ($i = $running_count; $i < \Skeleton\Transaction\Config::$max_processes; $i++) {
			$rows[] = [ 'FREE', 'FREE', 'FREE', '' ];
		}

		$rows[] = new TableSeparator();

		$rows[] = [ new TableCell('Ready to run', ['colspan' => 3]) ];
		$rows[] = new TableSeparator();
		foreach ($trn_runnable as $transaction) {
			$rows[] = [ $transaction->id, $transaction->classname, $transaction->scheduled_at, $this->show_parallel($transaction) ];
		}
		if (sizeof($trn_runnable) === 0) {
			$rows[] = [ '/', '/', '/', '/' ];
		}

		$rows[] = new TableSeparator();
		$rows[] = [ new TableCell('Scheduled', ['colspan' => 3]) ];
		$rows[] = new TableSeparator();
		foreach ($trn_scheduled as $transaction) {
			$rows[] = [ $transaction->id, $transaction->classname, $transaction->scheduled_at, $this->show_parallel($transaction) ];
		}
		if (sizeof($trn_scheduled) === 0) {
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
	 */
	private function show_parallel(\Skeleton\Transaction\Transaction $transaction): string {
		if ((bool)$transaction->parallel === false) {
			return 'NO';
		}
		return '';
	}
}
