<?php

declare(strict_types=1);

namespace Tarampampam\GuzzleUrlMock\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions;
use Tarampampam\GuzzleUrlMock\UrlsMockHandler;

/**
 * @coversDefaultClass \Tarampampam\GuzzleUrlMock\UrlsMockHandler
 */
class UrlsMockHandlerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var UrlsMockHandler
     */
    protected $handler;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = new UrlsMockHandler;
    }

    /**
     * @return void
     */
    public function testConstants(): void
    {
        $this->assertSame('method', UrlsMockHandler::METHOD);
        $this->assertSame('response', UrlsMockHandler::RESPONSE);
    }

    /**
     * @return void
     */
    public function testCount(): void
    {
        $this->assertSame(0, $this->handler->count());

        $this->handler->onUriRequested('https://goo.gl', 'get', new Response(200));
        $this->assertSame(1, $this->handler->count());

        $this->handler->onUriRegexpRequested('~https:\/\/goo\.gl\/.*~', 'post', new Response(200));
        $this->assertSame(2, $this->handler->count());
    }

    /**
     * @return void
     */
    public function testGetLastAndHistory(): void
    {
        $this->handler->onUriRegexpRequested(
            '~https:\/\/goo\.gl\/.*~',
            $method = 'get',
            new Response(200, ['foo' => 'bar'], 'blah blah')
        );

        $this->handler->__invoke(new Request($method, $uri1 = 'https://goo.gl/'), [
            RequestOptions::VERIFY => false,
        ]);

        $this->handler->__invoke($request = new Request($method, $uri2 = 'https://goo.gl/foo'), $options = [
            RequestOptions::VERIFY => true,
        ]);

        $this->assertSame($request, $this->handler->getLastRequest());
        $this->assertSame($options, $this->handler->getLastOptions());
        $this->assertSame($uri2, $this->handler->getLastRequestedUri());

        $this->assertSame([$uri1, $uri2], $this->handler->getRequestsUriHistory());
    }

    /**
     * @return void
     */
    public function testCreateWithMiddleware(): void
    {
        $stack = $this->handler::createWithMiddleware();

        $this->assertTrue($stack->hasHandler());
    }

    /**
     * @return void
     */
    public function testInvokeUsingGuzzleClient(): void
    {
        $this->handler->onUriRequested($uri = 'https://goo.gl', $method1 = 'get', new Response(
            $code1 = 200,
            $headers1 = ['foo' => ['bar']],
            $body1 = '<h1>All looks fine!</h1>'
        ));

        $this->handler->onUriRegexpRequested('~https:\/\/goo\.gl\/.*~', $method2 = 'post', new Response(
            $code2 = 404,
            $headers2 = [],
            $body2 = 'Nothing found'
        ));

        $guzzle = new Client([
            'handler' => HandlerStack::create($this->handler),
        ]);

        $response1 = $guzzle->request($method1, $uri);
        $this->assertSame($body1, $response1->getBody()->getContents());
        $this->assertSame($code1, $response1->getStatusCode());
        $this->assertSame($headers1, $response1->getHeaders());

        // Send same request again
        $response2 = $guzzle->request($method1, $uri);
        $this->assertSame($body1, $response2->getBody()->getContents());
        $this->assertSame($code1, $response2->getStatusCode());
        $this->assertSame($headers1, $response2->getHeaders());

        $response3 = $guzzle->request($method2, $uri . '/foo' . \random_int(1, 999), [
            RequestOptions::HTTP_ERRORS => false,
        ]);
        $this->assertSame($body2, $response3->getBody()->getContents());
        $this->assertSame($code2, $response3->getStatusCode());
        $this->assertSame($headers2, $response3->getHeaders());
    }

    /**
     * @return void
     */
    public function testRegisterWithPassingToTopParameter(): void
    {
        $this->handler->onUriRegexpRequested('~https:\/\/goo\.gl\/.*~', $method = 'post', new Response(
            $code1 = 200,
            $headers1 = [],
            $body1 = 'Content 1'
        ));

        $this->handler->onUriRegexpRequested('~https:\/\/goo\.gl\/foo.*~', $method, new Response(
            $code2 = 202,
            $headers2 = [],
            $body2 = 'Content 2'
        ), true);

        $guzzle = new Client([
            'handler' => HandlerStack::create($this->handler),
        ]);

        $response1 = $guzzle->request($method, 'https://goo.gl/foo');

        $this->assertSame($body2, $response1->getBody()->getContents());
        $this->assertSame($code2, $response1->getStatusCode());
        $this->assertSame($headers2, $response1->getHeaders());
    }

    /**
     * @return void
     */
    public function testRegisterWithoutPassingToTopParameter(): void
    {
        $this->handler->onUriRegexpRequested('~https:\/\/goo\.gl\/.*~', $method = 'post', new Response(
            $code1 = 200,
            $headers1 = [],
            $body1 = 'Content 1'
        ));

        $this->handler->onUriRegexpRequested('~https:\/\/goo\.gl\/foo.*~', $method, new Response(
            $code2 = 202,
            $headers2 = [],
            $body2 = 'Content 2'
        ));

        $guzzle = new Client([
            'handler' => HandlerStack::create($this->handler),
        ]);

        $response1 = $guzzle->request($method, 'https://goo.gl/foo');

        $this->assertSame($body1, $response1->getBody()->getContents());
        $this->assertSame($code1, $response1->getStatusCode());
        $this->assertSame($headers1, $response1->getHeaders());
    }

    /**
     * @return void
     */
    public function testSameUriDifferentHttpMethods(): void
    {
        $this->handler->onUriRequested($uri = 'https://goo.gl', $method1 = 'get', new Response($code1 = 201));
        $this->handler->onUriRequested($uri, $method2 = 'patch', new Response($code2 = 202));

        $guzzle = new Client([
            'handler' => HandlerStack::create($this->handler),
        ]);

        $response1 = $guzzle->request($method1, $uri);
        $response2 = $guzzle->request($method2, $uri, ['body' => 'patched']);

        $this->assertEquals($code1, $response1->getStatusCode());
        $this->assertEquals($code2, $response2->getStatusCode());
    }

    /**
     * @return void
     */
    public function testSameUriDifferentHttpMethodsRegex(): void
    {
        $this->handler->onUriRegexpRequested('~https:\/\/goo\.gl~', $method1 = 'get', new Response($code1 = 201));
        $this->handler->onUriRegexpRequested('~https:\/\/goo\.gl~', $method2 = 'patch', new Response($code2 = 202));

        $guzzle = new Client([
            'handler' => HandlerStack::create($this->handler),
        ]);

        $response1 = $guzzle->request($method1, 'https://goo.gl');
        $response2 = $guzzle->request($method2, 'https://goo.gl', ['body' => 'patched']);

        $this->assertEquals($code1, $response1->getStatusCode());
        $this->assertEquals($code2, $response2->getStatusCode());
    }

    /**
     * @return void
     */
    public function testCaseInsensitiveHttpMethod(): void
    {
        $this->handler->onUriRequested('https://goo.gl/1', 'GET', new Response(200));
        $this->handler->onUriRequested('https://goo.gl/2', 'get', new Response(200));
        $this->handler->onUriRequested('https://goo.gl/3', 'gEt', new Response(200));
        $this->handler->onUriRequested('https://goo.gl/4', 'Post', new Response(200));

        $guzzle = new Client([
            'handler' => HandlerStack::create($this->handler),
        ]);

        $response1 = $guzzle->request('get', 'https://goo.gl/1');
        $response2 = $guzzle->request('GET', 'https://goo.gl/2');
        $response3 = $guzzle->request('GeT', 'https://goo.gl/3');
        $response4 = $guzzle->request('POST', 'https://goo.gl/4');

        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertEquals(200, $response2->getStatusCode());
        $this->assertEquals(200, $response3->getStatusCode());
        $this->assertEquals(200, $response4->getStatusCode());
    }
}
