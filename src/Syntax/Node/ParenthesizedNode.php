<?php
declare(strict_types=1);

namespace Mineflow\Language\Syntax\Node;

/**
 * An expression enclosed in parentheses.
 */
final readonly class ParenthesizedNode extends Node {
    public function __construct(
        public Node $expression,
        int         $start,
        int         $end,
    ) {
        parent::__construct($start, $end);
    }
}
