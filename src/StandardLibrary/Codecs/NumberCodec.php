<?php
declare(strict_types=1);

namespace Mineflow\Language\StandardLibrary\Codecs;

use Mineflow\Language\Error\InvalidValue;
use Mineflow\Language\Serialization\ValueCodec;
use Mineflow\Language\Serialization\ValueSerializer;
use Mineflow\Language\Values\NumberValue;
use Mineflow\Language\Values\Value;

/**
 * JSON keeps a float such as 2.0 apart from an int only when the host encodes with JSON_PRESERVE_ZERO_FRACTION. A
 * numeric string is read because JSON_BIGINT_AS_STRING decodes a large number as one.
 * @implements ValueCodec<NumberValue>
 */
final readonly class NumberCodec implements ValueCodec {
    public function valueClass(): string {
        return NumberValue::class;
    }

    public function encode(Value $value, ValueSerializer $serializer): int|float {
        return $value->value;
    }

    public function decode(mixed $data, ValueSerializer $serializer): NumberValue {
        return match (true) {
            is_int($data), is_float($data) => new NumberValue($data),
            is_string($data) && is_numeric($data) => new NumberValue($data + 0),
            default => throw new InvalidValue("A saved number is a number, not ".json_encode($data)),
        };
    }
}
