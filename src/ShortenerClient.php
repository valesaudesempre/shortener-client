<?php

namespace ValeSaude\ShortenerClient;

use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\RequestOptions;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use ValeSaude\ShortenerClient\Exceptions\ShortenerClientException;

class ShortenerClient
{
    /** @var Client */
    private $client;

    /** @var CacheRepository */
    private $cache;

    public function __construct(
        string $baseUri,
        string $username,
        string $password,
        CacheRepository $cache,
        ?HandlerStack $handlerStack = null
    ) {
        if (!$handlerStack) {
            $handlerStack = HandlerStack::create(new CurlHandler());
        }

        $this->cache = $cache;
        $this->client = new Client([
            'base_uri' => $baseUri,
            'handler' => $handlerStack,
            RequestOptions::AUTH => [$username, $password],
            RequestOptions::TIMEOUT => 10,
        ]);
    }

    /**
     * @throws ShortenerClientException
     */
    public function shorten(string $url, bool $useCache = true): string
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw ShortenerClientException::invalidURL($url);
        }

        if ($useCache && $this->hasCache($url)) {
            return $this->getCache($url);
        }

        try {
            $response = $this->client->post(
                'api/v1/shorten',
                [RequestOptions::JSON => ['long_url' => $url]],
            );
            $data = json_decode($response->getBody(), true);
            $shortUrl = $data['short_url'];

            $this->putCache($url, $shortUrl);

            return $shortUrl;
        } catch (GuzzleException $e) {
            $this->handleErrorResponse($e);
        }
    }

    private function handleErrorResponse(GuzzleException $e): void
    {
        if (!$e instanceof RequestException) {
            throw ShortenerClientException::unexpectedResponse();
        }

        if (!$e->hasResponse()) {
            throw ShortenerClientException::unexpectedResponse();
        }

        $response = $e->getResponse();

        if (401 === $response->getStatusCode()) {
            throw ShortenerClientException::authenticationFailed();
        }

        $data = json_decode($response->getBody(), true);

        if (!isset($data['message'])) {
            throw ShortenerClientException::unexpectedResponse();
        }

        throw ShortenerClientException::apiError($data['message']);
    }

    private function getCacheKey(string $url): string
    {
        return 'valesaude.shortener-client.url.'.md5($url);
    }

    private function hasCache(string $url): bool
    {
        return $this->cache->has($this->getCacheKey($url));
    }

    private function getCache(string $url): string
    {
        return $this->cache->get($this->getCacheKey($url));
    }

    private function putCache(string $url, string $shortUrl): void
    {
        $this->cache->put($this->getCacheKey($url), $shortUrl, new DateTimeImmutable('+1 day'));
    }
}