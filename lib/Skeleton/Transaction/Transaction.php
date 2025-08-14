<?php

declare(strict_types=1);

/**
 * Transaction class
 *
 * Each action that takes place is defined in a specific transaction
 *
 * @abstract
 * @author Gerry Demaret <gerry@tigron.be>
 * @author Christophe Gosiau <christophe@tigron.be>
 * @author David Vandemaele <david@tigron.be>
 */

namespace Skeleton\Transaction;

use Skeleton\Database\Database;

abstract class Transaction {
	use \Skeleton\Object\Model {
		__construct as trait_construct;
		__get as trait_get;
	}
	use \Skeleton\Object\Get;
	use \Skeleton\Object\Save {
		save as trait_save;
	}
	use \Skeleton\Object\Delete;

	/**
	 * Non-persistent rescheduled flag
	 */
	private bool $rescheduled = false;

	/**
	 * Transaction
	 *
	 * @access public
	 */
	public function __construct(?int $id = null) {
		$classname = get_called_class();
		$this->classname = substr($classname, strpos($classname, '_') + 1);
		$this->parallel = $this->parallel();
		$this->weight = $this->get_weight();

		$this->trait_construct($id);
	}

	/**
	 * __get()
	 *
	 * @access public
	 * @param return mixed
	 */
	public function __get(string $key) {
		if ($key === 'data') {
			// If the value in 'data' can be json_decoded, do so before returning
			// it. It will be encoded again in the save() method.
			if (is_string($this->details['data']) && json_decode($this->details['data']) !== null) {
				$this->details['data'] = json_decode($this->details['data'], true);
			}

			return $this->details['data'];
		}

		// temporary workaround until we somehow fix this in skeleton-database
		if (in_array($key, ['completed', 'failed', 'recurring', 'parallel', 'locked'])) {
			return (bool)$this->trait_get($key);
		}

		return $this->trait_get($key);
	}

	/**
	 * Run transaction
	 *
	 * @abstract
	 */
	abstract public function run(): void;

	/**
	 * save(): ensures 'data' is json_encoded before saving.
	 *
	 * @access public
	 */
	public function save(): void {
		// If 'data' can not be decoded, it means it has been decoded already
		// and we should encode it again before saving it.
		if (isset($this->details['data']) && (is_array($this->details['data']) || json_decode($this->details['data']) === null)) {
			$this->details['data'] = json_encode($this->details['data']);
		}

		$this->trait_save();
	}

	/**
	 * Get last transaction_log
	 *
	 * @access public
	 * @return ?Log $log
	 */
	public function get_last_transaction_log(): ?Log {
		try {
			return Log::get_last_by_transaction($this);
		} catch (\Exception $e) {
		}

		return null;
	}

	/**
	 * parallel()
	 * Defaults to true, it can be overriden in any transaction and return false
	 * if the transaction should not be run in parallel.
	 *
	 * @access public
	 */
	public function parallel(): bool {
		return true;
	}

	/**
	 * Schedule now
	 *
	 * @access public
	 */
	public function schedule(?string $time = null): void {
		if (!isset($this->scheduled_at) || $this->scheduled_at === null || $time === null) {
			$this->scheduled_at = (new \DateTime())->format('Y-m-d H:i:s');
		}

		if ($time !== null && new \DateTime($this->scheduled_at) < (new \DateTime($this->scheduled_at))->modify($time)) {
			$this->scheduled_at = (new \DateTime())->format('Y-m-d H:i:s');
		}

		if ($time !== null) {
			$this->scheduled_at = (new \DateTime($this->scheduled_at))->modify($time)->format('Y-m-d H:i:s');
		}

		$this->save();

		// Keep a non-persistent flag, so we know not to mark this as completed
		// later on.
		$this->rescheduled = true;
	}

	/**
	 * Unschedule
	 *
	 * @access public
	 */
	public function unschedule(): void {
		$this->scheduled_at = null;
		$this->save();
	}

	/**
	 * Is scheduled
	 *
	 * @access public
	 * @return bool $scheduled
	 */
	public function is_scheduled(): bool {
		if ((bool) $this->completed === true) {
			return false;
		}

		if (
			isset($this->scheduled_at) === true
			&& new \DateTime($this->scheduled_at) >= new \DateTime()
			&& (bool) $this->locked === false
		) {
			return true;
		}

		return false;
	}

	/**
	 * Mark locked
	 *
	 * @access public
	 * @param string $date
	 */
	public function lock(): void {
		$lock_name = 'transaction_runnable';
		\Skeleton\Lock\Handler::get()::obtain($lock_name);

		// refresh the current object's details before verifying the lock status
		$this->get_details();

		if ((bool) $this->locked === true) {
			\Skeleton\Lock\Handler::get()::release($lock_name);
			throw new Exception\Locked();
		}

		$this->locked = true;
		$this->save();

		\Skeleton\Lock\Handler::get()::release($lock_name);
	}

