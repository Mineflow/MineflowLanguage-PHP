<?php
declare(strict_types=1);

namespace Mineflow\Language\Types\Expression;

use Mineflow\Language\Types\Type;
use Mineflow\Language\Types\TypeExpression;
use Mineflow\Language\Types\TypeResolver;

final readonly class OneOf implements TypeExpression {
    /** @var list<TypeExpression> */
    public array $members;

    public function __construct(TypeExpression ...$members) {
        $this->members = array_values($members);
    }

    public function resolve(TypeResolver $resolver): Type {
        return Type::union(...array_map(static fn(TypeExpression $member) => $member->resolve($resolver), $this->members));
    }
}
