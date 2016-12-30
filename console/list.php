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
		$trn_runnable = \Skeleton\Transaction\Transaction::get_runnable();
		$trn_scheduled = \Skeleton\Transaction\Transaction::get_scheduled();

		$length_id = 0;
		$length_classname = 0;
		foreach ($trn_runnable as $trn) {
			if (strlen($trn->classname) > $length_classname) {
				$length_classname = strlen($trn->classname);
			}
			if (strlen(strval($trn->id)) > $length_id) {
				$length_id = strlen(strval($trn->id));
			}
		}
		foreach ($trn_scheduled as $trn) {
			if (strlen($trn->classname) > $length_classname) {
				$length_classname = strlen($trn->classname);
			}
			if (strlen(strval($trn->id)) > $length_id) {
				$length_id = strlen(strval($trn->id));
			}
		}

		$table = new Table($output);

		$table->setHeaders(['ID', 'Type', 'Scheduled at']);

		$rows = [];

		foreach ($trn_runnable as $transaction) {
			$rows[] = [ $transaction->id, $transaction->classname, $transaction->scheduled_at ];
		}
		if (sizeof($trn_runnable) == 0) {
			$rows[] = [ str_repeat(' ', ($length_id / 2)) . '/', str_repeat(' ', ($length_classname / 2)) . '/', '         /' ];
		}
		$rows[] = [ str_repeat('-', $length_id), str_repeat('-', $length_classname), '-------------------' ];
		foreach ($trn_scheduled as $transaction) {
			$rows[] = [ $transaction->id, $transaction->classname, $transaction->scheduled_at ];
		}
		if (sizeof($trn_scheduled) == 0) {
			$rows[] = [ str_repeat(' ', ($length_id / 2)) . '/', str_repeat(' ', ($length_classname / 2)) . '/', '         /' ];
		}
		$table->setRows($rows);
		$table->render();

		return 0;
	}

}
