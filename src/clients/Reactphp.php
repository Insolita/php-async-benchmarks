<?php

namespace app\clients;

use Clue\React\Buzz\Browser;
use Clue\React\Mq\Queue;
use Evenement\EventEmitter;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\LoopInterface;
use React\Filesystem\Filesystem;
use React\Filesystem\FilesystemInterface;
use React\Promise\PromiseInterface;
use React\Stream\WritableStreamInterface;
use Symfony\Component\DomCrawler\Crawler;

use function React\Promise\resolve;

class Reactphp extends EventEmitter
{
    private int $batchSize;

    private int $concurrency;

    private int $processed = 0;

    private string $urlPath;

    private string $tempDir;

    private array $urls;

    private Browser $browser;

    private FilesystemInterface $fs;

    private LoopInterface $loop;

    private PromiseInterface $badFile;

    private PromiseInterface $goodFile;

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
        $this->badFile = $this->fs->file($this->tempDir . '/bad.txt')->open('a');
        $this->goodFile = $this->fs->file($this->tempDir . '/ok.txt')->open('a');
        $getUrls = $this->fs->file($this->urlPath)->getContents()->then(function ($contents) {
            $this->urls = array_slice(explode(PHP_EOL, $contents), 0, $this->batchSize);
        });
        $queue = new Queue($this->concurrency, null, function ($url) {
            return $this->browser->withOptions(['timeout' => 5])->get($url);
        });

        $getUrls->then(function () use ($queue) {
            foreach ($this->urls as $url) {
                $queue($url)
                    ->then(function (ResponseInterface $response) use ($url) {
                        // echo 'process url ' . $url . PHP_EOL;
                        $this->processHtml((string)$response->getBody(), $url);
                    },
                        function () use ($url, $queue) {
                            $this->badFile->then(function (WritableStreamInterface $stream) use ($url) {
                                $stream->write($url . PHP_EOL);
                                $this->emit('processed');
                                return resolve($stream);
                            });
                        });
            }
        });
        $this->on('processed', function () use ($queue) {
            ++$this->processed;
            if ($this->processed === count($this->urls)) {
                $this->badFile->then(function (WritableStreamInterface $stream) {
                    $stream->close();
                    $this->badFile->close();
                });
                $this->goodFile->then(function (WritableStreamInterface $stream) {
                    $stream->close();
                    $this->goodFile->close();
                });
            }
        });
    }

    private function processHtml(string $html, string $url)
    {
        $crawler = new Crawler($html);
        $title = $crawler->filterXPath('//title')->text('No title');
        unset($crawler);
        $this->goodFile->then(function (WritableStreamInterface $stream) use ($url) {
            $stream->write($url . PHP_EOL);
            $this->emit('processed');
            return resolve($stream);
        });
    }
}
