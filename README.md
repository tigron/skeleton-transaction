# skeleton-transaction

## Description

Transactions for Skeleton. Transactions are used to perform background
tasks.

## Installation

Installation via composer:

    composer require tigron/skeleton-transaction

## Howto


    CREATE TABLE `transaction` (
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


    CREATE TABLE `transaction_log` (
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
