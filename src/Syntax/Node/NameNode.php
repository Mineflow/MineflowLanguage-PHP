<?php
declare(strict_types=1);

namespace Mineflow\Language\Syntax\Node;

/**
 * A property, method, or function name written in the source.
 */
final readonly class NameNode extends Node {
    public function __construct(
        public string $name,
        int           $start,
        int           $end,
    ) {
        parent::__construct($start, $end);
    }
}
