<?php
declare(strict_types=1);

namespace Mineflow\Language\Syntax\Node;

/**
 * A variable referred to by a name written in the source.
 */
final readonly class IdentifierNode extends Node {
    public function __construct(
        public string $name,
        int           $start,
        int           $end,
    ) {
        parent::__construct($start, $end);
    }
}
