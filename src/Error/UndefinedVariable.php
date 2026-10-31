<?php
declare(strict_types=1);

namespace Mineflow\Language\Error;

final class UndefinedVariable extends EvaluationError {
    public function __construct(public readonly string $name) {
        parent::__construct("Undefined variable: $name");
    }
}
