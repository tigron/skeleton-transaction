<?php
/**
 * Module Monitor
 *
 * @author Christophe Gosiau <christophe@tigron.be>
 */

namespace Skeleton\Transaction\Web\Module;

use \Skeleton\Transaction\Daemon;

class Monitor extends \Skeleton\Core\Web\Module {

	/**
	 * Display the status
	 *
	 * @access public
	 */
	public function display() {
		$status = Daemon::status();

		$authorized = $this->authenticate();
		if (!$authorized) {
			header('HTTP/1.0 403 Access denied');
			exit;
		}

		$error = 200;
		$message = '';

		foreach ($status as $key => $check) {
			if (is_callable( [$this, 'check_' . $key])) {
				$error_code = call_user_func_array( [$this, 'check_' . $key], [ $check ]);
				if ($error_code > $error) {
					$error = $error_code;
					$message = 'check_' . $key . ': ' . $check['message'];
				}
			}
		}
		header('HTTP/1.0 ' . $error . ' ' . $message);
		echo $message;
	}

	/**
	 * Check database connection
	 *
	 * @access protected
	 */
	protected function check_database($check) {
		if (!$check['result']) {
			return 500;
		}
	}

	/**
	 * Check database connection
	 *
	 * @access protected
	 */
	protected function check_recurring($check) {
		if ($check['result'] > 0) {
			return 400;
		}
	}

	/**
	 * Check database connection
	 *
	 * @access protected
	 */
	protected function check_last_update($check) {
		$last_update = strtotime($check['result']);
		if ($last_update === false) {
			return 500;
		}

		if (time() - $last_update > 60) {
			return 500;
		}
	}

	/**
	 * Check runnable
	 *
	 * @access protected
	 */
	protected function check_runnable($check) {
		if ($check['result'] > 500) {
			return 400;
		}
	}

	/**
	 * Check last successful
	 *
	 * @access protected
	 */
	protected function check_last_successful($check) {
		$last_successful = strtotime($check['result']);
		if ($last_successful === false) {
			return 400;
		}

		if (time() - $last_successful > 60*60*24) {
			return 400;
		}
	}

	/**
	 * Secure the module
	 *
	 * @access private
	 */
	protected function authenticate() {
		if (!isset(\Skeleton\Transaction\Config::$monitor_authentication)) {
			return true;
		}

		if (!isset($_SERVER['HTTP_X_AUTHENTICATION'])) {
			return false;
		}

		if ($_SERVER['HTTP_X_AUTHENTICATION'] != \Skeleton\Transaction\Config::$monitor_authentication) {
			return false;
		}

		return true;
	}
}