	/**
	 * Mark unlocked
	 *
	 * @access public
	 * @param string $date
	 */
	public function unlock(): void {
		$this->locked = false;
		$this->save();
	}

	/**
	 * Mark this transaction as failed
	 *
	 * @param string exception that is thrown
	 * @access public
	 */
	public function mark_failed(string $output, \Throwable $exception, ?string $date = null): void {
		Log::create($this, true, $output, $exception, $date);

		$this->failed = true;
		$this->completed = true;
		$this->save();

		// Report exception
		if (class_exists('\Skeleton\Error\Handler') === true) {
			$handler = \Skeleton\Error\Handler::enable();
			$handler->report_exception($exception);
		}
	}

	/**
	 * Mark completed
	 *
	 * @access public
	 */
	public function mark_completed(string $output, ?string $date = null): void {
		Log::create($this, false, $output, null, $date);

		// Don't mark this transaction as completed if it has been rescheduled.
		if ((bool) $this->rescheduled === true) {
			return;
		}

		$this->failed = false;

		if ((bool) $this->recurring === false) {
			$this->completed = true;
		}

		$this->retry_attempt = 0;
		$this->save();
	}

	/**
	 * Get the weight of this transaction
	 *
	 * @access public
	 */
	public function get_weight(): int {
		return 10;
	}

	/**
	 * Get transaction logs
	 *
	 * @access public
	 * @return array $transaction_logs
	 */
	public function get_transaction_logs(?int $limit = null): array {
		return Log::get_by_transaction($this, $limit);
	}

	/**
	 * Get transaction by id
	 *
	 * @param Transaction id
	 * @access public
	 */
	public static function get_by_id(int $id) {
		$db = Database::get();
		$classname = $db->get_one('SELECT classname FROM transaction WHERE id = ?', [$id]);

		if ($classname === null) {
			throw new \Exception('Transaction not found');
		}

		$classname = 'Transaction_' . $classname;
		return new $classname($id);
	}

	/**
	 * Get transactions by classname
	 *
	 * @access public
	 */
	public static function get_by_classname(string $classname, ?int $limit = null): array {
		$db = Database::get();
		$query = 'SELECT id FROM transaction WHERE classname = ? ORDER BY id DESC';
		$params = [$classname];

		if ($limit !== null) {
			$query .= ' LIMIT ?';
			$params[] = $limit;
		}

		$ids = $db->get_column($query, $params);

		return self::get_by_ids($ids);
	}

	/**
	 * Get runnable transactions
	 *
	 * @access public
	 */
	public static function get_runnable(): array {
		$db = Database::get();
		$ids = $db->get_column('
			SELECT id FROM
				(	SELECT id, failed, locked, scheduled_at, weight
					FROM transaction
					WHERE 1
					AND scheduled_at < NOW()
					AND completed = 0
				) AS transaction
			WHERE 1
			AND scheduled_at IS NOT NULL
			AND failed = 0
			AND locked = 0
			ORDER BY weight ASC, scheduled_at, id
			LIMIT ?;
		', [ Config::$max_processes ]);

		return self::get_by_ids($ids);
	}

	/**
	 * Get runnable transactions
	 *
	 * @access public
	 */
	public static function count_runnable(): int {
		$db = Database::get();

		return (int) $db->get_one('
			SELECT count(1) FROM
				(SELECT failed, locked, scheduled_at FROM transaction WHERE scheduled_at < NOW() AND completed = 0) AS transaction
			WHERE 1
			AND scheduled_at IS NOT NULL
			AND failed = 0
			AND locked = 0;
		');
	}

	/**
	 * Get scheduled transactions
	 *
	 * @access public
	 */
	public static function get_scheduled(): array {
		$db = Database::get();
		$ids = $db->get_column('SELECT id FROM transaction WHERE scheduled_at > NOW();', []);

		return self::get_by_ids($ids);
	}

	/**
	 * Get running
	 *
	 * @access public
	 */
	public static function get_running(): array {
		$db = Database::get();
		$ids = $db->get_column('SELECT id FROM transaction WHERE locked = 1 AND completed = 0 ORDER BY parallel ASC;', []);

		return self::get_by_ids($ids);
	}

	/**
	 * unlock_all()
	 *
	 * @access public
	 */
	public static function unlock_all(): void {
		$db = Database::get();
		$db->query('UPDATE transaction SET locked = 0 WHERE locked = 1;', []);
	}

	/**
	 * Get failed recurring
	 *
	 * @access public
	 * @return array $transactions
	 */
	public static function get_failed_recurring(): array {
		$db = Database::get();
		$ids = $db->get_column('SELECT id FROM transaction WHERE recurring = 1 AND (failed = 1 OR completed = 1);', []);

		return self::get_by_ids($ids);
	}
}
