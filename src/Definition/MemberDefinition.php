<?php
declare(strict_types=1);

namespace Mineflow\Language\Definition;

use JsonSerializable;
use Mineflow\Language\Types\Type;

final readonly class MemberDefinition implements JsonSerializable {
    /**
     * @param list<Parameter> $parameters
     */
    public function __construct(
        public Type   $returns,
        public array  $parameters = [],
        public string $description = "",
    ) {
    }

    public function resultType(?Type $targetType): Type {
        return $this->returns->resolvePlaceholders($targetType);
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array {
        return get_object_vars($this);
    }
}
