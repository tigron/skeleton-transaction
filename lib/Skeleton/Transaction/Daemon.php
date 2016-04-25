<?php
/**
 * Transaction Runner
 *
 * This library fetches the runnable transaction and runs them
 */

namespace Skeleton\Transaction;

class Daemon {

	/**
	 * Run
	 *
	 * @access public
	 */
	public function run() {
		$pid = pcntl_fork();

		while (true) {
			if($pid == -1) {
				die('Could not fork');
			} elseif ($pid) {
				// Parent
				pcntl_waitpid($pid, $status);
				sleep(5);
				$pid = pcntl_fork();
			} else {
				// Child
				$trans = new Runner();
				$trans->run();
				exit;
			}
		}
	}

	/**
	 * public static function running
	 *
	 * @access public
	 * @return bool $running
	 */
	public static function is_running() {
		if (!file_exists(Config::$pid_file)) {
			return false;
		}

		$pid = file_get_contents(Config::$pid_file);
		if (posix_getpgid($pid) === false) {
			unlink(Config::$pid_file);
			return false;
		}

		return true;
	}

	/**
	 * Start
	 *
	 * @access public
	 */
	public static function start() {
		if (self::is_running()) {
			throw new \Exception('Transaction daemon is already running');
		}
		$pid = pcntl_fork();
		if ($pid == -1) {
			throw new \Exception('Error while forking');
		} elseif ($pid) {
			file_put_contents(Config::$pid_file, $pid);
			return $pid;
		} else {
			// Child
			$daemon = new self();
			$daemon->run();
		}
	}

	/**
	 * Stop
	 *
	 * @access public
	 */
	public static function stop() {
		if (!self::is_running()) {
			throw new \Exception('Transaction daemon is not running');
		}

		$pid = file_get_contents(Config::$pid_file);
		$return = posix_kill($pid, 15);
		if ($return === false) {
			throw new \Exception('Unable to kill daemon');
		}
		return true;
	}

}
