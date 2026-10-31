<?php
declare(strict_types=1);

namespace Mineflow\Language\Values;

use Mineflow\Language\Types\CollectionType;
use Mineflow\Language\Types\NamedType;

/**
 * @phpstan-sealed StringValue|NumberValue|BooleanValue|NullValue|ObjectValue|CollectionValue
 */
abstract readonly class Value {
    protected function __construct(public NamedType|CollectionType $type) {
    }

    abstract public function native(): mixed;
}
