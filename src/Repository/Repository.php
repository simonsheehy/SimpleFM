<?php

declare(strict_types=1);

namespace Soliant\SimpleFM\Repository;

use Soliant\SimpleFM\Authentication\Identity;
use Soliant\SimpleFM\Client\ResultSet\ResultSetClientInterface;
use Soliant\SimpleFM\Collection\CollectionInterface;
use Soliant\SimpleFM\Collection\ItemCollection;
use Soliant\SimpleFM\Connection\Command;
use Soliant\SimpleFM\Repository\Builder\Proxy\ProxyInterface;
use Soliant\SimpleFM\Repository\Exception\DomainException;
use Soliant\SimpleFM\Repository\Exception\InvalidResultException;
use Soliant\SimpleFM\Repository\Query\FindQuery;
use SplObjectStorage;

final class Repository implements RepositoryInterface
{
    /**
     * @var ResultSetClientInterface
     */
    private $resultSetClient;

    /**
     * @var string
     */
    private $layout;

    /**
     * @var HydrationInterface
     */
    private $hydration;

    /**
     * @var ExtractionInterface
     */
    private $extraction;

    /**
     * @var Identity|null
     */
    private $identity;

    /**
     * @var SplObjectStorage
     */
    private $managedEntities;

    /**
     * @var array
     */
    private $entitiesByRecordId = [];

    public function __construct(
        ResultSetClientInterface $resultSetClient,
        string $layout,
        HydrationInterface $hydration,
        ExtractionInterface $extraction
    ) {
        $this->resultSetClient = $resultSetClient;
        $this->layout = $layout;
        $this->hydration = $hydration;
        $this->extraction = $extraction;
        $this->managedEntities = new SplObjectStorage();
    }

    public function withIdentity(Identity $identity): RepositoryInterface
    {
        $repository = clone $this;
        $repository->identity = $identity;

        return $repository;
    }

    public function find(int $recordId)
    {
        return $this->findOneBy(['-recid' => $recordId]);
    }

    public function findOneBy(array $search, bool $autoQuoteSearch = true)
    {
        $resultSet = $this->execute(new Command(
            $this->layout,
            $this->createSearchParameters($search, $autoQuoteSearch) + ['-find' => null, '-max' => 1]
        ));

        if ($resultSet->isEmpty()) {
            return null;
        }

        return $this->createEntity($resultSet->first());
    }

    public function findOneByQuery(FindQuery $query)
    {
        $resultSet = $this->execute(new Command(
            $this->layout,
            $query->toParameters() + ['-findquery' => null, '-max' => 1]
        ));

        if ($resultSet->isEmpty()) {
            return null;
        }

        return $this->createEntity($resultSet->first());
    }

    public function findAll(array $sort = [], ?int $limit = null, ?int $offset = null): CollectionInterface
    {
        $resultSet = $this->execute(new Command(
            $this->layout,
            (
                $this->createSortParameters($sort)
                + $this->createLimitAndOffsetParameters($limit, $offset)
                + ['-findall' => null]
            )
        ));

        return $this->createCollection($resultSet);
    }

    public function findBy(
        array $search,
        array $sort = [],
        ?int $limit = null,
        ?int $offset = null,
        bool $autoQuoteSearch = true
    ): CollectionInterface {
        $resultSet = $this->execute(new Command(
            $this->layout,
            (
                $this->createSearchParameters($search, $autoQuoteSearch)
                + $this->createSortParameters($sort)
                + $this->createLimitAndOffsetParameters($limit, $offset)
                + ['-find' => null]
            )
        ));

        return $this->createCollection($resultSet);
    }

    public function findByQuery(
        FindQuery $findQuery,
        array $sort = [],
        ?int $limit = null,
        ?int $offset = null
    ): CollectionInterface {
        $resultSet = $this->execute(new Command(
            $this->layout,
            (
                $findQuery->toParameters()
                + $this->createSortParameters($sort)
                + $this->createLimitAndOffsetParameters($limit, $offset)
                + ['-findquery' => null]
            )
        ));

        return $this->createCollection($resultSet);
    }

