<?php
declare(strict_types=1);

namespace Mineflow\Language\Attribute;

use Attribute;
use Mineflow\Language\Types\TypeExpression;

/**
 * Put on a method called when `target.name` reads a name that is not a property of the type.
 * The method receives the name and returns null if there is no entry.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Index extends MemberAttribute {
    public function __construct(?TypeExpression $type = null, string $description = "") {
        parent::__construct(type: $type, description: $description);
    }
}
