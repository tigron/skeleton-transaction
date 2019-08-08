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
	public static $max_processes = 10;

	/**
	 * Log file
	 *
	 * @access public
	 * @var string $log_file
	 */
	public static $monitor_file = '/tmp/skeleton-transaction.status';

	/**
	 * Monitor authentication header
	 *
	 * x-authentication
	 *
	 * @access public
	 * @var string $monitor_authentication
	 */
	public static $monitor_authentication = null;
}
