<?php
declare(strict_types=1);

namespace Mineflow\Language\Syntax\Node;

/**
 * A property, method, or function name built at runtime.
 */
final readonly class ComputedNameNode extends Node {
    public function __construct(
        public Node $name,
    ) {
        parent::__construct($name->start, $name->end);
    }
}
