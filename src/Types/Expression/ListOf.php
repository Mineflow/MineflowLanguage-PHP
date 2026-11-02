<?php
declare(strict_types=1);

namespace Mineflow\Language\Types\Expression;

use Mineflow\Language\Types\TypeExpression;
use Mineflow\Language\Values\ListValue;

final readonly class ListOf extends CollectionExpression {
    /**
     * @param TypeExpression|class-string $element
     */
    public function __construct(TypeExpression|string $element) {
        parent::__construct(ListValue::class, $element, null);
    }
}
