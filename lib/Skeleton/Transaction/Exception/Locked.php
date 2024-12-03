<?php

declare(strict_types=1);

/**
 * Exception Locked class
 */

namespace Skeleton\Transaction\Exception;

class Locked extends \Exception {
	/**
	 * Constructor
	 *
	 * @access public
	 * @param string $message
	 */
	public function __construct() {
		$this->message = 'Transaction already locked';
	}
}
