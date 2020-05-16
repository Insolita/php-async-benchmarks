<?php

namespace app\clients;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PDO;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Link;

class GuzzleDb
{
    private int $batchSize;

    private string $urlPath;

    private PDO $db;

    /**
     * @var Client
     */
    private Client $client;

    public function __construct(int $batchSize, string $urlPath, array $dbconf)
    {
        $this->batchSize = $batchSize;
        $this->urlPath = $urlPath;
        $this->db = new PDO($dbconf['dsn'], $dbconf['user'], $dbconf['pass']);
        $this->client = new Client(['connect_timeout' => 5, 'timeout' => 30]);
    }

    public function run()
    {
        $pool = new Pool($this->client, $this->requestGenerator(), [
            'fulfilled' => function (Response $response, $index) {
                $body = $response->getBody()->getContents();
                $crawler = new Crawler($body, $index);
                $title = $crawler->filterXPath('//title')->text('No title');
                $links = $crawler->filter('a')->links();
                $links = array_map(fn (Link $link) => urldecode($link->getUri()), $links);
                $links = array_filter($links, fn ($link) => strpos($link, 'https') !== false);
                $status = $response->getStatusCode();
                $this->dbWrite($index, $status, $title, $links);
            },
            'rejected' => function (RequestException $reason, $index) {
                $this->dbWrite($index, $reason->getCode(), $reason->getMessage(), []);
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
                $url = trim($line);
                yield $url => new Request('GET', $url);
            }
        } finally {
            fclose($f);
        }
    }

    private function dbWrite(string $url, int $status, string $title, array $links)
    {
        $this->db->prepare('INSERT INTO guzzle_urls (url, title, status) VALUES (?, ?, ?)')
            ->execute([$url, $title, $status]);
        if (count($links)) {
            $id = $this->db->lastInsertId();
            foreach ($links as $link) {
                $this->db->prepare('INSERT INTO guzzle_links (url_id, link) VALUES (?, ?)')
                    ->execute([$id, $link]);
            }
        }
    }
}
