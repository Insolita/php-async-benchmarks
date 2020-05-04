<?php

namespace app\clients;

use Rx\Observable;
use Rx\React\FromFileObservable;
use Rx\React\Http;
use Rx\React\ToFileObserver;
use Symfony\Component\DomCrawler\Crawler;
use function Rx\await;
use const PHP_EOL;

class Rxphp
{
    private int $batchSize;

    private int $concurrency;

    private string $urlPath;

    private string $tempDir;

    public function __construct(int $concurrency, int $batchSize, string $urlPath, string $tempDir)
    {
//        $this->concurrency = $concurrency;  No easy way to do concurrency with Rx
        $this->batchSize   = $batchSize;
        $this->urlPath     = $urlPath;
        $this->tempDir     = $tempDir;
    }

    public function run()
    {
        $okLog  = new ToFileObserver($this->tempDir . '/ok.txt');
        $badLog = new ToFileObserver($this->tempDir . '/bad.txt');

        $toHtmlFromUrl = fn($url) => Http::get($url)
            ->timeout(5000)
            ->catch(function (\Throwable $e) use ($url, $badLog) {
                $badLog->onNext($url . '  ' . $e->getMessage() . PHP_EOL);
                return Observable::empty();
            })
            ->map(fn($body) => [$url, $body]);

        $toTitleFromHtml = function ($args) {
            [$url, $body] = $args;
            $crawler = new Crawler($body);
            $title   = $crawler->filterXPath('//title')->text("No title");
            return "$url,$title" . PHP_EOL;
        };

        $observable = (new FromFileObservable($this->urlPath))
            ->cut(PHP_EOL)
            ->take($this->batchSize)
            ->flatMap($toHtmlFromUrl)
            ->map($toTitleFromHtml);

        // Wait here until we get results
        $results= await($observable);

        foreach ($results as $item) {
            $okLog->onNext($item);
        }
    }
}