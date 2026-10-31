<?php
declare(strict_types=1);

namespace Mineflow\Language\Attribute;

use Attribute;

/**
 * Passes the EvaluationContext instead of an argument from the expression.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class FromContext {
}
