<?php
declare(strict_types=1);

namespace Mineflow\Language\Attribute;

use Attribute;
use Mineflow\Language\Types\TypeExpression;

#[Attribute(Attribute::TARGET_METHOD)]
final readonly class Entries extends MemberAttribute {
    public function __construct(?TypeExpression $type = null, string $description = "") {
        parent::__construct(type: $type, description: $description);
    }
}
