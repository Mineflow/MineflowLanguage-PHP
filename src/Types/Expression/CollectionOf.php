<?php
declare(strict_types=1);

namespace Mineflow\Language\Types\Expression;

use Mineflow\Language\Types\TypeExpression;
use Mineflow\Language\Values\CollectionValue;

/**
 * A collection: a list, a map, or another CollectionValue class (`new CollectionOf(StringValue::class)`).
 */
final readonly class CollectionOf extends CollectionExpression {
    /**
     * @param TypeExpression|class-string|null $element
     * @param TypeExpression|class-string|null $key
     */
    public function __construct(TypeExpression|string|null $element = null, TypeExpression|string|null $key = null) {
        parent::__construct(CollectionValue::class, $element, $key);
    }
}
