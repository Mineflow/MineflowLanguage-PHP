<?php
declare(strict_types=1);

namespace Mineflow\Language\Types;

interface TypeResolver {
    public function object(string $class): Type;

    public function placeholder(Placeholder $placeholder): Type;
}
