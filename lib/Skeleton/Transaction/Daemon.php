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
	 * Maximum number of processes, set via Config
	 */
	private int $max_processes = 10;

	/**
	 * Internal array of process slots
	 *
	 * @var array<Process>
	 */
	private array $processes = [];

	/**
	 * Flags
	 */
	private bool $flag_stop = false;

	/**
	 * lock_timestamp
	 */
	private string|int|null $lock_timestamp = null;

	/**
	 * PID of the monitor thread
	 */
	private ?int $monitor_pid = null;

	/**
	 * Constructor
	 */
	public function __construct() {
		if (self::is_running()) {
			throw new \Exception('Transaction daemon already running');
		}

		// acquire a lock
		$this->get_lock();

		// install the signal handlers
		$this->install_signal_handlers();

		// provision the desired number of processes
		$this->max_processes = Config::$max_processes;
		for ($i = 0; $i < $this->max_processes; $i++) {
			$this->processes[$i] = new Process();
		}

		// unlocking all transactions in case the process did not stop properly
		Transaction::unlock_all();

		// call unlock on the transaction lock_handler (in case it is persistent)
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
		$last_transaction = time();

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
				// No processes available, sleep for 0.05s and try again
				// We're probably very busy so a short retry should be good
				usleep(50000);
				continue;
			}

			// This is by definition a single thread, so it should be safe to
			// get the currently running pools from here and exclude those from
			// the fetched transaction list
			$running_pools = $this->get_running_pools();
			$transactions = Transaction::get_runnable(exclude_pools: $running_pools);

			// We have an idle process, try to load a transaction if there is one
			if (count($transactions) === 0) {
				// back off for 0.2 to 5 seconds, depending on when we last did
				// something
				$since_last_transaction = time() - $last_transaction;
				$backoff = min(5, (floor($since_last_transaction / 5) * 0.2) + 0.2);
				usleep((int)($backoff * 1000000));
				continue;
			}

			$last_transaction = time();

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
			throw new \Exception('Problem with lock: lock file does not exist');
		}

		$lock_pid = (int)file_get_contents(Config::$pid_file);
		if ($lock_pid !== getmypid()) {
			throw new \Exception('Problem with lock: PID in lock is not ours');
		}

		// bail out if the lock is still fresh
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
	 * @return array<string> Array with status information
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
	public static function stop(): bool {
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
		// not immediately returning a process upon match as is_running() will
		// call waitpid() on the process, which in case the process had stopped
		// will make the cleanup happen faster
		$idle_process = null;
		foreach ($this->processes as $process) {
			if (!$process->is_running()) {
				$idle_process = $process;
			}
		}

		if ($idle_process !== null) {
			return $idle_process;
		}

		throw new \Exception('No idle processes');
	}

	/**
	 * Get the pools we are currently running a process for
	 *
	 * @return array<string> List of pools for which we currently have a running process
	 * @access private
	 */
	private function get_running_pools(): array {
		$pools = [];
		foreach ($this->processes as $process) {
			$process_pool = $process->get_pool();
			if ($process_pool !== null) {
				$pools[] = $process_pool;
			}
		}

		return array_unique($pools);
	}

	/**
	 * Get the lock
	 *
	 * @acces private
	 */
	private function get_lock(): void {
		if (self::is_running()) {
			throw new \Exception('Could not get lock, is the daemon already running?');
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
		// clean up previous monitor child if there was one, if it still exists
		// then don't start another just yet
		if ($this->monitor_pid !== null) {
			$exited_pid = pcntl_waitpid($this->monitor_pid, $status, WNOHANG);

			if ($exited_pid === $this->monitor_pid) {
				$this->monitor_pid = null;
			} else {
				return;
			}
		}

		$pid = pcntl_fork();

		\Skeleton\Database\Database::reset();

		// if pid is -1 we couldn't fork and things will probably not go well
		// from here, perhaps add some graceful recovery

		if ($pid) {
			// parent process, $pid is the forked process

			$this->monitor_pid = $pid;
			return;
		}

		if ($pid === 0) {
			// child process

			posix_setsid();
			cli_set_process_title('monitor');

			$monitor = new Monitor();
			$monitor->run();

			exit(0);
		}
	}

	/**
	 * Remove lock
	 *
	 * @acces private
	 */
	private function remove_lock(): void {
		if (!file_exists(Config::$pid_file)) {
			throw new \Exception('Problem with lock: lock file does not exist');
		}

		$lock_pid = (int)file_get_contents(Config::$pid_file);
		if ($lock_pid !== getmypid()) {
			throw new \Exception('Problem with lock: PID is not ours, ' . $lock_pid . ' while we are ' . getmypid());
		}

		unlink(Config::$pid_file);

		$this->lock_timestamp = null;
	}
}
