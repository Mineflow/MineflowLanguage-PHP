<?php
declare(strict_types=1);

namespace Mineflow\Language\Error;

use Mineflow\Language\Definition\MemberReference;

final class UndefinedMember extends EvaluationError {
    public function __construct(public readonly MemberReference $reference) {
        parent::__construct("Undefined {$reference->kind->value}: $reference");
    }
}
