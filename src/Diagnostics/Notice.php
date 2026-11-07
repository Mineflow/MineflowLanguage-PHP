<?php
declare(strict_types=1);

namespace Mineflow\Language\Diagnostics;

/**
 * Something the evaluation went on past although the source may not mean it, such as text read as the number 0.
 * The range is that of the innermost part of the source being evaluated when it was noticed.
 */
final readonly class Notice {
    public function __construct(
        public string $message,
        public ?int   $start = null,
        public ?int   $end = null,
    ) {
    }
}
