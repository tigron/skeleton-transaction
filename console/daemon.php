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
use \Skeleton\Transaction\Daemon;

class Transaction_Daemon extends \Skeleton\Console\Command {

	/**
	 * Configure the Create command
	 *
	 * @access protected
	 */
	protected function configure() {
		$this->setName('transaction:daemon');
		$this->setDescription('Manage Transaction_Daemon');
		$this->addArgument('action', InputArgument::REQUIRED, 'start/stop');
	}

	/**
	 * Execute the Command
	 *
	 * @access protected
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		$action = $input->getArgument('action');
		if (!is_callable([ $this, $action ])) {
			$output->writeln('<error>Please specify a valid action: start/stop/status</error>');
			return 1;
		}
		return $this->$action($input, $output);
	}

	/**
	 * Start
	 *
	 * @access protected
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
	protected function start(InputInterface $input, OutputInterface $output) {
		try {
			$pid = Daemon::start();
		} catch (\Exception $e) {
			$output->writeln('<error>' . $e->getMessage() . ': Please specify a valid action: start/stop/status</error>');
			return 1;
		}
		$output->writeln('<info>Transaction Daemon is running, pid ' . $pid . '</info>');
		return 0;
	}

	/**
	 * Stop
	 *
	 * @access protected
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
	protected function stop(InputInterface $input, OutputInterface $output) {
		try {
			$pid = Daemon::stop();
		} catch (\Exception $e) {
			$output->writeln('<error>' . $e->getMessage() . ': Please specify a valid action: start/stop/status</error>');
			return 1;
		}
		$output->writeln('<info>Transaction Daemon is stopped</info>');
		return 0;
	}

	/**
	 * Status
	 *
	 * @access protected
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
	protected function status(InputInterface $input, OutputInterface $output) {
		if (Daemon::is_running()) {
			$output->writeln('<info>Transaction Daemon is running</info>');
		} else {
			$output->writeln('<error>Transaction Daemon is not running</error>');
		}
		return 0;
	}


}
