# skeleton-transaction

## Description

Transactions for Skeleton. Transactions are used to perform background
tasks.

## Installation

Installation via composer:

    composer require tigron/skeleton-transaction

## Howto setup

Run the initial migrations

## Create transactions

Transactions should all extend from \Skeleton\Transaction\Transaction and should
implement the run() method:

    <?php
    /**
     * Transaction_Test
     *
     * @author Christophe Gosiau <christophe@tigron.be>
     */
    class Transaction_Test extends \Skeleton\Transaction\Transaction {

        /**
         * Run
         *
         * @access public
         */
        public function run() {
            // Do your thing
            $data = $this->get_data();
        }
    }

Schedule your transaction

    $transaction = new Transaction_Email_Order_Canceled();
    $data = [ 'some_data' => 'some_value ];
    $transaction->data = $data;
    $transaction->schedule();

## Manage the daemon

Start the transaction daemon with the skeleton binary:

    skeleton transaction:daemon start

Stop the transaction daemon

    skeleton transaction:daemon stop

Get the status of the daemon

    skeleton transaction:daemon status

## Interact with transactions

Get a list of all scheduled transactions

    skeleton transaction:list

Run a transaction

    skeleton transaction:run <transaction_id>

Show the log of a transaction

    skeleton transaction:log <transaction_id_or_classname>

## Monitor the daemon with Nagios

Skeleton Transaction Daemon can be monitored via its status file. The status
file is updated every 5 seconds and can be configured via Config:

    \Skeleton\Transaction\Config::$monitor_file = '/tmp/skeleton-transaction.status';

To monitor the daemon via Nagios, a \Skeleton\Core\Web\Module is provided which
will read the status file and return an appropiate response.

To enable Nagios monitoring, make sure to create a module in your application
that will handle the monitoring request:

    <?php
    /**
      * Module monitor
      *
      * @author Christophe Gosiau <christophe@tigron.be>
      */
    class Web_Module_Monitor extends \Skeleton\Transaction\Web\Module\Monitor {
    }

Optionally, an authentication header can be configured:

    \Skeleton\Transaction\Config::$monitor_authentication = 'YOUR_SECRET_STRING';

### Nagios configuration

In Nagios, you should configure a `command` to call the service. We will use the
built-in `check_http` command as a starting point:

    define command {
        command_name	check_skeleton_http
        command_line	/usr/lib/nagios/plugins/check_http -H $ARG1$ -u $ARG2$ -k 'X-Authentication: $ARG3$'
    }

Your service definition could then look like this:

    define service {
        use                             generic-service
        host_name                       hostname.example.com
        service_description             SKELETON
        check_command                   check_skeleton_http!app.hostname.example.com!/monitor!AuThEnTiCaTiOnStRiNg
    }
