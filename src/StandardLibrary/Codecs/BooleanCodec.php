<?php
declare(strict_types=1);

namespace Mineflow\Language\StandardLibrary\Codecs;

use Mineflow\Language\Error\InvalidValue;
use Mineflow\Language\Serialization\ValueCodec;
use Mineflow\Language\Serialization\ValueSerializer;
use Mineflow\Language\Values\BooleanValue;
use Mineflow\Language\Values\Value;

/**
 * @implements ValueCodec<BooleanValue>
 */
final readonly class BooleanCodec implements ValueCodec {
    public function valueClass(): string {
        return BooleanValue::class;
    }

    public function encode(Value $value, ValueSerializer $serializer): bool {
        return $value->value;
    }

    public function decode(mixed $data, ValueSerializer $serializer): BooleanValue {
        return is_bool($data) ? new BooleanValue($data) : throw new InvalidValue("A saved boolean is true or false, not ".json_encode($data));
    }
}
