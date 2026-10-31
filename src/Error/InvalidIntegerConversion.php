<?php
declare(strict_types=1);

namespace Mineflow\Language\Error;

final class InvalidIntegerConversion extends EvaluationError {
    public function __construct(public readonly float $actual) {
        parent::__construct("Expected an integer within the PHP integer range.");
    }
}
