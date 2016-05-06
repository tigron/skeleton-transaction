<?php
/**
 * Database migration class
 *
 * @author Gerry Demaret <gerry@tigron.be>
 * @author Christophe Gosiau <christophe@tigron.be>
 * @author David Vandemaele <david@tigron.be>
 */
namespace Skeleton\Transaction;

use \Skeleton\Database\Database;

class Migration_20160504_142001_Init extends \Skeleton\Database\Migration {

	/**
	 * Migrate up
	 *
	 * @access public
	 */
	public function up() {
		$db = Database::get();
		$db->query("
			CREATE TABLE IF NOT EXISTS `transaction` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`classname` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
				`created` datetime NOT NULL,
				`scheduled_at` datetime NOT NULL,
				`executed_at` datetime NOT NULL,
				`data` text COLLATE utf8_unicode_ci NOT NULL,
				`completed` tinyint(4) NOT NULL,
				`failed` tinyint(4) NOT NULL,
				`locked` tinyint(4) NOT NULL,
				`frozen` tinyint(4) NOT NULL,
				PRIMARY KEY (`id`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
		");

		$db->query("
			CREATE TABLE IF NOT EXISTS `transaction_log` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`transaction_id` int(11) NOT NULL,
				`created` datetime NOT NULL,
				`output` longtext COLLATE utf8_unicode_ci NOT NULL,
				`failed` tinyint(4) NOT NULL,
				`exception` longtext COLLATE utf8_unicode_ci NOT NULL,
				PRIMARY KEY (`id`),
				KEY `transaction_id` (`transaction_id`),
				KEY `created` (`created`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
		");
	}

	/**
	 * Migrate down
	 *
	 * @access public
	 */
	public function down() {

	}
}
