<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MongoDBTransactionFix extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:transaction
    {--rollback : Rollback the transaction function}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * File path for MongoDB Connection
     *
     * @var string
     */
    protected $connection_path = 'vendor/jenssegers/mongodb/src/Connection.php';

    /**
     * Import to be searched and appended
     *
     * @var string
     */
    protected $search_import = 'use MongoDB\Client;';

    /**
     * Function to be searched and appended
     * @var string
     */
    protected $search_function = 'public function __call($method, $parameters)
    {
        return call_user_func_array([$this->db, $method], $parameters);
    }';

    /**
     * Import to be appended
     *
     * @var string
     */
    protected $insert_import = 'use MongoDB\Driver\ReadConcern;
use MongoDB\Driver\ReadPreference;
use MongoDB\Driver\WriteConcern;';

    /**
     * Function to be appended
     *
     * @var string
     */
    protected $insert_function = '/**
     * create a session and start a transaction in session
     *
     * In version 4.0, MongoDB supports multi-document transactions on replica sets.
     * In version 4.2, MongoDB introduces distributed transactions, which adds support for multi-document transactions on sharded clusters and incorporates the existing support for multi-document transactions on replica sets.
     * To use transactions on MongoDB 4.2 deployments(replica sets and sharded clusters), clients must use MongoDB drivers updated for MongoDB 4.2.
     *
     * @see https://docs.mongodb.com/manual/core/transactions/
     */
    public function beginTransaction()
    {
        $this->session_key = uniqid();
        $this->sessions[$this->session_key] = $this->connection->startSession();

        $this->sessions[$this->session_key]->startTransaction([
            \'readPreference\' => new ReadPreference(ReadPreference::RP_PRIMARY),
            \'writeConcern\' => new WriteConcern(1),
            \'readConcern\' => new ReadConcern(ReadConcern::LOCAL)
        ]);
    }

    /**
     * commit transaction in this session and close this session
     */
    public function commit()
    {
        if ($session = $this->getSession()) {
            $session->commitTransaction();
            $this->setLastSession();
        }
    }

    /**
     * rollback transaction in this session and close this session
     */
    public function rollBack($toLevel = null)
    {
        if ($session = $this->getSession()) {
            $session->abortTransaction();
            $this->setLastSession();
        }
    }

    /**
     * close this session and get last session key to session_key
     * Why do it ? Because nested transactions
     */
    protected function setLastSession()
    {
        if ($session = $this->getSession()) {
            $session->endSession();
            unset($this->sessions[$this->session_key]);
            if (empty($this->sessions)) {
                $this->session_key = null;
            } else {
                end($this->sessions);
                $this->session_key = key($this->sessions);
            }
        }
    }

    /**
     * get now session if it has session
     * @return \MongoDB\Driver\Session|null
     */
    public function getSession()
    {
        return $this->sessions[$this->session_key] ?? null;
    }';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if (!$this->option('rollback'))
        {
            $this->appendTransactionFunction();
            $this->info("MongoDB transaction function added");
        } else {

            $this->detachTransactionFunction();
            $this->info("MongoDB transaction function rolled back");
        }
    }

    /**
     * Appends the following codes to your Connection.php
     */
    public function appendTransactionFunction() {
        $append_import = $this->search_import . "\n" . $this->insert_import;
        $append_function = $this->search_function . "\n\n" . $this->insert_function;

        file_put_contents(
            $this->connection_path,
            str_replace(
                $this->search_import,
                $append_import,
                file_get_contents($this->connection_path)
            )
        );

        file_put_contents(
            $this->connection_path,
            str_replace(
                $this->search_function,
                $append_function,
                file_get_contents($this->connection_path)
            )
        );
    }

    /**
     * Reverts the following codes from your Connection.php
     */
    public function detachTransactionFunction() {
        $append_import = $this->search_import . "\n" . $this->insert_import;
        $append_function = $this->search_function . "\n\n" . $this->insert_function;

        file_put_contents(
            $this->connection_path,
            str_replace(
                $append_import,
                $this->search_import,
                file_get_contents($this->connection_path)
            )
        );

        file_put_contents(
            $this->connection_path,
            str_replace(
                $append_function,
                $this->search_function,
                file_get_contents($this->connection_path)
            )
        );
    }
}