    public function insert($entity)
    {
        $this->persist($entity, '-new');
    }

    public function update($entity, bool $force = false)
    {
        if ($entity instanceof ProxyInterface) {
            $entity = $entity->__getRealEntity();
        }

        if (! isset($this->managedEntities[$entity])) {
            throw DomainException::fromUnmanagedEntity($entity);
        }

        $parameters = ['-recid' => $this->managedEntities[$entity]['record-id']];

        if (! $force) {
            $parameters['-modid'] = $this->managedEntities[$entity]['mod-id'];
        }

        $this->persist($entity, '-edit', $parameters);
    }

    public function delete($entity, bool $force = false)
    {
        if ($entity instanceof ProxyInterface) {
            $entity = $entity->__getRealEntity();
        }

        if (! isset($this->managedEntities[$entity])) {
            throw DomainException::fromUnmanagedEntity($entity);
        }

        $parameters = ['-recid' => $this->managedEntities[$entity]['record-id'], '-delete' => null];

        if (! $force) {
            $parameters['-modid'] = $this->managedEntities[$entity]['mod-id'];
        }

        $this->execute(new Command($this->layout, $parameters));
        unset($this->managedEntities[$entity]);
    }

    public function quoteString(string $string): string
    {
        return $this->resultSetClient->quoteString($string);
    }

    public function createEntity(array $record)
    {
        if (array_key_exists($record['record-id'], $this->entitiesByRecordId)) {
            $entity = $this->entitiesByRecordId[$record['record-id']];
        } else {
            $entity = $this->hydration->hydrateNewEntity($record);
        }

        $this->addOrUpdateManagedEntity($record['record-id'], $record['mod-id'], $entity);

        return $entity;
    }

    private function persist($entity, string $mode, array $additionalParameters = [])
    {
        $resultSet = $this->execute(new Command(
            $this->layout,
            $this->extraction->extract($entity) + $additionalParameters + [$mode => null]
        ));

        if ($resultSet->isEmpty()) {
            throw InvalidResultException::fromEmptyResultSet();
        }

        $record = $resultSet->first();

        $this->hydration->hydrateExistingEntity($record, $entity);
        $this->addOrUpdateManagedEntity($record['record-id'], $record['mod-id'], $entity);
    }

    private function addOrUpdateManagedEntity(int $recordId, int $modId, $entity)
    {
        $this->managedEntities[$entity] = [
            'record-id' => $recordId,
            'mod-id' => $modId,
        ];
        $this->entitiesByRecordId[$recordId] = $entity;
    }

    private function createCollection(CollectionInterface $resultSet): CollectionInterface
    {
        $entities = [];

        foreach ($resultSet as $record) {
            $entities[] = $this->createEntity($record);
        }

        return new ItemCollection($entities, $resultSet->getTotalCount());
    }

    private function createSearchParameters(array $search, bool $autoQuoteSearch): array
    {
        $searchParameters = [];

        foreach ($search as $field => $value) {
            $searchParameters[$field] = $autoQuoteSearch ? $this->quoteString((string) $value) : (string) $value;
        }

        return $searchParameters;
    }

    private function createSortParameters(array $sort): array
    {
        if (count($sort) > 9) {
            throw DomainException::fromTooManySortParameters(9, $sort);
        }

        $index = 1;
        $parameters = [];

        foreach ($sort as $field => $order) {
            $parameters['-sortfield.'.$index] = $field;
            $parameters['-sortorder.'.$index] = $order;
            $index++;
        }

        return $parameters;
    }

    private function createLimitAndOffsetParameters(?int $limit = null, ?int $offset = null): array
    {
        $parameters = [];

        if ($limit !== null) {
            $parameters['-max'] = $limit;
        }

        if ($offset !== null) {
            $parameters['-skip'] = $offset;
        }

        return $parameters;
    }

    private function execute(Command $command): CollectionInterface
    {
        if ($this->identity !== null) {
            $command = $command->withIdentity($this->identity);
        }

        return $this->resultSetClient->execute($command);
    }
}
