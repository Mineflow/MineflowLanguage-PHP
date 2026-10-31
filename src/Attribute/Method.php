<?php
declare(strict_types=1);

namespace Mineflow\Language\Attribute;

use Attribute;

/**
 * Put on a method called with `target.name(arguments)`. In a class added as functions, it is called with `name(arguments)`.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Method extends MemberAttribute {
}
