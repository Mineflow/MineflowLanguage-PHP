<?php
declare(strict_types=1);

namespace Mineflow\Language\StandardLibrary\Codecs;

use Mineflow\Language\Error\InvalidValue;
use Mineflow\Language\Serialization\ValueCodec;
use Mineflow\Language\Serialization\ValueSerializer;
use Mineflow\Language\Values\NullValue;
use Mineflow\Language\Values\Value;

/**
 * @implements ValueCodec<NullValue>
 */
final readonly class NullCodec implements ValueCodec {
    public function valueClass(): string {
        return NullValue::class;
    }

    public function encode(Value $value, ValueSerializer $serializer): null {
        return null;
    }

    public function decode(mixed $data, ValueSerializer $serializer): NullValue {
        return $data === null ? new NullValue() : throw new InvalidValue("A saved null is null, not ".json_encode($data));
    }
}
