<?php
declare(strict_types=1);

namespace Mineflow\Language\Types\Expression;

use Mineflow\Language\Types\TypeExpression;
use Mineflow\Language\Values\MapValue;

final readonly class MapOf extends CollectionExpression {
    /**
     * @param TypeExpression|class-string $element
     * @param TypeExpression|class-string|null $key
     */
    public function __construct(TypeExpression|string $element, TypeExpression|string|null $key = null) {
        parent::__construct(MapValue::class, $element, $key);
    }
}
