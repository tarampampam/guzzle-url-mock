<?php

declare(strict_types=1);

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
     * @var mixed[]|null
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
     * @param mixed[]          $options
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
     *
     * @throws InvalidArgumentException
     *
     * @return void
     */
    public function onUriRequested(string $uri, string $method, $response)
    {
        if ($this->validateResponse($response)) {
            $index                   = $method . ' ' . $uri;
            $this->uri_fixed[$index] = [
                static::METHOD   => $method,
                static::RESPONSE => $response,
            ];
        }
    }

    /**
     * Register action for URI regexp pattern request.
     *
     * @param string                                                $uri_pattern
     * @param string                                                $method
     * @param callable|Exception|PromiseInterface|ResponseInterface $response
     * @param bool                                                  $to_top      Push action into the top of stack
     *
     * @throws InvalidArgumentException
     *
     * @return void
     */
    public function onUriRegexpRequested(string $uri_pattern, string $method, $response, bool $to_top = false)
    {
        if (@\preg_match($uri_pattern, '') === false) {
            throw new InvalidArgumentException("Wrong URI pattern [$uri_pattern] passed");
        }

        if ($this->validateResponse($response)) {
            $entry = [
                static::METHOD   => $method,
                static::RESPONSE => $response,
            ];

            $index = $method . ' ' . $uri_pattern;
            if ($to_top === true) {
                $this->uri_patterns = [$index => $entry] + $this->uri_patterns;
            } else {
                $this->uri_patterns[$index] = $entry;
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
     * @return mixed[]|null
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
        return \count($this->uri_fixed) + \count($this->uri_patterns);
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

        $index = $method . ' ' . $uri;
        if (isset($this->uri_fixed[$index])) {
            return $this->uri_fixed[$index][static::RESPONSE];
        }

        foreach ($this->uri_patterns as $uri_pattern => $rule_array) {
            $uri_pattern = $this->removeMethodFromPattern($uri_pattern);
            if (\preg_match($uri_pattern, $uri) && \mb_strtolower($rule_array[static::METHOD]) === $method) {
                return $rule_array[static::RESPONSE];
            }
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
     * @param mixed[]                $options
     * @param ResponseInterface|null $response
     * @param mixed                  $reason
     *
     * @return void
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
     * Removes the http from the uri method.
     *
     * @param string $uri_pattern A uri pattern containing a method e.g. get ~https:\/\/goo\.gl~
     *
     * @return string e.g. ~https:\/\/goo\.gl~
     */
    private function removeMethodFromPattern($uri_pattern)
    {
        return mb_substr($uri_pattern, mb_strpos($uri_pattern, ' ') + 1);
    }
}
