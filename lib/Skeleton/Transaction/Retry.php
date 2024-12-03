<?php

declare(strict_types=1);

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
	 */
	public static int $max_attempts = -1;

	/**
	 * Retry transaction after a specified time (default: 15 minutes)
	 *
	 * @access public
	 */
	public function retry(string $output = '', string $next_retry = '+15 minutes'): void {
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
	 */
	public function retry_incremental(string $output = '', int $exp = 2, string $unit = 'minutes'): void {
		if (self::$max_attempts > 0 && $this->retry_attempt >= self::$max_attempts) {
			throw new \Exception($output);
		}

		Log::create($this, true, $output);

		$this->retry_attempt++;
		$this->schedule(pow($this->retry_attempt, $exp) . ' ' . $unit);
	}
}
