<?php

declare(strict_types = 1);

namespace Tarampampam\GuzzleUrlMock;

use Exception;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\TransferStats;
use InvalidArgumentException;
use OutOfBoundsException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class UrlsMockHandler implements \Countable
{
    const METHOD   = 'method';

    const RESPONSE = 'response';

    const TIMES = 'times';

    /**
     * Registered URI regexp patterns.
     *
     * @var array|array[]
     */
    protected $uri_patterns = [];

    /**
     * Registered fixed URIs.
     *
     * @var array|array[]
     */
    protected $uri_fixed = [];

    /**
     * Last processed request.
     *
     * @var RequestInterface|null
     */
    protected $last_request;

    /**
     * Options, passed with last processed request.
     *
     * @var array|null
     */
    protected $last_options;

    /**
     * Array with URI of all processed requests.
     *
     * @var string[]
     */
    protected $requests_uri_history = [];

    /**
     * Invoke incoming request.
     *
     * @param RequestInterface $request
     * @param array            $options
     *
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     *
     * @return PromiseInterface
     */
    public function __invoke(RequestInterface $request, array $options)
    {
        if ($this->count() <= 0) {
            throw new OutOfBoundsException('Mock actions is empty');
        }

        if (isset($options['delay'])) {
            \usleep($options['delay'] * 1000);
        }

        $this->last_request           = $request;
        $this->last_options           = $options;
        $this->requests_uri_history[] = $request->getUri()->__toString();

        $response = $this->findResponseForRequest($request);

        if ($response === null) {
            throw new OutOfBoundsException(
                "There is no action for requested URI: {$request->getUri()->__toString()} ({$request->getMethod()})"
            );
        }

        // Fix "empty response content" error
        if ($response instanceof ResponseInterface) {
            $response->getBody()->rewind();
        }

        if (isset($options['on_headers'])) {
            if (! \is_callable($options['on_headers'])) {
                throw new InvalidArgumentException('on_headers must be callable');
            }
            try {
                $options['on_headers']($response);
            } catch (Exception $e) {
                $msg      = 'An error was encountered during the on_headers event';
                $response = new RequestException($msg, $request, $response, $e);
            }
        }

        if (\is_callable($response)) {
            $response = $response($request, $options);
        }

        $response = $response instanceof \Exception
            ? \GuzzleHttp\Promise\rejection_for($response)
            : \GuzzleHttp\Promise\promise_for($response);

        return $response->then(
            function ($value) use ($request, $options) {
                /* @var callable|Exception|PromiseInterface|ResponseInterface|mixed $value */
                $this->invokeStats($request, $options, $value);

                if (isset($options['sink'])) {
                    $contents = (string) $value->getBody();
                    $sink     = $options['sink'];

                    if (\is_resource($sink)) {
                        \fwrite($sink, $contents);
                    } elseif (\is_string($sink)) {
                        \file_put_contents($sink, $contents);
                    } elseif ($sink instanceof \Psr\Http\Message\StreamInterface) {
                        $sink->write($contents);
                    }
                }

                return $value;
            },
            function ($reason) use ($request, $options) {
                $this->invokeStats($request, $options, null, $reason);

                return \GuzzleHttp\Promise\rejection_for($reason);
            }
        );
    }

    /**
     * Creates a new handler that uses the default handler stack list of middlewares.
     *
     * @return HandlerStack
     */
    public static function createWithMiddleware(): HandlerStack
    {
        return HandlerStack::create(new self);
    }

    /**
     * Register action for URI request.
     *
     * @param string                                                $uri
     * @param string                                                $method
     * @param callable|Exception|PromiseInterface|ResponseInterface $response
     * @param int                                                   $times    Number of times to respond (-1 is infinite)
     *
     * @throws InvalidArgumentException
     *
     * @return void
     */
    public function onUriRequested(string $uri, string $method, $response, $times = -1)
    {
        if ($this->validateResponse($response)) {
            $this->registerUri($this->uri_fixed, $uri, $method, $response, $times);
        }
    }

    /**
     * Register action for URI regexp pattern request.
     *
     * @param string                                                $uri_pattern
     * @param string                                                $method
     * @param callable|Exception|PromiseInterface|ResponseInterface $response
     * @param bool                                                  $to_top      Push action into the top of stack
     * @param int                                                   $times       Number of times to respond (-1 is infinite)
     *
     * @throws InvalidArgumentException
     *
     * @return void
     */
    public function onUriRegexpRequested(string $uri_pattern, string $method, $response, bool $to_top = false, $times = -1)
    {
        if (@\preg_match($uri_pattern, '') === false) {
            throw new InvalidArgumentException("Wrong URI pattern [$uri_pattern] passed");
        }

        if ($this->validateResponse($response)) {
            if ($to_top === true) {
                $entry = [
                    static::RESPONSE => $response,
                    static::TIMES =>    $times,
                ];
                $top = [
                    $uri_pattern => [
                        static::METHOD   => $method,
                        static::RESPONSE => [$entry],
                    ]
                ];
                $this->uri_patterns = $top + $this->uri_patterns;
            } else {
                $this->registerUri($this->uri_patterns, $uri_pattern, $method, $response, $times);
            }
        }
    }

    /**
     * Returns an array of all requested URIs.
     *
     * @return string[]
     */
    public function getRequestsUriHistory(): array
    {
        return $this->requests_uri_history;
    }

    /**
     * Get the last requested URI.
     *
     * @return string|null
     */
    public function getLastRequestedUri()
    {
        $count = \count($this->requests_uri_history);

        return $count > 0
            ? $this->requests_uri_history[$count - 1]
            : null;
    }

    /**
     * Get the last received request.
     *
     * @return RequestInterface|null
     */
    public function getLastRequest()
    {
        return $this->last_request;
    }

    /**
     * Get the last received request options.
     *
     * @return array|null
     */
    public function getLastOptions()
    {
        return $this->last_options;
    }

    /**
     * Returns the number of all registered URIs.
     *
     * @return int
     */
    public function count(): int
    {
        $num = 0;
        $num += $this->countResponses($this->uri_fixed);
        $num += $this->countResponses($this->uri_patterns);

        return $num;
    }

    /**
     * Try to find response for passed request.
     *
     * @param RequestInterface $request
     *
     * @return mixed|null
     */
    protected function findResponseForRequest(RequestInterface $request)
    {
        $uri    = $request->getUri()->__toString();
        $method = \mb_strtolower($request->getMethod());

        if (isset($this->uri_fixed[$uri]) && \mb_strtolower($this->uri_fixed[$uri][static::METHOD]) === $method) {
            return $this->processAndReturnResponse($this->uri_fixed, 0, $uri);
        }

        $index = 0;
        foreach ($this->uri_patterns as $uri_pattern => $rule_array) {
            if (\preg_match($uri_pattern, $uri) && \mb_strtolower($rule_array[static::METHOD]) === $method) {
                return $this->processAndReturnResponse($this->uri_patterns, $index, $uri_pattern);
            }
            $index++;
        }
    }

    /**
     * Make response instance validation.
     *
     * @param mixed $response
     *
     * @throws InvalidArgumentException
     *
     * @return bool
     */
    protected function validateResponse($response): bool
    {
        if ($response instanceof ResponseInterface
            || $response instanceof Exception
            || $response instanceof PromiseInterface
            || \is_callable($response)
        ) {
            return true;
        }

        throw new InvalidArgumentException(
            'Expected a response or exception. Found ' . \GuzzleHttp\describe_type($response)
        );
    }

    /**
     * Invoke stats.
     *
     * @param RequestInterface       $request
     * @param array                  $options
     * @param ResponseInterface|null $response
     * @param mixed                  $reason
     */
    protected function invokeStats(
        RequestInterface $request,
        array $options,
        ResponseInterface $response = null,
        $reason = null
    ) {
        if (isset($options['on_stats']) && \is_callable($on_stats = $options['on_stats'])) {
            $on_stats(new TransferStats($request, $response, null, $reason));
        }
    }

    /**
     * Counts the number of responses in an array.
     *
     * @param array $uri_array Either $this->uri_fixed or $this->uri_patterns
     *
     * @return int
     */
    private function countResponses($uri_array)
    {
        $num = 0;
        foreach ($uri_array as $uri_item) {
            foreach ($uri_item[static::RESPONSE] as $response) {
                $times = $response[static::TIMES];
                if ($times === -1) {
                    $times = 1;
                }
                $num += $times;
            }
        }

        return $num;
    }

    /**
     * Register the URI.
     *
     * @param array                                                 $uri_array Either $this->uri_fixed or $this->uri_patterns
     * @param string                                                $uri
     * @param string                                                $method
     * @param callable|Exception|PromiseInterface|ResponseInterface $response
     * @param int                                                   $times     Number of times to respond (-1 is infinite)
     *
     * @return void
     */
    private function registerUri(array &$uri_array, string $uri, string $method, $response, int $times)
    {
        $shouldRegisterUri = ! isset($uri_array[$uri]);
        if ($shouldRegisterUri) {
            $uri_array[$uri] = [
                static::METHOD   => $method,
                static::RESPONSE => [],
            ];
        }
        $uri_array[$uri][static::RESPONSE][] = [
            static::RESPONSE => $response,
            static::TIMES    => $times,
        ];
    }

    /**
     * Removes the response for $uri from $uri_array.
     *
     * @param array  $uri_array Either $this->uri_fixed or $this->uri_patterns
     * @param string $uri       The request URI
     *
     * @return void
     */
    private function removeResponse(&$uri_array, $uri)
    {
        unset($uri_array[$uri][static::RESPONSE][0]);
        $uri_array[$uri][static::RESPONSE] = array_values($uri_array[$uri][static::RESPONSE]);
        if (count($uri_array[$uri][static::RESPONSE]) === 0) {
            unset($uri_array[$uri]);
        }
    }

    /**
     * Decrements the fixed response and removes it if it's the last one.
     *
     * @param array  $uri_array Either $this->uri_fixed or $this->uri_patterns
     * @param int    $index     The index of the array which contains the response
     * @param string $uri       The request URI
     *
     * @return mixed|null
     */
    private function processAndReturnResponse(&$uri_array, $index, $uri)
    {
        $response = $uri_array[$uri][static::RESPONSE][$index];

        $isLastResponse = $response[static::TIMES] === 1;
        if ($isLastResponse) {
            $this->removeResponse($uri_array, $uri);
        } else {
            $hasInfiniteResponses = $response[static::TIMES] === -1;
            if (! $hasInfiniteResponses) {
                $uri_array[$uri][static::RESPONSE][$index][static::TIMES]--;
            }
        }

        return $response[static::RESPONSE];
    }
}
