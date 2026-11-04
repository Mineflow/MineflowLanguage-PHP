<?php
declare(strict_types=1);

namespace Mineflow\Language\StandardLibrary\Codecs;

use Mineflow\Language\Error\InvalidValue;
use Mineflow\Language\Serialization\ValueCodec;
use Mineflow\Language\Serialization\ValueSerializer;
use Mineflow\Language\Values\StringValue;
use Mineflow\Language\Values\Value;

/**
 * @implements ValueCodec<StringValue>
 */
final readonly class StringCodec implements ValueCodec {
    public function valueClass(): string {
        return StringValue::class;
    }

    public function encode(Value $value, ValueSerializer $serializer): string {
        return $value->value;
    }

    public function decode(mixed $data, ValueSerializer $serializer): StringValue {
        return is_string($data) ? new StringValue($data) : throw new InvalidValue("A saved string is a string, not ".json_encode($data));
    }
}
