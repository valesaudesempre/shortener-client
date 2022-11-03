<?php

namespace Tests;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Mockery\MockInterface;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Assert;
use ValeSaude\ShortenerClient\Exceptions\ShortenerClientException;
use ValeSaude\ShortenerClient\ShortenerClient;
use ValeSaude\ShortenerClient\ShortenerClientServiceProvider;

class ShortenerClientTest extends TestCase
{
    /** @var CacheRepository&MockInterface */
    private $cacheMock;

    /** @var MockHandler */
    private $guzzleMockHandler;

    /** @var ShortenerClient */
    private $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cacheMock = $this->createMock(CacheRepository::class);
        $this->guzzleMockHandler = new MockHandler();
        $this->sut = new ShortenerClient(
            'http://some.uri',
            'username',
            'password',
            $this->cacheMock,
            HandlerStack::create($this->guzzleMockHandler)
        );
    }

    protected function getPackageProviders($app): array
    {
        return [ShortenerClientServiceProvider::class];
    }

    public function test_shorten_method_throws_when_provided_url_parameter_is_not_a_valid_url(): void
    {
        // then
        $this->expectExceptionObject(ShortenerClientException::invalidURL('some-invalid-url'));

        // when
        $this->sut->shorten('some-invalid-url');
    }

    public function test_shorten_method_throws_when_connection_fails(): void
    {
        // given
        $this->guzzleMockHandler->append(
            function (Request $request) {
                throw new ConnectException('Some message', $request);
            }
        );

        // then
        $this->expectExceptionObject(ShortenerClientException::unexpectedResponse());

        // when
        $this->sut->shorten('http://long.url', false);
    }

    public function test_shorten_method_throws_when_api_returns_authentication_error(): void
    {
        // given
        $this->guzzleMockHandler->append(new Response(401));

        // then
        $this->expectExceptionObject(ShortenerClientException::authenticationFailed());

        // when
        $this->sut->shorten('http://long.url', false);
    }

    public function test_shorten_method_throws_when_api_returns_error_in_a_unexpected_format(): void
    {
        // given
        $this->guzzleMockHandler->append(new Response(400));

        // then
        $this->expectExceptionObject(ShortenerClientException::unexpectedResponse());

        // when
        $this->sut->shorten('http://long.url', false);
    }

    public function test_shorten_method_throws_when_api_returns_error_in_expected_format(): void
    {
        // given
        $this->guzzleMockHandler->append(new Response(400, [], '{"message": "Some error"}'));

        // then
        $this->expectExceptionObject(ShortenerClientException::apiError('Some error'));

        // when
        $this->sut->shorten('http://long.url', false);
    }

    public function test_shorten_method_returns_short_url_on_success(): void
    {
        // given
        $expectedShortURL = 'http://short.url';
        $this->guzzleMockHandler->append(
            static function (Request $request) use ($expectedShortURL) {
                Assert::assertEquals('http://some.uri/api/v1/shorten', (string) $request->getUri());
                Assert::assertEquals('POST', $request->getMethod());

                return new Response(200, [], "{\"short_url\": \"{$expectedShortURL}\"}");
            }
        );
        $this->cacheMock
            ->expects($this->once())
            ->method('put')
            ->with('valesaude.shortener-client.url.'.md5('http://long.url'), $expectedShortURL);

        // when
        $actualShortURL = $this->sut->shorten('http://long.url', false);

        // then
        $this->assertEquals($expectedShortURL, $actualShortURL);
    }

    public function test_shorten_method_returns_cached_short_url_when_use_cache_is_true_and_there_is_cache(): void
    {
        // given
        $expectedShortURL = 'http://short.url';
        $this->cacheMock
            ->expects($this->once())
            ->method('has')
            ->willReturn(true);
        $this->cacheMock
            ->expects($this->once())
            ->method('get')
            ->willReturn($expectedShortURL);

        // when
        $actualShortURL = $this->sut->shorten('http://long.url');

        // then
        $this->assertEquals($expectedShortURL, $actualShortURL);
    }
}
