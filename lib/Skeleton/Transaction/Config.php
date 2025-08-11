<?php

declare(strict_types=1);

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
	 */
	public static string $pid_file = '/tmp/skeleton-transaction.pid';

	/**
	 * max processes
	 *
	 * @access public
	 */
	public static int $max_processes = 10;

	/**
	 * Log file
	 *
	 * @access public
	 */
	public static string $monitor_file = '/tmp/skeleton-transaction.status';

	/**
	 * Monitor authentication header
	 *
	 * x-authentication
	 *
	 * @access public
	 */
	public static ?string $monitor_authentication = null;
}