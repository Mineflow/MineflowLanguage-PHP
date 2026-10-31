<?php
declare(strict_types=1);

namespace Mineflow\Language\Syntax;

final readonly class Token {
    public function __construct(
        public TokenKind $kind,
        public string    $text,
        public int       $start,
        public int       $end,
    ) {
    }
}
