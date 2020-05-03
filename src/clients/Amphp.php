<?php

namespace app\clients;


use Amp\File\Driver;
use Amp\File\File;
use Amp\Http\Client\HttpClient;
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

    private HttpClient $client;
    private Driver $fs;
    private \SplQueue $queue;

    public function __construct(int $concurrency, int $batchSize, string $urlPath, string $tempDir)
    {
        $this->concurrency = $concurrency;
        $this->batchSize = $batchSize;
        $this->urlPath = $urlPath;
        $this->tempDir = $tempDir;
        //$this->client = HttpClientBuilder::buildDefault();
        $this->client = (new HttpClientBuilder())->intercept(new SetRequestTimeout(5000, 10000, 30000))
                                                 ->followRedirects(0)->retry(0)
                                                 ->build();

        $this->fs    = filesystem();
        $this->queue = new \SplQueue();
    }

    public function run()
    {
        Loop::run(function () {
            yield from $this->initUrls();
            yield from $this->processRequests();
        });
    }

    private function initUrls()
    {
        $data = yield $this->fs->get($this->urlPath);
        $urls = \array_slice(\explode(PHP_EOL, $data), 0, $this->batchSize);
        foreach ($urls as $url) {
            $this->queue->enqueue($url);
        }
        unset($data);
    }

    private function processRequests()
    {
        /** @var Promise[] $pool */
        $pool = [];
        while (!$this->queue->isEmpty()) {
            if (count($pool) < $this->concurrency) {
                // Fill whole pool with work
                $promise = $this->processRequest($this->queue->dequeue());
                // Remove promise from queue when resolved
                $promise->onResolve(static function () use (&$pool, $promise) {
                    // We should not yield here, because we want to await all promises at once.
                    // Also, it is important to determine which promise has been resolved, which is impossible
                    // when await combined promises. onResolve helps to determine it.
                    unset($pool[array_search($promise, $pool, true)]);
                });

                $pool[] = $promise;
                continue;
            }
            // Wait when at least one task will be accomplished
            yield Promise\first($pool);
        }
    }

    private function processRequest(string $url)
    {
        return call(function () use ($url) {
            try {
                $response = yield $this->client->request(new Request($url));
                $body     = yield $response->getBody()->buffer();
                yield from $this->processHtml($body, $url);
            } catch (\Throwable $e) {
                $file = yield $this->fs->open($this->tempDir . '/bad.txt', 'a');
                assert($file instanceof File);
                $file->end($url . PHP_EOL);
            }
        });
    }

    private function processHtml(string $html, string $url)
    {
        $crawler = new Crawler($html);
        $title = $crawler->filterXPath('//title')->text("No title");
        $file = yield $this->fs->open($this->tempDir . '/ok.txt', 'a');
        assert($file instanceof File);
        yield $file->end("$url,$title" . PHP_EOL);
    }
}
