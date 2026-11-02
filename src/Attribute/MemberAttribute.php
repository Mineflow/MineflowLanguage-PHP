<?php
declare(strict_types=1);

namespace Mineflow\Language\Attribute;

use Mineflow\Language\Types\TypeExpression;

/**
 * @phpstan-sealed Property|Method|Operator|Index
 */
abstract readonly class MemberAttribute {
    /**
     * @param string|null $name The name used in expressions. Defaults to the PHP method name.
     * @param TypeExpression|class-string|null $type The language type of the return value. Specify it when the PHP return type cannot express it.
     * @param list<string> $aliases Other names that call the same member.
     * @param string $description The description of the member.
     */
    public function __construct(
        public ?string                    $name = null,
        public TypeExpression|string|null $type = null,
        public array                      $aliases = [],
        public string                     $description = "",
    ) {
    }

    /**
     * The name used in expressions, and its aliases.
     * @return non-empty-list<string>
     */
    public function names(string $method): array {
        return [$this->name ?? $method, ...$this->aliases];
    }
}
