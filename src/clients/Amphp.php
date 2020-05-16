<?php

namespace app\clients;

use Amp\ByteStream\LineReader;
use Amp\File;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Interceptor\SetRequestTimeout;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Iterator;
use Amp\Producer;
use Amp\Promise;
use Amp\Sync\LocalSemaphore;
use Amp\Sync\Lock;
use Symfony\Component\DomCrawler\Crawler;
use function Amp\asyncCall;
use function Amp\call;
use function Amp\Promise\wait;

class Amphp
{
    private int $concurrency;
    private int $batchSize;

    private string $urlPath;
    private string $tempDir;

    private HttpClient $client;

    private File\File $goodFile;
    private File\File $badFile;

    public function __construct(int $concurrency, int $batchSize, string $urlPath, string $tempDir)
    {
        $this->concurrency = $concurrency;
        $this->batchSize = $batchSize;
        $this->urlPath = $urlPath;
        $this->tempDir = $tempDir;

        $this->client = (new HttpClientBuilder())
            ->intercept(new SetRequestTimeout(5000, 10000, 30000))
            ->followRedirects(0)
            ->retry(0)
            ->build();
    }

    public function run(): void
    {
        wait($this->processRequests($this->readUrls()));
    }

    private function readUrls(): Iterator
    {
        return new Producer(function (callable $emit) {
            /** @var File\File $fileHandle */
            $fileHandle = yield File\open($this->urlPath, 'r');
            $lineReader = new LineReader($fileHandle);

            try {
                $num = 0;
                while (($line = yield $lineReader->readLine()) && $num < $this->batchSize) {
                    $num++;

                    yield $emit(trim($line));
                }
            } finally {
                yield $fileHandle->close();
            }
        });
    }

    private function processRequests(Iterator $urls): Promise
    {
        return call(function () use ($urls) {
            $this->goodFile = yield File\open($this->tempDir . '/ok.txt', 'a');
            $this->badFile = yield File\open($this->tempDir . '/bad.txt', 'a');

            try {
                $semaphore = new LocalSemaphore($this->concurrency);

                while (yield $urls->advance()) {
                    $url = $urls->getCurrent();

                    /** @var Lock $lock */
                    $lock = yield $semaphore->acquire();

                    asyncCall(function () use ($url, $lock) {
                        try {
                            /** @var Response $response */
                            $response = yield $this->client->request(new Request($url));
                            yield $this->processHtml(yield $response->getBody()->buffer(), $url);
                        } catch (\Throwable $e) {
                            yield $this->badFile->write($url . PHP_EOL);
                        } finally {
                            $lock->release();
                        }
                    });
                }
            } finally {
                $locks = [];

                // Acquire all locks to ensure all requests finished
                for ($i = 0; $i < $this->concurrency; $i++) {
                    $locks[] = yield $semaphore->acquire();
                }

                yield $this->goodFile->close();
                yield $this->badFile->close();
            }
        });
    }

    private function processHtml(string $html, string $url): Promise
    {
        return call(function () use ($html, $url) {
            $crawler = new Crawler($html);
            $title = $crawler->filterXPath('//title')->text('No title');

            yield $this->goodFile->write($url . ',' . $title . PHP_EOL);
        });
    }
}
