<?php
declare(strict_types=1);

namespace Mineflow\Language\Syntax\Node;

final readonly class PropertyNode extends Node {
    public function __construct(
        public Node $target,
        public Node $name,
    ) {
        parent::__construct($target->start, $name->end);
    }
}
