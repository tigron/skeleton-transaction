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
		$this->addArgument('action', InputArgument::REQUIRED, 'start/stop/install');
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

	/**
	 * Install
	 *
	 * @access protected
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 */
	protected function install(InputInterface $input, OutputInterface $output) {
		$dialog = $this->getHelper('dialog');

		if (posix_getuid() !== 0){
			$output->writeln('<error>The installation requires root privileges</error>');
			return 0;
		}

		if (!$dialog->askConfirmation($output, '<question>Is this system using systemd?</question> [y/N]: ', false)) {
			$output->writeln('<error>The install command currently only supports systemd</error>');
			return 0;
		}

		$using_mysql = $dialog->askConfirmation($output, '<question>Are you using MySQL?</question> [Y/n]: ', true);
		$systemd_config = $dialog->ask($output, 'Enter the path to your systemd configuration [/etc/systemd/]: ', '/etc/systemd/');
		$systemd_unitname = $dialog->ask($output, 'Enter the name of the systemd unit [skeleton-transaction]: ', 'skeleton-transaction');
		$systemd_username = $dialog->ask($output, 'Enter the username to run the daemon as []: ', false);

		if (!$systemd_username) {
			$output->writeln('<error>Please provide a username</error>');
			return 0;
		}

		$systemd_group = $dialog->ask($output, 'Enter the group to run the daemon as []: ', false);

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
		$content .= 'ExecStart=/var/www/measuring.benison.be/util/Transaction_Daemon.php' . "\n";
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

		$output->writeln('<info>Transaction daemon has been installed</info>');
		return 0;
	}
}
