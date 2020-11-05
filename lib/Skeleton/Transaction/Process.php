<?php
/**
 * Transaction Process
 *
 * This library fetches the runnable transaction and runs them
 */

namespace Skeleton\Transaction;

class Process {

	/**
	 * Running
	 *
	 * @access private
	 * @var bool $running
	 */
	private $running;

	/**
	 * Transaction
	 *
	 * @access private
	 * @var \Skeleton\Transaction\Transaction $transaction
	 */
	private $transaction = null;

	/**
	 * PID
	 *
	 * @access private
	 * @var int $pid
	 */
	private $pid = null;

	/**
	 * Parallel
	 *
	 * @access private
	 * @var bool $parallel
	 */
	private $parallel = false;

	/**
	 * Constructor
	 *
	 * @access public
	 */
	public function __construct() {
		$this->running = false;
	}

	/**
	 * Handle stop
	 *
	 * @access public
	 */
	public function handle_stop() {
		echo 'stop child' . $this->pid . "\n";
		exit();
	}

	/**
	 * Load a transaction
	 *
	 * @access public
	 * @param \Skeleton\Transaction $transaction
	 */
	public function load_transaction(\Skeleton\Transaction\Transaction $transaction) {
		$this->transaction = $transaction;
		$this->transaction->lock();
		$this->parallel = $this->transaction->parallel;
	}

	/**
	 * Check if the process is running
	 *
	 * @access public
	 * @return bool $is_running
	 */
	public function is_running() {
		if ($this->pid === null) {
			return $this->running;
		}

		$exited_child_pid = pcntl_waitpid($this->pid, $status, WNOHANG);

		if ($exited_child_pid === $this->pid) {
			$this->reset();
		}

		return $this->running;
	}

	/**
	 * Reset the process
	 *
	 * @access private
	 */
	private function reset() {
		$this->running = false;
		$this->transaction = null;
		$this->pid = null;
		$this->parallel = false;
	}

	/**
	 * Is parallel
	 *
	 * @access public
	 * @return bool $is_parallel
	 */
	public function is_parallel() {
		$this->is_running();
		return $this->parallel;
	}

	/**
	 * Run the transaction
	 *
	 * @access public
	 */
	public function run() {
		\Skeleton\Database\Database::reset();
		$pid = pcntl_fork();

		if ($pid == -1) {
			$this->transaction->running = false;
			$this->transaction->save();
			throw new \Exception('Process cannot be spawned');
		} else if ($pid) {
			/**
			 * Parent
			 */
			$this->pid = $pid;
			$this->running = true;
			return;
		} else {
			/**
			 * Child
			 */
			posix_setsid();

			$parallel = 'serial';
			if ($this->transaction->parallel) {
				$parallel = 'parallel';
			}
			cli_set_process_title(strtolower($this->transaction->classname) . ' (' . $this->transaction->id . ') - ' . $parallel);
			$runner = new Runner();

			$db = \Skeleton\Database\Database::get();
			$runner->run_transaction($this->transaction);
			$this->transaction->unlock();

			exit;
		}
	}

}
