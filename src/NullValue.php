<?php
declare(strict_types=1);

namespace Mineflow\Language;

use Mineflow\Language\Types\Type;

final readonly class NullValue extends Value {
    public function __construct() {
        parent::__construct(Type::null());
    }

    public function native(): null {
        return null;
    }
}
