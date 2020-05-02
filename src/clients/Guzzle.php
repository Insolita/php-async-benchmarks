<?php

namespace app\clients;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\DomCrawler\Crawler;
use const FILE_APPEND;
use const PHP_EOL;

class Guzzle
{
    private int $concurrency;
    private int $batchSize;
    private string $urlPath;
    private string $tempDir;

    /**
     * @var \GuzzleHttp\Client
     */
    private Client $client;
    public function __construct(int $concurrency, int $batchSize, string $urlPath, string $tempDir)
    {
        $this->concurrency = $concurrency;
        $this->batchSize = $batchSize;
        $this->urlPath = $urlPath;
        $this->tempDir = $tempDir;
        $this->client = $this->createClient();
    }

    protected function createClient():Client
    {
        return new Client(['connect_timeout' => 5, 'timeout'=>30]);
    }

    public function run()
    {
        $pool = new Pool($this->client, $this->requestGenerator(), [
            'concurrency' => $this->concurrency,
            'fulfilled' => function (Response $response, $index) {
                $body = $response->getBody()->getContents();
                $crawler = new Crawler($body);
                $title = $crawler->filterXPath('//title')->text("No title");
                \file_put_contents($this->tempDir."/ok.txt", "$index,$title".PHP_EOL, FILE_APPEND);
            },
            'rejected' => function (RequestException $reason, $index) {
                \file_put_contents($this->tempDir.'/bad.txt',$index.PHP_EOL, FILE_APPEND);
            },
        ]);
        $promise = $pool->promise();
        $promise->wait();
    }

    private function requestGenerator()
    {
        $f = fopen($this->urlPath, 'r');
        try {
            $num = 0;
            while (($line = fgets($f)) && $num < $this->batchSize) {
                $num++;
                $url = \trim($line);
                yield $url => new Request('GET', $url);
            }
        } finally {
            fclose($f);
        }
    }
}