<?php

declare(strict_types=1);

namespace Soliant\SimpleFM\Repository\Builder\Type;

use Assert\Assertion;
use DateTimeInterface;

final class DateTimeType implements TypeInterface
{
    public function fromFileMakerValue($value)
    {
        if ($value === null) {
            return null;
        }

        Assertion::isInstanceOf($value, DateTimeInterface::class);

        return $value;
    }

    public function toFileMakerValue($value)
    {
        if ($value === null) {
            return null;
        }

        Assertion::isInstanceOf($value, DateTimeInterface::class);

        return $value;
    }
}
