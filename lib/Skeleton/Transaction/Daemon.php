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
		// initializing
		$this->max_processes = Config::$max_processes;

		// unlocking all transactions in case the process did not stop properly
		try {
			Transaction::unlock_all();
		} catch (\ErrorException $e) {
			printf("%s\n", $e->getMessage());
			die();
		}

		// initializing the processes array to free slots
		for ($i = 0; $i <= $this->max_processes; $i++) {
			$this->processes[$i] = -1;
		}

		// run forever
		while (true) {

			// counters for parallels and non-parallel transactions
			$not_parallel_free = 0;
			$parallels_free = 0;

			// cleaning processes array before to begin
			$this->clean_slots($not_parallel_free, $parallels_free);
			while ($not_parallel_free + $parallels_free == 0) {				// if all slots are used then sleep
				usleep(100000);												// for 1/10 second and see if one or
				$this->clean_slots($not_parallel_free, $parallels_free);	// more slot got freed
			}

			try {
				$transactions = Transaction::get_runnable();
			} catch (\ErrorException $e) {
				printf("%s\n", $e->getMessage());
				sleep(1);
				continue;
			}
			if (count($transactions) == 0) {	// if there is no transaction to
				sleep(1);						// process then sleep for a second
				continue;						// and start over
			}

			// testing every slot and if empty looking for a transaction to start
			for ($i = 0; $i < $this->max_processes; $i++) {
				if ($this->is_slot_free($i)) {
					if ($i == 0) {
						$transaction = $this->get_first_transaction($transactions, false);
					} else {
						$transaction = $this->get_first_transaction($transactions, true);
					}
					if ($transaction != null) {
printf("Slot %d -> %s (%d)\n", $i, $transaction->classname, $transaction->id);
						$sleep = 0; // as the queue of transactions is not empty, we won't sleep
						\Skeleton\Database\Database::reset();
						$pid = pcntl_fork();
						if ($pid == -1) {
							die('Could not fork');
						} else if ($pid) {
							// PARENT
							$this->lock_slot($i, $pid);
						} else {
							// CHILD
							cli_set_process_title(strtolower($transaction->classname) . ' (' . $transaction->id . ')');
							$runner = new Runner();
							$runner->run_transaction($transaction);
							$transaction->unlock();
							exit;
						}
					}
				}
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
	 * clean_slots
	 *
	 * @access private
	 * @param &$not_parallel_free
	 * @param &$parallels_free
	 */
	private function clean_slots(&$not_parallel_free, &$parallels_free) {
		$not_parallel_free = 0;
		$parallels_free = 0;
		for ($i = 0; $i < $this->max_processes; $i++) {
			// test if slot is used
			if ($this->processes[$i] >= 0) {
				$status = 0;
				// test if the process has finished (if true the freeing slot)
				$rc = pcntl_waitpid($this->processes[$i], $status, WNOHANG);
				if ($rc == $this->processes[$i]) {
					$this->free_slot($i);
					if ($i == 0) {
						$not_parallel_free++;
					} else {
						$parallels_free++;
					}
				}
				printf("[x]");
			} else {
				if ($i == 0) {
					$not_parallel_free++;
				} else {
					$parallels_free++;
				}
				printf("[ ]");
			}
		}
		printf("\n");
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

	/**
	 * Check the status of a given slot
	 *
	 * @access private
	 * @param id
	 */
	private function get_first_transaction(&$transactions, $parallel) {
		foreach ($transactions as $key => $transaction) {
			if ($transaction->parallel == $parallel) {
				unset($transactions[$key]);
				$transaction->lock_transaction();
				return $transaction;
			}
		}
		return null;
	}
}
