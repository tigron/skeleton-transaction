<?php
/**
 * Config class
 * Configuration for Skeleton\Transaction
 *
 * @author Christophe Gosiau <christophe@tigron.be>
 * @author Gerry Demaret <gerry@tigron.be>
 */

namespace Skeleton\Transaction;

trait Retry {

	protected static $next_retry = '+15 minutes';

	/**
	 * Retry transaction after a default time of 15 minutes
	 *
	 * @access public
	 * @param  $content
	 * @param  Exception $e
	 */
	public function retry($content = null, \Exception $e = null) {

		$this->schedule(self::$next_retry);
	}

	/**
	 * Retry transaction after a fixed time provided
	 *
	 * @access public
	 * @param  $interval
	 * @param  $content
	 * @param  Exception $e
	 */
	public function retry_fixed($interval, $content = null, \Exception $e = null) {

		$this->schedule($interval);
	}

	/**
	 * Retry transaction using an incremental time algorithm
	 *
	 * @access public
	 * @param  $start_interval (integer only)
	 * @param  $content
	 * @param  Exception $e
	 */
	public function retry_incremental($start_interval = 2, $content = null, \Exception $e = null) {

		if ($this->retry_interval == 0) {
			$this->retry_interval = $start_interval;
		} else if ($this->retry_interval > 30*24*60) {
			$this->retry_incremental_cancel();
			return;
		} else {
			$this->retry_interval = $this->retry_interval * 2;
		}
		$this->save();
		$this->schedule($this->retry_interval . ' minutes');
	}

	/**
	 * retry_incremental_cancel() resets the incremental for a transaction
	 *
	 * @access public
	 */
	public function retry_incremental_cancel() {
		$this->retry_interval = 0;
		$this->save();
	}
}
