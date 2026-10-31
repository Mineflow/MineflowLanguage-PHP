<?php
declare(strict_types=1);

namespace Mineflow\Language\Types;

/**
 * @phpstan-sealed ListType|MapType
 */
abstract readonly class CollectionType extends Type {
    protected function __construct(public string $name, public Type $key, public Type $element) {
    }
}
