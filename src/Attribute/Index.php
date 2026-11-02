<?php
declare(strict_types=1);

namespace Mineflow\Language\Attribute;

use Attribute;
use Mineflow\Language\Types\TypeExpression;

/**
 * Put on a method called when `target.name` reads a name that is not a property of the type. The method receives
 * the name, and what it returns is what the name reads, null included. An index a class declares replaces the one
 * of its parent class.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Index extends MemberAttribute {
    /**
     * @param TypeExpression|class-string|null $type
     */
    public function __construct(TypeExpression|string|null $type = null, string $description = "") {
        parent::__construct(type: $type, description: $description);
    }
}
