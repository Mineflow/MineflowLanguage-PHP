<?php
declare(strict_types=1);

namespace Mineflow\Language;

use Mineflow\Language\Error\InvalidValue;
use Mineflow\Language\Types\NamedType;

final readonly class ObjectValue extends Value {
    public function __construct(NamedType $type, public object $value) {
        if ($type->class === null || !$value instanceof $type->class) {
            throw new InvalidValue($value::class." cannot be a value of the type $type.");
        }

        parent::__construct($type);
    }

    public function native(): object {
        return $this->value;
    }
}
