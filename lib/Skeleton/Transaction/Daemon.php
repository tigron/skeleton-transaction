<?php
/**
 * Transaction Runner
 *
 * This library fetches the runnable transaction and runs them
 */

namespace Skeleton\Transaction;

class Daemon {
	/**
	 * Maximum number of parallel processes
	 * FIXME: make this a setting
	 *
	 * @access private
	 * @var int $max_processes
	 */
	private $max_processes = 5;

	/**
	 * Internal array of process slots
	 *
	 * @access private
	 * @var array $processes
	 */
	private $processes = [];

	/**
	 * Run
	 *
	 * @access public
	 */
	public function run() {
		// unlocking all transactions in case the process did not stop properly
		$pid = pcntl_fork();
		if ($pid == -1) {
			die('Could not fork');
		} else if ($pid) {
			// PARENT
			$status = null;
			pcntl_waitpid($pid, $status);
		} else {
			// CHILD
			Transaction::unlock_all();
			exit;
		}

		// initializing the processes array to free slots
		for ($i = 0; $i <= $this->max_processes; $i++) {
			$this->processes[$i] = -1;
		}

		// run forever
		while (true) {

			// loop through processes pool to see if there is free room
			for ($i = 0; $i <= $this->max_processes; $i++) {
				if ($this->is_slot_free($i)) {
					// slot is free > using it to search for transactions to run
					$pid = pcntl_fork();
					if ($pid == -1) {
						die('Could not fork');
					} else if ($pid) {
						// PARENT
						$this->lock_slot($i, $pid);
					} else {
						// CHILD
						if ($i == 0) {
							// slot zero is for non parallel transactions
							$transaction = Transaction::get_and_lock_first_runnable(false);
						} else {
							// other slots are for parallel transactions
							$transaction = Transaction::get_and_lock_first_runnable(true);
						}

						if ($transaction != null) {
							cli_set_process_title(strtolower($transaction->classname) . ' (' . $transaction->id . ')');
							$runner = new Runner();
							$runner->run_transaction($transaction);
							$transaction->unlock();
						}
						exit;
					}
				}
				sleep(1);
			}

			// loop five sleep time of a second
			for ($sleep = 0; $sleep < 5; $sleep++) {
				// loop through processes arry
				for ($i = 0; $i <= $this->max_processes; $i++) {
					// test if slot is used
					if ($this->processes[$i] >= 0) {
						$status = 0;
						// test if the process has finished (if true the freeing slot)
						$rc = pcntl_waitpid($this->processes[$i], $status, WNOHANG);
						if ($rc == $this->processes[$i]) {
							$this->free_slot($i);
						}
					}
				}
				sleep(1);
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
		Transaction::unlock_all();
		return true;
	}

	/**
	 * get_slot()
	 *
	 * @access private
	 * @return position (-1 if full)
	 */
	private function get_slot() {
		for ($i = 0; $i < $this->max_processes; $i++) {
			if ($this->processes[$i] == -1) {
				return $i;
			}
		}
		return -1;
	}

	/**
	 * lock_slot()
	 *
	 * @access private
	 * @param id
	 */
	private function lock_slot($id, $pid) {
		$this->processes[$id] = $pid;
	}

	/**
	 * free_slot()
	 *
	 * @access private
	 * @param id
	 */
	private function free_slot($id) {
		$this->processes[$id] = -1;
	}

	/**
	 * Check the status of a given slot
	 *
	 * @access private
	 * @param id
	 */
	private function is_slot_free($id) {
		return $this->processes[$id] == -1;
	}
}
