<?php

declare(strict_types=1);

namespace Soliant\SimpleFM\Repository\Builder\Metadata;

use Assert\Assertion;

final class ManyToOne
{
    /**
     * @var string
     */
    private $fieldName;

    /**
     * @var string
     */
    private $propertyName;

    /**
     * @var string
     */
    private $targetTable;

    /**
     * @var string
     */
    private $targetEntity;

    /**
     * @var string
     */
    private $targetPropertyName;

    /**
     * @var string
     */
    private $targetFieldName;

    /**
     * @var string
     */
    private $targetInterfaceName;

    /**
     * @var bool
     */
    private $readOnly;

    /**
     * @var bool
     */
    private $eagerHydration;

    public function __construct(
        string $fieldName,
        string $propertyName,
        string $targetTable,
        string $targetEntity,
        string $targetPropertyName,
        string $targetFieldName,
        ?string $targetInterfaceName = null,
        bool $readOnly = false,
        bool $eagerHydration = false
    ) {
        $this->fieldName = $fieldName;
        $this->propertyName = $propertyName;
        $this->targetTable = $targetTable;
        $this->targetEntity = $targetEntity;
        $this->targetPropertyName = $targetPropertyName;
        $this->targetFieldName = $targetFieldName;
        $this->targetInterfaceName = $targetInterfaceName;
        $this->readOnly = $readOnly;
        $this->eagerHydration = $eagerHydration;
    }

    public function getFieldName(): string
    {
        return $this->fieldName;
    }

    public function getPropertyName(): string
    {
        return $this->propertyName;
    }

    public function getTargetTable(): string
    {
        return $this->targetTable;
    }

    public function getTargetEntity(): string
    {
        return $this->targetEntity;
    }

    public function getTargetPropertyName(): string
    {
        return $this->targetPropertyName;
    }

    public function getTargetFieldName(): string
    {
        return $this->targetFieldName;
    }

    public function getTargetInterfaceName(): string
    {
        Assertion::notNull(
            $this->targetInterfaceName,
            sprintf('Target entity %s has no interface name defined', $this->targetEntity)
        );

        return $this->targetInterfaceName;
    }

    public function isReadOnly(): bool
    {
        return $this->readOnly;
    }

    public function hasEagerHydration(): bool
    {
        return $this->eagerHydration;
    }
}
