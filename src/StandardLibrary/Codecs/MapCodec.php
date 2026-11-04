<?php
declare(strict_types=1);

namespace Mineflow\Language\StandardLibrary\Codecs;

use Mineflow\Language\Error\InvalidValue;
use Mineflow\Language\Serialization\ValueCodec;
use Mineflow\Language\Serialization\ValueSerializer;
use Mineflow\Language\Values\MapValue;
use Mineflow\Language\Values\Value;

/**
 * Keys 0, 1, ... in order make a JSON array, which is still read back as a map.
 * @implements ValueCodec<MapValue>
 */
final readonly class MapCodec implements ValueCodec {
    public function valueClass(): string {
        return MapValue::class;
    }

    /**
     * @return array<string|int, array{type: string, value: mixed}>
     */
    public function encode(Value $value, ValueSerializer $serializer): array {
        return array_map($serializer->serialize(...), $value->items());
    }

    public function decode(mixed $data, ValueSerializer $serializer): MapValue {
        if (!is_array($data)) {
            throw new InvalidValue("A saved map is an array of its items under their keys, not ".json_encode($data));
        }

        return new MapValue(array_map($serializer->deserialize(...), $data));
    }
}
