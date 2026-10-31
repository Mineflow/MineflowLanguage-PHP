<?php
declare(strict_types=1);

namespace Mineflow\Language\Syntax\Node;

use Mineflow\Language\Syntax\BinaryOperator;

final readonly class BinaryExpressionNode extends Node {
    public function __construct(
        public Node           $left,
        public BinaryOperator $operator,
        public Node           $right,
    ) {
        parent::__construct($left->start, $right->end);
    }
}
