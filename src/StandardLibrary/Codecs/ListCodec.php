<?php
declare(strict_types=1);

namespace Mineflow\Language\StandardLibrary\Codecs;

use Mineflow\Language\Error\InvalidValue;
use Mineflow\Language\Serialization\ValueCodec;
use Mineflow\Language\Serialization\ValueSerializer;
use Mineflow\Language\Values\ListValue;
use Mineflow\Language\Values\Value;

/**
 * @implements ValueCodec<ListValue>
 */
final readonly class ListCodec implements ValueCodec {
    public function valueClass(): string {
        return ListValue::class;
    }

    /**
     * @return list<array{type: string, value: mixed}>
     */
    public function encode(Value $value, ValueSerializer $serializer): array {
        return array_map($serializer->serialize(...), $value->items());
    }

    public function decode(mixed $data, ValueSerializer $serializer): ListValue {
        if (!is_array($data)) {
            throw new InvalidValue("A saved list is an array of its items, not ".json_encode($data));
        }

        return new ListValue(array_values(array_map($serializer->deserialize(...), $data)));
    }
}
