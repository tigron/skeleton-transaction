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
      `type` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
      `created` datetime NOT NULL,
      `running_date` datetime NOT NULL,
      `frozen` tinyint(4) NOT NULL DEFAULT '0',
      `data` text COLLATE utf8_unicode_ci NOT NULL,
      `completed` tinyint(4) NOT NULL DEFAULT '0',
      `failed` tinyint(4) NOT NULL,
      `failed_date` datetime NOT NULL,
      `exception` text COLLATE utf8_unicode_ci NOT NULL,
      PRIMARY KEY (`id`),
      KEY `type` (`type`),
      KEY `running_date` (`running_date`),
      KEY `completed` (`completed`),
      KEY `frozen` (`frozen`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
