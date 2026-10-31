<?php
declare(strict_types=1);

namespace Mineflow\Language\Syntax\Node;

/**
 * A variable referred to by a name built at runtime (`{which}`).
 */
final readonly class ComputedIdentifierNode extends Node {
    public function __construct(
        public Node $name,
    ) {
        parent::__construct($name->start, $name->end);
    }
}
