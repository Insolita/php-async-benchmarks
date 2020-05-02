<?php

namespace app;

use Amp\Loop;
use app\clients\Amphp;
use app\clients\Guzzle;
use app\clients\Reactphp;
use BadMethodCallException;
use function rmdir;
use function unlink;
use const PHP_EOL;

class Bench
{
    private const URL_PATH = __DIR__ . '/urls.csv';
    private const TEMP_DIR = __DIR__ . '/temp/';

    private string $clientName;

    private int $concurrency;

    private int $batchSize;

    private int $iterations;

    private string $tempDir;

    private $startTime;
    private $finishTime;
    private array $deltas = [];

    public function __construct(string $clientName, int $iterations, int $concurrency, int $batchSize)
    {

        $this->clientName = $clientName;
        $this->concurrency = $concurrency;
        $this->batchSize = $batchSize;
        $this->iterations = $iterations;
        $this->tempDir = self::TEMP_DIR . $this->clientName;
    }

    public function run()
    {
        for ($i = 0; $i < $this->iterations; $i++) {
            $this->clearTempData();
            $start = microtime(true);
            $this->runClient();
            $delta = microtime(true) - $start;
            echo $i. ' - '. $delta.PHP_EOL;
            $this->deltas[] = $delta;
        }
        $this->printResult();
    }

    private function runClient()
    {
        switch ($this->clientName){
            case 'amp':
                Loop::run(
                    fn() => (new Amphp($this->concurrency, $this->batchSize, self::URL_PATH, $this->tempDir))->run()
                );
                break;
            case 'react':
                $loop = \React\EventLoop\Factory::create();
                $client = new Reactphp($this->concurrency, $this->batchSize, self::URL_PATH, $this->tempDir, $loop);
                $client->run();
                $loop->run();
                $loop->stop();
                unset($client, $loop);
                break;
            case 'guzzle':
                $client = new Guzzle($this->concurrency, $this->batchSize, self::URL_PATH, $this->tempDir);
                $client->run();
                unset($client);
                break;
            default:
                throw new BadMethodCallException('Not supported client');
        }
    }

    private function prepareTempDir()
    {
        if (\file_exists($this->tempDir)) {
            rmdir($this->tempDir);
        }
        mkdir($this->tempDir);
    }

    private function clearTempData()
    {
        @unlink($this->tempDir . '/ok.txt');
        @unlink($this->tempDir . '/bad.txt');
        $this->prepareTempDir();
    }

    private function printResult()
    {
        $result = [
            'client'=>$this->clientName,
            'concurrency'=>$this->concurrency,
            'batchSize'=>$this->batchSize,
            'iterations'=>$this->iterations,
            'max'=>max($this->deltas),
            'min'=>min($this->deltas),
            'avg'=>round(array_sum($this->deltas)/$this->iterations, 6)
        ];

        echo print_r($result, true).PHP_EOL;
    }
}