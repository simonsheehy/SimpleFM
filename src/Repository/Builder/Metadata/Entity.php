<?php

declare(strict_types=1);

namespace Soliant\SimpleFM\Repository\Builder\Metadata;

use Assert\Assertion;

final class Entity
{
    /**
     * @var string
     */
    private $layout;

    /**
     * @var string
     */
    private $className;

    /**
     * @var Field[]
     */
    private $fields;

    /**
     * @var Embeddable[]
     */
    private $embeddables;

    /**
     * @var OneToMany[]
     */
    private $oneToMany;

    /**
     * @var ManyToOne[]
     */
    private $manyToOne;

    /**
     * @var OneToOne[]
     */
    private $oneToOne;

    /**
     * @var RecordId|null
     */
    private $recordId;

    /**
     * @var string
     */
    private $interfaceName;

    public function __construct(
        string $layout,
        string $className,
        array $fields,
        array $embeddables,
        array $oneToMany,
        array $manyToOne,
        array $oneToOne,
        ?RecordId $recordId = null,
        ?string $interfaceName = null
    ) {
        $this->validateArray($fields, Field::class);
        $this->validateArray($embeddables, Embeddable::class);
        $this->validateArray($oneToMany, OneToMany::class);
        $this->validateArray($manyToOne, ManyToOne::class);
        $this->validateArray($oneToOne, OneToOne::class);

        $this->layout = $layout;
        $this->className = $className;
        $this->fields = $fields;
        $this->embeddables = $embeddables;
        $this->oneToMany = $oneToMany;
        $this->manyToOne = $manyToOne;
        $this->oneToOne = $oneToOne;
        $this->recordId = $recordId;
        $this->interfaceName = $interfaceName;
    }

    public function getLayout(): string
    {
        return $this->layout;
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function hasInterfaceName(): bool
    {
        return $this->interfaceName !== null;
    }

    public function getInterfaceName(): string
    {
        Assertion::notNull($this->interfaceName);

        return $this->interfaceName;
    }

    /**
     * @return Field[]
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @return Embeddable[]
     */
    public function getEmbeddables(): array
    {
        return $this->embeddables;
    }

    /**
     * @return OneToMany[]
     */
    public function getOneToMany(): array
    {
        return $this->oneToMany;
    }

    /**
     * @return ManyToOne[]
     */
    public function getManyToOne(): array
    {
        return $this->manyToOne;
    }

    /**
     * @return OneToOne[]
     */
    public function getOneToOne(): array
    {
        return $this->oneToOne;
    }

    public function hasRecordId(): bool
    {
        return $this->recordId !== null;
    }

    public function getRecordId(): RecordId
    {
        Assertion::notNull($this->recordId);

        return $this->recordId;
    }

    private function validateArray(array $array, string $expectedClassName)
    {
        Assertion::count(array_filter($array, function ($metadata) use ($expectedClassName): bool {
            return ! $metadata instanceof $expectedClassName;
        }), 0, sprintf('At least one element in array is not an instance of %s', $expectedClassName));
    }
}
