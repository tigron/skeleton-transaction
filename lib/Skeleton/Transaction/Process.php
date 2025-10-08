<?php

declare(strict_types=1);

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
	 */
	private bool $running;

	/**
	 * Transaction
	 *
	 * @access private
	 */
	private ?\Skeleton\Transaction\Transaction $transaction = null;

	/**
	 * PID
	 *
	 * @access private
	 */
	private ?int $pid = null;

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
	public function handle_stop(): void {
		exit;
	}

	/**
	 * Load a transaction
	 *
	 * @access public
	 * @param \Skeleton\Transaction $transaction
	 */
	public function load_transaction(\Skeleton\Transaction\Transaction $transaction): void {
		$this->transaction = $transaction;
		$this->transaction->lock();
	}

	/**
	 * Get the current pool we are running a transaction for
	 *
	 * @access public
	 */
	public function get_pool(): ?string {
		if ($this->is_running() && $this->transaction !== null) {
			return $this->transaction->get_pool();
		}

		return null;
	}

	/**
	 * Check if the process is running
	 *
	 * @access public
	 * @return bool $is_running
	 */
	public function is_running(): bool {
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
	 * Run the transaction
	 *
	 * @access public
	 */
	public function run(): void {
		\Skeleton\Database\Database::reset();
		$pid = pcntl_fork();

		if ($pid === -1) {
			$this->transaction->running = false;
			$this->transaction->save();
			throw new \Exception('Process cannot be spawned');
		}

		if ($pid) {
			// parent process
			$this->pid = $pid;
			$this->running = true;
			return;
		}

		// child process
		posix_setsid();

		$pool = '';

		if ($this->transaction->pool !== null) {
			$pool = ' pool: ' . $this->transaction->pool;
		}

		cli_set_process_title(strtolower($this->transaction->classname) . ' (' . $this->transaction->id . ')' . $pool);
		$runner = new Runner();

		$runner->run_transaction($this->transaction);
		$this->transaction->unlock();

		exit;
	}

	/**
	 * Reset the process
	 *
	 * @access private
	 */
	private function reset(): void {
		$this->running = false;
		$this->transaction = null;
		$this->pid = null;
		$this->pool = null;
	}
}
