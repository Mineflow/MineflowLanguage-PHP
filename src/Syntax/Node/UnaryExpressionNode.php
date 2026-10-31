<?php
declare(strict_types=1);

namespace Mineflow\Language\Syntax\Node;

use Mineflow\Language\Syntax\BinaryOperator;

final readonly class UnaryExpressionNode extends Node {
    public function __construct(
        public BinaryOperator $operator,
        public Node           $operand,
        int                   $start,
    ) {
        parent::__construct($start, $operand->end);
    }
}
