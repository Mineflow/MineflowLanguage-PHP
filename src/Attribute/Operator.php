<?php
declare(strict_types=1);

namespace Mineflow\Language\Attribute;

use Attribute;
use Mineflow\Language\Syntax\BinaryOperator;
use Mineflow\Language\Types\TypeExpression;

/**
 * Put on a method used as `left symbol right`. The target value is the left operand.
 * When reflected, the target value is the right operand.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Operator extends MemberAttribute {
    /**
     * @param TypeExpression|class-string|null $type
     */
    public function __construct(
        public BinaryOperator      $operator,
        TypeExpression|string|null $type = null,
        string                     $description = "",
        public bool                $reflected = false,
    ) {
        parent::__construct(type: $type, description: $description);
    }
}
