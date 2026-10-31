<?php
declare(strict_types=1);

namespace Mineflow\Language\Runtime;

use Mineflow\Language\Value;

interface MemberImplementation {
    /**
     * @param list<Value> $arguments
     */
    public function call(?Value $target, array $arguments, EvaluationContext $context): Value;
}
