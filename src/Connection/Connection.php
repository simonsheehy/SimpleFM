<?php

declare(strict_types=1);

namespace Soliant\SimpleFM\Connection;

use Assert\Assertion;
use Http\Client\HttpClient;
use Laminas\Diactoros\Request;
use Laminas\Diactoros\Stream;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SimpleXMLElement;
use Soliant\SimpleFM\Authentication\IdentityHandlerInterface;
use Soliant\SimpleFM\Connection\Exception\InvalidResponseException;

final class Connection implements ConnectionInterface
{
    /**
     * @var HttpClient
     */
    private $httpClient;

    /**
     * @var UriInterface
     */
    private $uri;

    /**
     * @var string
     */
    private $database;

    /**
     * @var IdentityHandlerInterface|null
     */
    private $identityHandler;

    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        HttpClient $httpClient,
        UriInterface $uri,
        string $database,
        ?IdentityHandlerInterface $identityHandler = null,
        ?LoggerInterface $logger = null
    ) {
        $this->httpClient = $httpClient;
        $this->uri = $uri;
        $this->database = $database;
        $this->identityHandler = $identityHandler;
        $this->logger = $logger ?: new NullLogger();
    }

    public function execute(Command $command, string $grammarPath): SimpleXMLElement
    {
        $uri = $this->uri->withPath($grammarPath);
        $response = $this->httpClient->sendRequest($this->buildRequest($command, $uri));

        if ((int) $response->getStatusCode() !== 200) {
            throw InvalidResponseException::fromUnsuccessfulResponse($response);
        }

        $previousValue = libxml_use_internal_errors(true);
        $xml = simplexml_load_string((string) $response->getBody());
        libxml_use_internal_errors($previousValue);

        if ($xml === false) {
            throw InvalidResponseException::fromXmlError(libxml_get_last_error());
        }

        return $xml;
    }

    public function getAsset(string $assetUri): StreamInterface
    {
        $assetUriParts = parse_url($assetUri);
        $uri = $this->uri->withUserInfo('');

        if (array_key_exists('path', $assetUriParts)) {
            $uri = $uri->withPath($assetUriParts['path']);
        }

        if (array_key_exists('query', $assetUriParts)) {
            $uri = $uri->withQuery($assetUriParts['query']);
        }

        $request = (new Request($uri, 'GET'))
            ->withAddedHeader('User-agent', 'SimpleFM');

        $credentials = urldecode($this->uri->getUserInfo());

        if ($credentials !== '') {
            $request = $request->withAddedHeader('Authorization', sprintf('Basic %s', base64_encode($credentials)));
        }

        $response = $this->httpClient->sendRequest($request);

        if ((int) $response->getStatusCode() !== 200) {
            throw InvalidResponseException::fromUnsuccessfulResponse($response);
        }

        return $response->getBody();
    }

    private function buildRequest(Command $command, UriInterface $uri): RequestInterface
    {
        $parameters = sprintf('-db=%s&%s', urlencode($this->database), $command);

        $body = new Stream('php://temp', 'wb+');
        $body->write($parameters);
        $body->rewind();

        $request = (new Request($uri->withUserInfo(''), 'POST'))
            ->withAddedHeader('User-agent', 'SimpleFM')
            ->withAddedHeader('Content-type', 'application/x-www-form-urlencoded')
            ->withAddedHeader('Content-length', (string) strlen($parameters))
            ->withBody($body);

        $credentials = urldecode($uri->getUserInfo());

        if ($command->hasIdentity()) {
            Assertion::notNull($this->identityHandler, 'An identity handler must be set to use identities on commands');
            $identity = $command->getIdentity();

            $credentials = sprintf(
                '%s:%s',
                $identity->getUsername(),
                $this->identityHandler->decryptPassword($identity)
            );
        }

        $this->logger->info(sprintf('%s?%s', (string) $uri->withUserInfo(''), $parameters));

        if ($credentials === '') {
            return $request;
        }

        return $request->withAddedHeader('Authorization', sprintf('Basic %s', base64_encode($credentials)));
    }
}
