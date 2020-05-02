<?php

namespace app\clients;

use Clue\React\Buzz\Browser;
use Clue\React\Mq\Queue;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\LoopInterface;
use React\Filesystem\Filesystem;
use Rx\Observer\CallbackObserver;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Link;
use function array_map;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;
use const PHP_EOL;

class ReactDb
{
    private int $batchSize;
    private string $urlPath;
    private  $db;
    private array $urls;
    /**
     * @var \Clue\React\Buzz\Browser
     */
    private Browser $browser;

    private \React\Filesystem\FilesystemInterface $fs;

    /**
     * @var \React\EventLoop\LoopInterface
     */
    private LoopInterface $loop;

    public function __construct(int $batchSize, string $urlPath, array $dbconf, LoopInterface $loop)
    {
        $this->batchSize = $batchSize;
        $this->urlPath = $urlPath;
        $this->browser = new Browser($loop);
        $this->fs = Filesystem::create($loop);
        $this->db = new \PgAsync\Client([
            "host"     => $dbconf['host'],
            "user"     => $dbconf['user'],
            "password"     => $dbconf['pass'],
            "database" => $dbconf['dbname'],
            "auto_disconnect" =>true,
            "max_connections"=>200
        ], $loop);
        $this->loop = $loop;
    }

    public function run()
    {
        $getUrls = $this->fs->file($this->urlPath)->getContents()->then(function($contents) {
            $this->urls = \array_slice(\explode(PHP_EOL, $contents), 0, $this->batchSize);
        });
        $queue = new Queue(25, null, function($url) {
            return $this->browser->withOptions(['timeout' => 5])->get($url);
        });
        $getUrls->then(function() use ($queue) {
            foreach ($this->urls as $url) {
                if(empty($url)){
                    continue;
                }
                $promise = $queue($url)
                    ->then(function(ResponseInterface $response) use ($url) {
                        $status = $response->getStatusCode();
                        $this->loop->futureTick(fn()=>$this->processHtml((string)$response->getBody(), $url, $status));
                    },
                        function() use ($url) {
                            $this->loop->futureTick(fn()=>$this->dbWrite($url, 999,'', []));
                        });
            }
        });
    }

    private function processHtml(string $html, string $url, int $status)
    {
        $crawler = new Crawler($html, $url);
        $title = $crawler->filterXPath('//title')->text("No title");
        $links = $crawler->filter('a')->links();
        $links = array_map(fn(Link $link) => \urldecode($link->getUri()), $links);
        $links = \array_filter($links, fn($link)=>\strpos($link, 'https')!== false);
        $this->loop->futureTick(fn()=>$this->dbWrite($url,  $status, $title, $links));
    }

    private function dbWrite(string $url, int $status, string $title, array $links)
    {
        $observer = new CallbackObserver(function($row) use ($links){
            if(empty($row['id'])){
                return;
            }
            $sql = 'INSERT INTO react_links (url_id, link) VALUES($1, $2)';
            $onError = new CallbackObserver();
            array_map(fn($link) => $this->db->executeStatement($sql, [$row['id'], $link])->subscribe($onError),$links);
        }, fn($e) => \print_r(["$url insert failed", $e]));

        $this->db->executeStatement(
            'INSERT INTO react_urls (url, title, status) VALUES($1, $2, $3)',
            [$url, $title, $status]
        )->subscribe(new CallbackObserver(null,  fn($e) => \print_r(["$url insert failed", $e])));
        $this->db->executeStatement('SELECT id from react_urls WHERE url = $1', [$url])->subscribe($observer);
    }
}