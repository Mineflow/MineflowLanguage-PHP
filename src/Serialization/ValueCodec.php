<?php
declare(strict_types=1);

namespace Mineflow\Language\Serialization;

use Mineflow\Language\Error\InvalidValue;
use Mineflow\Language\Values\Value;

/**
 * @template T of object A Value class, or an interface Value classes implement
 */
interface ValueCodec {
    /**
     * @return class-string<T>
     */
    public function valueClass(): string;

    /**
     * Returns the "value" part of a saved value, and only strings, numbers, booleans, null and arrays of those, so that
     * JSON can hold it. A value it holds goes through $serializer->serialize(), so that it keeps its type.
     * @param T&Value $value
     */
    public function encode(Value $value, ValueSerializer $serializer): mixed;

    /**
     * @return T&Value
     * @throws InvalidValue When the data is not of the shape encode() writes.
     */
    public function decode(mixed $data, ValueSerializer $serializer): Value;
}
