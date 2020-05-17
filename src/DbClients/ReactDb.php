<?php

namespace app\DbClients;

use Clue\React\Buzz\Browser;
use Clue\React\Mq\Queue;
use Evenement\EventEmitter;
use PgAsync\Client;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\LoopInterface;
use React\Filesystem\Filesystem;
use React\Filesystem\FilesystemInterface;
use Rx\Observer\CallbackObserver;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Link;
use function array_filter;
use function array_map;
use function array_slice;
use function explode;
use function print_r;
use function strpos;
use function urldecode;
use const PHP_EOL;

class ReactDb extends EventEmitter
{
    private int $batchSize;

    private string $urlPath;

    private $db;

    private array $urls;
    private Browser $browser;

    private FilesystemInterface $fs;
    private LoopInterface $loop;
    private int $processed = 0;

    public function __construct(int $batchSize, string $urlPath, array $dbconf, LoopInterface $loop)
    {
        $this->batchSize = $batchSize;
        $this->urlPath = $urlPath;
        $this->browser = new Browser($loop);
        $this->fs = Filesystem::create($loop);
        $this->db = new Client([
            "host" => $dbconf['host'],
            "user" => $dbconf['user'],
            "password" => $dbconf['pass'],
            "database" => $dbconf['dbname'],
            "auto_disconnect" => true,
            "max_connections" => 200,
        ], $loop);
        $this->loop = $loop;
    }

    public function run()
    {
        $getUrls = $this->fs->file($this->urlPath)->getContents()->then(function($contents) {
            $this->urls = array_slice(explode(PHP_EOL, $contents), 0, $this->batchSize);
        });
        $queue = new Queue(25, null, function($url) {
            return $this->browser->withOptions([
                'timeout' => 5,
                'obeySuccessCode' => false,
            ])->get($url);
        });
        $getUrls->then(function() use ($queue) {
            foreach ($this->urls as $url) {
                if (empty($url)) {
                    continue;
                }
                $queue($url)
                    ->then(function(ResponseInterface $response) use ($url) {
                        $status = $response->getStatusCode();
                        $this->processHtml((string)$response->getBody(), $url, $status);
                    },
                        function() use ($url) {
                            $this->dbWrite($url, 999, '', []);
                            $this->emit('processed');
                        });
            }
        });
        $this->on('processed', function() use ($queue) {
            ++$this->processed;
        });
    }

    private function processHtml(string $html, string $url, int $status)
    {
        $crawler = new Crawler($html, $url);
        $title = $crawler->filterXPath('//title')->text("No title");
        $links = $crawler->filter('a')->links();
        $links = array_map(fn(Link $link) => urldecode($link->getUri()), $links);
        $links = array_filter($links, fn($link) => strpos($link, 'https') !== false);
        $this->loop->futureTick(fn() => $this->dbWrite($url, $status, $title, $links));
        $this->emit('processed');
    }

    private function dbWrite(string $url, int $status, string $title, array $links)
    {
        $observer = new CallbackObserver(function($row) use ($links) {
            if (empty($row['id'])) {
                return;
            }
            $sql = 'INSERT INTO react_links (url_id, link) VALUES($1, $2)';
            $onError = new CallbackObserver();
            array_map(fn($link) => $this->db->executeStatement($sql, [$row['id'], $link])->subscribe($onError), $links);
        }, fn($e) => print_r(["$url insert failed", $e]));

        $this->db->executeStatement(
            'INSERT INTO react_urls (url, title, status) VALUES($1, $2, $3)',
            [$url, $title, $status]
        )->subscribe(new CallbackObserver(null, fn($e) => print_r(["$url insert failed", $e])));
        $this->db->executeStatement('SELECT id from react_urls WHERE url = $1', [$url])->subscribe($observer);
    }
}