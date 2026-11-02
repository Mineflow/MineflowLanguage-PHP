<?php
declare(strict_types=1);

namespace Mineflow\Language\Attribute;

use Attribute;
use Mineflow\Language\Types\TypeExpression;

#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class Param {
    /**
     * @param TypeExpression|class-string $type
     */
    public function __construct(public TypeExpression|string $type) {
    }
}
