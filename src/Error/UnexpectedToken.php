<?php
declare(strict_types=1);

namespace Mineflow\Language\Error;

final class UnexpectedToken extends SyntaxError {
    public function __construct(
        string                 $message,
        public readonly string $expected,
        public readonly string $actual,
        int                    $start,
        int                    $end,
    ) {
        parent::__construct($message, start: $start, end: $end);
    }
}
