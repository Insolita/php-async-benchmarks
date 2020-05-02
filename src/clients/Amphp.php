<?php

namespace app\clients;


use Amp\File\File;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Interceptor\SetRequestTimeout;
use Amp\Http\Client\Request;
use Amp\LazyPromise;
use Amp\Loop;
use Amp\Promise;
use Amp\TimeoutCancellationToken;
use Symfony\Component\DomCrawler\Crawler;
use function Amp\call;
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
    private \SplQueue $queue;

    public function __construct(int $concurrency, int $batchSize, string $urlPath, string $tempDir)
    {
        $this->concurrency = $concurrency;
        $this->batchSize = $batchSize;
        $this->urlPath = $urlPath;
        $this->tempDir = $tempDir;
        //$this->client = HttpClientBuilder::buildDefault();
        $this->client = (new HttpClientBuilder())->intercept(new SetRequestTimeout(5000, 10000, 30000))
                                                 ->followRedirects(0)
                                                 ->build();

        $this->fs    = filesystem();
        $this->queue = new \SplQueue();
    }

    public function run()
    {
        Loop::run(function () {
            yield $this->initUrls();
            yield $this->processRequests();
        });
    }

    private function initUrls()
    {

//        foreach ($this->urlGenerator() as $url){
//            $this->urls[] = $url;
//        }
        $data = yield $this->fs->get($this->urlPath);
        $urls = \array_slice(\explode(PHP_EOL, $data), 0, $this->batchSize);
        foreach ($urls as $url) {
            $this->queue->enqueue($url);
        }
        unset($data);
    }

    private function processRequests()
    {
        $maxPoolSize = 10;
        /** @var Promise[] $pool */
        $pool = [];
        while (!$this->queue->isEmpty()) {
            if (count($pool) < $maxPoolSize) {
                // fill pool with work
                $promise = $this->processRequest($this->queue->pop());

                $promise->onResolve(function () use (&$pool) {
                    unset($pool[array_search($this, $pool, true)]);
                });
                $pool[] = $promise;
                continue;
            }
            // wait when some task will be accomplished
            [$errors, $values] = yield Promise\some($pool);
        }
    }

    private function processRequest($url)
    {
        return call(function () use ($url) {
            try {
                $response = yield $this->client->request(new Request($url));
                $body     = yield $response->getBody()->buffer();
                yield call(fn() => $this->processHtml($body, $url));
            } catch (\Throwable $e) {
                $this->fs->open($this->tempDir . '/bad.txt', 'a')
                    ->onResolve(function ($err, File $file) use ($url) {
                        $file->end("$url" . PHP_EOL);
                    });
            }
        });
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
