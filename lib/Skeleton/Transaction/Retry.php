<?php
/**
 * Config class
 * Configuration for Skeleton\Transaction
 *
 * @author Christophe Gosiau <christophe@tigron.be>
 * @author Gerry Demaret <gerry@tigron.be>
 * @author David Vandemaele <david@tigron.be>
 */

namespace Skeleton\Transaction;

trait Retry {

	/**
	 * Max attempts
	 *
	 * @var integer
	 */
	static $max_attempts = -1;

	/**
	 * Retry transaction after a specified time (default: 15 minutes)
	 *
	 * @access public
	 * @param string $output
	 * @param string $next_retry
	 */
	public function retry(string $output = '', $next_retry = '+15 minutes') {
		if (self::$max_attempts > 0 && $this->retry_attempt >= self::$max_attempts) {
			throw new \Exception($output);
		}

		Log::create($this, true, $output);

		$this->retry_attempt++;
		$this->schedule($next_retry);
	}

	/**
	 * Retry transaction using an incremental time algorithm
	 *
	 * @access public
	 * @param string $output
	 * @param int $exp
	 * @param string $unit
	 */
	public function retry_incremental(string $output = '', $exp = 2, $unit = 'minutes') {
		if (self::$max_attempts > 0 && $this->retry_attempt >= self::$max_attempts) {
			throw new \Exception($output);
		}

		Log::create($this, true, $output);

		$this->retry_attempt++;
		$this->schedule(pow($this->retry_attempt, $exp) . ' ' . $unit);
	}
}
