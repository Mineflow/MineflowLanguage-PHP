<?php
declare(strict_types=1);

namespace Mineflow\Language\Definition;

use JsonSerializable;
use Mineflow\Language\Types\Type;

final readonly class Parameter implements JsonSerializable {
    public function __construct(
        public string $name,
        public Type   $type,
        public bool   $optional = false,
        public bool   $variadic = false,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array {
        return get_object_vars($this);
    }
}
