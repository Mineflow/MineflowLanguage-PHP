<?php
declare(strict_types=1);

namespace Mineflow\Language;

use Mineflow\Language\Types\Type;

final readonly class BooleanValue extends Value {
    public function __construct(public bool $value) {
        parent::__construct(Type::boolean());
    }

    public function native(): bool {
        return $this->value;
    }
}
