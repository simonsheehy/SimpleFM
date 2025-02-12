<?php

declare(strict_types=1);

namespace Soliant\SimpleFM\Authentication;

use Soliant\SimpleFM\Authentication\Exception\InvalidResultException;
use Soliant\SimpleFM\Client\ResultSet\ResultSetClientInterface;
use Soliant\SimpleFM\Connection\Command;
use Soliant\SimpleFM\Connection\Exception\InvalidResponseException;

final class Authenticator
{
    /**
     * @var ResultSetClientInterface
     */
    private $resultSetClient;

    /**
     * @var IdentityHandlerInterface
     */
    private $identityHandler;

    /**
     * @var string
     */
    private $identityLayout;

    /**
     * @var string
     */
    private $usernameField;

    public function __construct(
        ResultSetClientInterface $resultSetClient,
        IdentityHandlerInterface $identityHandler,
        string $identityLayout,
        string $usernameField
    ) {
        $this->resultSetClient = $resultSetClient;
        $this->identityHandler = $identityHandler;
        $this->identityLayout = $identityLayout;
        $this->usernameField = $usernameField;
    }

    public function authenticate(string $username, string $password): Result
    {
        $identity = $this->identityHandler->createIdentity($username, $password);

        try {
            $resultSet = $this->resultSetClient->execute(
                (new Command($this->identityLayout, [
                    $this->usernameField => '=='.$this->resultSetClient->quoteString($username),
                    '-find' => null,
                ]))->withIdentity($identity)
            );
        } catch (InvalidResponseException $e) {
            $errorCode = $e->getCode();

            if ($errorCode === 401) {
                return Result::fromInvalidCredentials();
            }

            throw $e;
        }

        if ($resultSet->isEmpty()) {
            throw InvalidResultException::fromEmptyResultSet();
        }

        return Result::fromIdentity($identity);
    }
}
