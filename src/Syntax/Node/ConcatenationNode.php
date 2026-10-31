<?php
declare(strict_types=1);

namespace Mineflow\Language\Syntax\Node;

final readonly class ConcatenationNode extends Node {
    /**
     * @param list<Node> $parts
     */
    public function __construct(
        public array $parts,
        int          $start,
        int          $end,
    ) {
        parent::__construct($start, $end);
    }
}
