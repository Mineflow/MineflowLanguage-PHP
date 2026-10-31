<?php
declare(strict_types=1);

namespace Mineflow\Language;

use Mineflow\Language\Types\Type;

final readonly class NumberValue extends Value {
    public function __construct(public int|float $value) {
        parent::__construct(Type::number());
    }

    public function native(): int|float {
        return $this->value;
    }
}
