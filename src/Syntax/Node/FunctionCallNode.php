<?php
declare(strict_types=1);

namespace Mineflow\Language\Syntax\Node;

final readonly class FunctionCallNode extends Node {
    /**
     * @param list<Node> $arguments
     */
    public function __construct(
        public Node  $name,
        public array $arguments,
        int          $end,
    ) {
        parent::__construct($name->start, $end);
    }
}
