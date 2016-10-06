<?php
declare(strict_types = 1);

namespace Soliant\SimpleFM\Client\ResultSet\Transformer;

use InvalidArgumentException;
use Litipk\BigNumbers\Decimal;
use Soliant\SimpleFM\Client\ResultSet\Transformer\Exception\DecimalException;

final class NumberTransformer
{
    public function __invoke(string $value)
    {
        if ('' === $value) {
            return null;
        }

        if (0 === strpos($value, '.')) {
            $value = '0' . $value;
        }

        try {
            return Decimal::fromString($value);
        } catch (InvalidArgumentException $e) {
            throw DecimalException::fromInvalidString($value);
        }
    }
}
