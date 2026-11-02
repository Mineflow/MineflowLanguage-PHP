<?php
declare(strict_types=1);

namespace Mineflow\Language\Values;

use Mineflow\Language\Attribute\DeclareType;
use Mineflow\Language\Attribute\FromNative;
use Stringable;

#[DeclareType("null")]
readonly class NullValue extends Value implements Stringable {
    #[FromNative]
    public static function fromNative(null $value): self {
        return new self();
    }

    public function native(): null {
        return null;
    }

    public function __toString(): string {
        return "null";
    }
}
