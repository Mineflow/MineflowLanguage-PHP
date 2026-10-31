<?php
declare(strict_types=1);

namespace Mineflow\Language\Error;

use Mineflow\Language\Syntax\BinaryOperator;

final class DivisionByZero extends EvaluationError {
    public function __construct(public readonly BinaryOperator $operator) {
        parent::__construct("Division by zero");
    }
}
