<?php

namespace app\clients;

use co;
use Swoole\Coroutine;
use Swoole\Coroutine\Channel;
use Symfony\Component\DomCrawler\Crawler;
use function Co\run;
use function go;
use const PHP_EOL;

class Swoole
{
    private int $concurrency;

    private int $batchSize;

    private string $urlPath;

    private string $tempDir;

    private Channel $urlChannel;

    public function __construct(int $concurrency, int $batchSize, string $urlPath, string $tempDir)
    {
        $this->concurrency = $concurrency;
        $this->batchSize = $batchSize;
        $this->urlPath = $urlPath;
        $this->tempDir = $tempDir;
    }

    public function run():void
    {
        $this->urlChannel = new Channel($this->batchSize);
        $this->readUrls();
        run(function() {
            while (!$this->urlChannel->isEmpty()) {
                $url = $this->urlChannel->pop();
                go(function() use ($url){
                    Co::sleep(.1);
                    echo $url.PHP_EOL;
                });
                Coroutine::defer(function() use ($url) {
                    Co::sleep(.1);
                    echo 'defer process ' . $url;
                });
            }
            $this->urlChannel->close();
        });
        echo 'close ' . PHP_EOL;
    }

    private function readUrls()
    {
        $fp = fopen($this->urlPath, "r");
        run(function() use ($fp) {
            for ($i = 0; $i < $this->batchSize; $i++) {
                echo 'read #' . $i . PHP_EOL;
                $this->urlChannel->push(Co::fgets($fp));
            }
        });
    }

    private function processHtml($html, $url)
    {
        $crawler = new Crawler($html);
        $title = $crawler->filterXPath('//title')->text("No title");
        echo $title . PHP_EOL;
    }

}