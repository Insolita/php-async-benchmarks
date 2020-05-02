<?php

namespace app\clients;

use Clue\React\Buzz\Browser;
use Clue\React\Mq\Queue;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\LoopInterface;
use React\Filesystem\Filesystem;
use React\Stream\WritableStreamInterface;
use Symfony\Component\DomCrawler\Crawler;
use const PHP_EOL;

class Reactphp
{
    private int $batchSize;
    private int $concurrency;
    private string $urlPath;
    private string $tempDir;

    /**
     * @var \Clue\React\Buzz\Browser
     */
    private Browser $browser;

    /**
     * @var \React\Filesystem\FilesystemInterface
     */
    private $fs;

    private array $urls;

    /**
     * @var \React\EventLoop\LoopInterface
     */
    private LoopInterface $loop;

    public function __construct(int $concurrency, int $batchSize, string $urlPath, string $tempDir, LoopInterface $loop)
    {
        $this->concurrency = $concurrency;
        $this->batchSize = $batchSize;
        $this->urlPath = $urlPath;
        $this->tempDir = $tempDir;
        $this->browser = new Browser($loop);
        $this->fs = Filesystem::create($loop);
        $this->loop = $loop;
    }

    public function run()
    {
        $getUrls = $this->fs->file($this->urlPath)->getContents()->then(function($contents) {
            $this->urls = \array_slice(\explode(PHP_EOL, $contents), 0, $this->batchSize);
        });
        $queue = new Queue($this->concurrency, null, function($url) {
            return $this->browser->withOptions(['timeout' => 5])->get($url);
        });
        $getUrls->then(function() use ($queue) {
            foreach ($this->urls as $url) {
                $promise = $queue($url)
                    ->then(function(ResponseInterface $response) use ($url) {
                       // echo 'process url ' . $url . PHP_EOL;
                        $this->processHtml((string)$response->getBody(), $url);
                    },
                        function() use ($url) {
                            $this->loop->futureTick(function() use ($url){
                                $this->fs->file($this->tempDir . '/bad.txt')->open('a+')
                                         ->then(fn(WritableStreamInterface $stream) =>  $stream->end($url. PHP_EOL));
                            });
                        });
            }
        });
    }

    private function processHtml(string $html, string $url)
    {
        $crawler = new Crawler($html);
        $title = $crawler->filterXPath('//title')->text("No title");
        $this->fs->file($this->tempDir . '/ok.txt')->open('a+')
            ->then(fn(WritableStreamInterface $stream) => $stream->end("$url,$title" . PHP_EOL));

    }
}