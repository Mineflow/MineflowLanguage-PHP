<?php
declare(strict_types=1);

namespace Mineflow\Language\Syntax\Node;

final readonly class MethodCallNode extends Node {
    /**
     * @param list<Node> $arguments
     */
    public function __construct(
        public Node  $target,
        public Node  $name,
        public array $arguments,
        int          $end,
    ) {
        parent::__construct($target->start, $end);
    }
}
