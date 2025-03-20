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
	 * Parallel
	 *
	 * @access private
	 */
	private ?bool $parallel = false;

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
		echo 'stop child' . $this->pid . "\n";
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
		$this->parallel = $this->transaction->parallel;
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
	 * Is parallel
	 *
	 * @access public
	 * @return bool $is_parallel
	 */
	public function is_parallel(): bool {
		$this->is_running();
		return $this->parallel;
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
			/**
			 * Parent
			 */
			$this->pid = $pid;
			$this->running = true;
			return;
		}

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
		$this->parallel = false;
	}
}
