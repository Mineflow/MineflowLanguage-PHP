<?php
declare(strict_types=1);

namespace Mineflow\Language\Error;

use Mineflow\Language\Definition\MemberReference;
use Mineflow\Language\Types\Type;

final class ReturnTypeMismatch extends EvaluationError {
    public function __construct(
        public readonly MemberReference $reference,
        public readonly Type            $expected,
        public readonly Type            $actual,
    ) {
        parent::__construct("result of $reference expects $expected, got $actual");
    }
}
