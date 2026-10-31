<?php
declare(strict_types=1);

namespace Mineflow\Language\Error;

use Mineflow\Language\Definition\MemberReference;

abstract class ArgumentError extends EvaluationError {
    public function __construct(public readonly MemberReference $reference, string $message) {
        parent::__construct($message);
    }
}
