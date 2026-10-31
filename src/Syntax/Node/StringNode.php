<?php
declare(strict_types=1);

namespace Mineflow\Language\Syntax\Node;

final readonly class StringNode extends Node {
    public function __construct(
        public string $text,
        int           $start,
        int           $end,
    ) {
        parent::__construct($start, $end);
    }
}
