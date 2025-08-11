<?php

declare(strict_types=1);

/**
 * transaction:daemon command for Skeleton Console
 *
 * @author Gerry Demaret <gerry@tigron.be>
 * @author Christophe Gosiau <christophe@tigron.be>
 * @author David Vandemaele <david@tigron.be>
 */

namespace Skeleton\Console\Command;

use Skeleton\Transaction\Daemon;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

class Transaction_Daemon extends \Skeleton\Console\Command {
	/**
	 * Configure the Create command
	 *
	 * @access protected
	 */
	protected function configure(): void {
		$this->setName('transaction:daemon');
		$this->setDescription('Manage Transaction_Daemon');
		$this->addArgument('action', InputArgument::REQUIRED, 'start/stop/install');
	}

	/**
	 * Execute the Command
	 *
	 * @access protected
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$action = $input->getArgument('action');
		if (!is_callable([ $this, $action ])) {
			$output->writeln('<error>Please specify a valid action: start/stop/status</error>');
			return 1;
		}

		return (int)$this->$action($input, $output);
	}

	/**
	 * Start
	 *
	 * @access protected
	 */
	protected function start(InputInterface $input, OutputInterface $output) {
		try {
			ob_start();
			Daemon::start();
			$content = ob_get_contents();
			ob_end_clean();
		} catch (\Exception $e) {
			$output->writeln('<error>' . $e->getMessage() . ': daemon not started</error>');
			return 1;
		}
		$output->writeln('<info>' . $content . '</info>');
		return 0;
	}

	/**
	 * Stop
	 *
	 * @access protected
	 */
	protected function stop(InputInterface $input, OutputInterface $output) {
		try {
			Daemon::stop();
		} catch (\Exception $e) {
			$output->writeln('<error>' . $e->getMessage() . ': daemon not stopped</error>');
			return 1;
		}
		$output->writeln('<info>Transaction Daemon is stopped</info>');
		return 0;
	}

	/**
	 * Restart
	 *
	 * @access protected
	 */
	protected function restart(InputInterface $input, OutputInterface $output) {
		$this->stop($input, $output);
		$this->start($input, $output);
		return 0;
	}

	/**
	 * Foreground
	 *
	 * Runs the transaction daemon in foreground mode
	 *
	 * @access protected
	 */
	protected function foreground(InputInterface $input, OutputInterface $output) {
		try {
			$daemon = new Daemon();
			$daemon->run();
		} catch (\Exception $e) {
			$output->writeln('<error>' . $e->getMessage() . ': daemon not started</error>');
			return 1;
		}
	}

	/**
	 * Status
	 *
	 * @access protected
	 */
	protected function status(InputInterface $input, OutputInterface $output) {
		$status = Daemon::status();
		$table = new Table($output);

		$table->setHeaders(['Check', 'Result', 'Message']);

		$rows = [];
		foreach ($status as $key => $state) {
			$rows[] = [ $key, $state['result'], $state['message'] ];
		}

		$table->setRows($rows);
		$table->render();

		return 0;
	}

	/**
	 * Install
	 *
	 * @access protected
	 */
	protected function install(InputInterface $input, OutputInterface $output) {
		$dialog = $this->getHelper('question');

		if (posix_getuid() !== 0) {
			$output->writeln('<error>The installation requires root privileges</error>');
			return 0;
		}

		$question = new ConfirmationQuestion('Is this system using systemd? [y/N] ', false);

		if (!$dialog->ask($input, $output, $question)) {
			$output->writeln('<error>The install command currently only supports systemd</error>');
			return 0;
		}

		$question = new Question('Enter the path to your skeleton console [' . realpath($_SERVER['PHP_SELF']) . ']: ', realpath($_SERVER['PHP_SELF']));
		$skeleton_console = $dialog->ask($input, $output, $question);

		$question = new Question('Enter the path to your systemd configuration [/etc/systemd/]: ', '/etc/systemd/');
		$systemd_config = $dialog->ask($input, $output, $question);

		$question = new Question('Enter the name of the systemd unit [skeleton-transaction]: ', 'skeleton-transaction');
		$systemd_unitname = $dialog->ask($input, $output, $question);

		$question = new Question('Enter the username to run the daemon as []:', false);
		$systemd_username = $dialog->ask($input, $output, $question);

		if (!$systemd_username) {
			$output->writeln('<error>Please provide a username</error>');
			return 0;
		}

		$question = new Question('Enter the group to run the daemon as []:', false);
		$systemd_group = $dialog->ask($input, $output, $question);

		if (!$systemd_group) {
			$output->writeln('<error>Please provide a group</error>');
			return 0;
		}

		$content = '';
		$content .= '[Unit]' . "\n";
		$content .= 'Description=Skeleton transaction daemon: ' . $systemd_unitname . "\n";
		$content .= 'Requires=mysql.service' . "\n";
		$content .= 'After=network-online.target mysql.service' . "\n";
		$content .= "\n";
		$content .= '[Service]' . "\n";
		$content .= 'User=' . $systemd_username . "\n";
		$content .= 'Group=' . $systemd_group . "\n";
		$content .= 'ExecStart=' . $skeleton_console . ' transaction:daemon start' . "\n";
		$content .= 'ExecStop=' . $skeleton_console . ' transaction:daemon stop' . "\n";
		$content .= 'PIDFile=' . \Skeleton\Transaction\Config::$pid_file . "\n";
		$content .= 'RemainAfterExit=yes' . "\n";
		$content .= "\n";
		$content .= '[Install]' . "\n";
		$content .= 'WantedBy=multi-user.target' . "\n";

		$unitfile = $systemd_config . '/system/' . $systemd_unitname . '.service';
		$symlink = $systemd_config . '/system/multi-user.target.wants/' . $systemd_unitname . '.service';

		if (file_exists($unitfile)) {
			$output->writeln('<error>Target file already exists: ' . $unitfile . '</error>');
			return 0;
		}

		if (file_exists($symlink)) {
			$output->writeln('<error>Target file already exists: ' . $symlink . '</error>');
			return 0;
		}

		file_put_contents($unitfile, $content);
		symlink($unitfile, $symlink);
		exec('systemctl daemon-reload');

		$output->writeln('<info>Transaction daemon has been installed</info>');
		return 0;
	}
}
