<?php

namespace app\clients;


use Amp\File\File;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\LazyPromise;
use Amp\TimeoutCancellationToken;
use Symfony\Component\DomCrawler\Crawler;
use function Amp\File\filesystem;
use function Amp\Promise\all;
use function Amp\Promise\any;
use function Amp\Promise\some;
use function Amp\Promise\wait;
use const PHP_EOL;

class Amphp
{
    private int $concurrency;
    private int $batchSize;
    private string $urlPath;
    private string $tempDir;

    private \Amp\Http\Client\HttpClient $client;
    private \Amp\File\Driver $fs;
    private array $urls;

    public function __construct(int $concurrency, int $batchSize, string $urlPath, string $tempDir)
    {
        $this->concurrency = $concurrency;
        $this->batchSize = $batchSize;
        $this->urlPath = $urlPath;
        $this->tempDir = $tempDir;
        $this->client = HttpClientBuilder::buildDefault();
        $this->fs = filesystem();
    }

    public function run()
    {
        $promise = \Amp\call(fn() => $this->initUrls());
        wait($promise);
        $promise = \Amp\call(fn() => $this->processRequestsConcurrent());
        wait($promise);
    }

    private function initUrls()
    {

//        foreach ($this->urlGenerator() as $url){
//            $this->urls[] = $url;
//        }
        $data = yield $this->fs->get($this->urlPath);
        $this->urls = \array_slice(\explode(PHP_EOL, $data), 0, $this->batchSize);
        $this->urls = \array_chunk($this->urls, $this->concurrency);
        unset($data);
    }

    private function processRequests()
    {
        foreach ($this->urls as $chunk){
            foreach ($chunk as $url){
                try{
                    $response = yield $this->client->request(new Request($url));
                    $body = yield $response->getBody()->buffer();
                    yield \Amp\call(fn() => $this->processHtml($body, $url));
                }catch (\Throwable $e){
                    $this->fs->open($this->tempDir . '/bad.txt', 'a')
                             ->onResolve(function($err, File $file) use($url) {
                                 $file->end("$url" . PHP_EOL);
                             });
                }
            }
        }
    }
    private function processRequestsConcurrent()
    {
        foreach ($this->urls as $chunk){
            $promises = [];
            foreach ($chunk as $url){
                $promises[$url] = \Amp\call(function () use ($url) {
                    try{
                        $response = yield $this->client->request(new Request($url));
                        $body = yield $response->getBody()->buffer();
                        return \Amp\call(fn() => $this->processHtml($body, $url));
                    }catch (\Throwable $e){
                       $this->fs->open($this->tempDir . '/bad.txt', 'a')
                                 ->onResolve(function($err, File $file) use($url) {
                                      $file->end("$url" . PHP_EOL);
                                 });
                    }
                });
            }
            wait(some($promises));
        }
    }

    private function processHtml(string $html, string $url)
    {
        $crawler = new Crawler($html);
        $title = $crawler->filterXPath('//title')->text("No title");
        $this->fs->open($this->tempDir . '/ok.txt', 'a')->onResolve(function($err, File $file) use($url, $title) {
            $file->end("$url,$title" . PHP_EOL);
        });
    }

    private function urlGenerator()
    {
        $f = fopen($this->urlPath, 'r');
        try {
            $num = 0;
            while (($line = fgets($f)) && $num < $this->batchSize) {
                $num++;
                yield \trim($line);
            }
        } finally {
            fclose($f);
        }
    }
}