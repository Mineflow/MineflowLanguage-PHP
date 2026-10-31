<?php
declare(strict_types=1);

namespace Mineflow\Language\Error;

use RuntimeException;
use Throwable;

abstract class SourceError extends RuntimeException implements LanguageException {
    public function __construct(
        string       $message,
        ?Throwable   $previous = null,
        private ?int $start = null,
        private ?int $end = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function start(): ?int {
        return $this->start;
    }

    public function end(): ?int {
        return $this->end;
    }

    public function at(int $start, int $end): static {
        if ($this->start === null) {
            $this->start = $start;
            $this->end = $end;
        }
        return $this;
    }
}
