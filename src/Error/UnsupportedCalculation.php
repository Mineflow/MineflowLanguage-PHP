<?php
declare(strict_types=1);

namespace Mineflow\Language\Error;

use Mineflow\Language\Syntax\BinaryOperator;
use Mineflow\Language\Types\Type;

final class UnsupportedCalculation extends EvaluationError {
    public function __construct(
        public readonly BinaryOperator $operator,
        public readonly Type           $left,
        public readonly Type           $right,
    ) {
        parent::__construct("Unsupported calculation: $left {$operator->value} $right");
    }
}
