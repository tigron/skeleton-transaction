<?php

declare(strict_types=1);

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
	 */
	private int $max_processes = 5;

	/**
	 * Internal array of process slots
	 *
	 * @access private
	 */
	private array $processes = [];

	/**
	 * Flags
	 *
	 * @access private
	 */
	private bool $flag_stop = false;

	/**
	 * lock_timestamp
	 *
	 * @access private
	 * @var $lock_timestamp
	 */
	private string|int|null $lock_timestamp = null;

	/**
	 * Constructor
	 *
	 * @access private
	 */
	public function __construct() {
		if (self::is_running()) {
			throw new \Exception('Transaction daemon already running');
		}

		/**
		 * Get lock file
		 */
		$this->get_lock();

		/**
		 * Install the signal handlers
		 */
		$this->install_signal_handlers();

		/**
		 * Provision the processes
		 */
		$this->max_processes = Config::$max_processes;
		for ($i = 0; $i < $this->max_processes; $i++) {
			$this->processes[$i] = new Process();
		}

		// unlocking all transactions in case the process did not stop properly
		Transaction::unlock_all();

		// Call unlock on the transaction lock_handler (in case it is persistent)
		\Skeleton\Lock\Handler::get()::release('transaction_runnable');
	}

	/**
	 * teardown
	 *
	 * @access private
	 */
	private function teardown(): void {
		/**
		 * Waiting for all processes to be terminated
		 */
		while ($this->transactions_running()) {
			// Wait 0.5 seconds
			usleep(500000);
		}

		/**
		 * Remove the PID file
		 */
		$this->remove_lock();
	}

	/**
	 * Run the daemon
	 *
	 * @access public
	 */
	public function run(): void {
		$run = true;
		while ($run) {
			pcntl_signal_dispatch();
			$this->refresh_lock();

			// If we are stopping, don't start new processes
			if ($this->flag_stop) {
				$run = false;
				continue;
			}

			try {
				$process = $this->get_idle_process();
			} catch (\Exception $e) {
				// No processes available, sleep for a bit and try again
				sleep(1);
				continue;
			}

			$transactions = Transaction::get_runnable();

			// If we are running a serial process, prevent a new serial from starting
			if ($this->serial_running()) {
				foreach ($transactions as $key => $transaction) {
					if (!$transaction->parallel) {
						unset($transactions[$key]);
					}
				}
			}

			// We have an idle process, try to load a transaction if there is one
			if (count($transactions) === 0) {
				sleep(1);
				continue;
			}

			$transaction = array_shift($transactions);

			try {
				$process->load_transaction($transaction);
			} catch (\Skeleton\Lock\Exception\Failed $e) {
				// If the process could not obtain an exclusive lock, bail out
				continue;
			} catch (Exception\Locked $e) {
				// If the transaction has been locked by another thread, bail out
				continue;
			}

			$process->run();
		}
		$this->teardown();
	}

	/**
	 * Handle the stop signal
	 *
	 * @access private
	 */
	public function handle_stop(): void {
		$this->flag_stop = true;
	}

	/**
	 * Refresh lock
	 *
	 * @access public
	 */
	public function refresh_lock(): void {
		if (!file_exists(Config::$pid_file)) {
			throw new \Exception('Problem with lock: Lock file does not exist');
		}

		$lock_pid = (int)file_get_contents(Config::$pid_file);
		if ($lock_pid !== getmypid()) {
			throw new \Exception('Problem with lock: Pid in lock is not ours');
		}

		if (time() - $this->lock_timestamp <= 5) {
			return;
		}

		file_put_contents(Config::$pid_file, getmypid());

		$this->lock_timestamp = time();

		$this->monitor();
	}

	/**
	 * Get status
	 *
	 * @access public
	 * @return array $status
	 */
	public static function status(): array {
		$status = file_get_contents(Config::$monitor_file);
		return json_decode($status, true);
	}

	/**
	 * Start
	 *
	 * @access public
	 */
	public static function start(): void {
		if (self::is_running()) {
			throw new \Exception('Transaction daemon is already running');
		}

		$pid = pcntl_fork();
		if ($pid === -1) {
			throw new \Exception('Error while forking');
		}
		if ($pid) {
			echo 'Daemon started, PID: ' . $pid . "\n";
		} else {
			// Child
			$title = realpath(getcwd() . '/' . $_SERVER['SCRIPT_FILENAME']) . ' transaction:daemon';
			\cli_set_process_title($title);
			$daemon = new self();
			$daemon->run();
			exit;
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

		$pid = (int)file_get_contents(Config::$pid_file);
		$return = posix_kill($pid, SIGTERM);
		echo 'Stopping daemon' . "\n";

		if ($return === false) {
			throw new \Exception('Unable to kill daemon');
		}

		echo 'Waiting for transactions to finish' . "\n";

		while (self::is_running()) {
			usleep(500000);
		}

		echo 'Daemon stopped' . "\n";

		return true;
	}

	/**
	 * public static function running
	 *
	 * @access public
	 * @return bool $running
	 */
	public static function is_running(): bool {
		if (!file_exists(Config::$pid_file)) {
			return false;
		}

		return true;
	}

	/**
	 * Install signal handlers
	 *
	 * @access private
	 */
	private function install_signal_handlers(): void {
		/**
		 * Graceful shutdown
		 */
		pcntl_signal(SIGINT, [$this, 'handle_stop']);
		pcntl_signal(SIGTERM, [$this, 'handle_stop']);
	}

	/**
	 * Check if a serial transaction is running
	 *
	 * @access private
	 * @return bool $serial_running
	 */
	private function serial_running(): bool {
		$serial_running = false;
		foreach ($this->processes as $process) {
			if ($process->is_running() and !$process->is_parallel()) {
				$serial_running = true;
			}
		}
		return $serial_running;
	}

	/**
	 * Check if a transaction is running
	 *
	 * @access private
	 * @return bool $transaction_running
	 */
	private function transactions_running(): bool {
		foreach ($this->processes as $process) {
			if ($process->is_running()) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get an idle process
	 *
	 * @access private
	 * @return Process $process;
	 */
	private function get_idle_process(): Process {
		foreach ($this->processes as $process) {
			if (!$process->is_running()) {
				return $process;
			}
		}
		throw new \Exception('No idle processes');
	}

	/**
	 * Get the lock
	 *
	 * @acces private
	 */
	private function get_lock(): void {
		if (self::is_running()) {
			throw new \Exception('Impossible to get lock, is Transaction Daemon already running?');
		}

		file_put_contents(Config::$pid_file, getmypid());

		$this->lock_timestamp = time();
	}

	/**
	 * Monitor the Daemon
	 *
	 * @access private
	 */
	private function monitor(): void {
		$monitor = new Monitor();
		$monitor->run();
	}

	/**
	 * Remove lock
	 *
	 * @acces private
	 */
	private function remove_lock(): void {
		if (!file_exists(Config::$pid_file)) {
			throw new \Exception('Problem with lock: Lock file does not exist');
		}

		$lock_pid = (int)file_get_contents(Config::$pid_file);
		if ($lock_pid !== getmypid()) {
			throw new \Exception('Problem with lock: Pid in lock is not ours ' . $lock_pid . ' ' . getmypid());
		}

		unlink(Config::$pid_file);

		$this->lock_timestamp = null;
	}
}
