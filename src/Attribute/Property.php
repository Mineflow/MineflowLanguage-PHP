<?php
declare(strict_types=1);

namespace Mineflow\Language\Attribute;

use Attribute;

/**
 * Put on a method that is read with `target.name`.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Property extends MemberAttribute {
}
