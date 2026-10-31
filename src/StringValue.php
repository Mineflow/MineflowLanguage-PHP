<?php
declare(strict_types=1);

namespace Mineflow\Language;

use Mineflow\Language\Types\Type;

final readonly class StringValue extends Value {
    public function __construct(public string $value) {
        parent::__construct(Type::string());
    }

    public function native(): string {
        return $this->value;
    }
}
