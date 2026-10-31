<?php
declare(strict_types=1);

namespace Mineflow\Language\Syntax\Node;

/**
 * @phpstan-sealed StringNode|NumberNode|NameNode|ComputedNameNode|ConcatenationNode|IdentifierNode|ComputedIdentifierNode|ParenthesizedNode|BinaryExpressionNode|UnaryExpressionNode|PropertyNode|MethodCallNode|FunctionCallNode
 */
abstract readonly class Node {
    public function __construct(
        public int $start,
        public int $end,
    ) {
    }
}
