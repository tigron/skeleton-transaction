<?php
/**
 * Config class
 * Configuration for Skeleton\Transaction
 *
 * @author Christophe Gosiau <christophe@tigron.be>
 * @author Gerry Demaret <gerry@tigron.be>
 */

namespace Skeleton\Transaction;

class Config {

	/**
	 * pid file
	 *
	 * @access public
	 * @var string $pid_file
	 */
	public static $pid_file = '/tmp/skeleton-transaction.pid';

	/**
	 * max processes
	 *
	 * @access public
	 * @var int $max_processes
	 */
	public static $max_processes = 5;
}
