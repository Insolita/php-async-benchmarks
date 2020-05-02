<?php

namespace app;

use app\clients\Guzzle;
use app\clients\GuzzleDb;
use app\clients\ReactDb;
use app\clients\Reactphp;
use BadMethodCallException;
use PDO;
use const PHP_EOL;

class BenchDb
{
    private const URL_PATH = __DIR__ . '/urls.csv';
    private string $clientName;
    private int $batchSize;
    private int $iterations;
    private array $dbconf;
    private array $deltas = [];

    private PDO $db;

    public function __construct(string $clientName, int $iterations, int $batchSize, array $dbconf)
    {
        $this->clientName = $clientName;
        $this->batchSize = $batchSize;
        $this->iterations = $iterations;
        $this->dbconf = $dbconf;
    }

    public function run()
    {
        $this->prepareDb();
        for ($i = 0; $i < $this->iterations; $i++) {
            $this->clearDbData();
            $start = microtime(true);
            $this->runClient();
            $end = microtime(true);
            $delta = $end - $start;
            echo $i. ' - '. $delta.PHP_EOL;
            $this->deltas[] = $delta;
        }
        $this->printResult();
    }

    private function prepareDb()
    {
        $this->db = new PDO($this->dbconf['dsn'], $this->dbconf['user'], $this->dbconf['pass']);
        $table1 = <<<SQL
CREATE TABLE IF NOT EXISTS "{$this->clientName}_urls" (
    id         bigserial,
    url        varchar(1024),
    status     varchar(10),
    title      text default NULL                                
);
SQL;
        $table2 = <<<SQL
CREATE TABLE IF NOT EXISTS "{$this->clientName}_links" (
    id         bigserial,
    url_id     bigint,
    link       text default ''                                  
);
SQL;

        $this->db->query($table1)->execute();
        $this->db->query($table2)->execute();
    }

    private function clearDbData()
    {
        $this->db->query("TRUNCATE {$this->clientName}_links RESTART IDENTITY")->execute();
        $this->db->query("TRUNCATE {$this->clientName}_urls RESTART IDENTITY")->execute();
    }

    private function runClient()
    {
        switch ($this->clientName){
            case 'react':
                $loop = \React\EventLoop\Factory::create();
                $client = new ReactDb($this->batchSize, self::URL_PATH, $this->dbconf, $loop);
                $client->run();
                $loop->run();
                $loop->stop();
                unset($client, $loop);
                break;
            case 'guzzle':
                $client = new GuzzleDb($this->batchSize, self::URL_PATH, $this->dbconf);
                $client->run();
                unset($client);
                break;
            default:
                throw new BadMethodCallException('Not supported client');
        }
    }

    private function printResult()
    {
        $result = [
            'client'=>$this->clientName,
            'batchSize'=>$this->batchSize,
            'iterations'=>$this->iterations,
            'max'=>max($this->deltas),
            'min'=>min($this->deltas),
            'avg'=>round(array_sum($this->deltas)/$this->iterations, 4)
        ];

        echo print_r($result, true).PHP_EOL;
    }
}