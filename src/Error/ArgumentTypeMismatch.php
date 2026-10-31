<?php
declare(strict_types=1);

namespace Mineflow\Language\Error;

use Mineflow\Language\Definition\MemberReference;
use Mineflow\Language\Types\Type;

final class ArgumentTypeMismatch extends ArgumentError {
    public function __construct(
        MemberReference        $reference,
        public readonly string $parameter,
        public readonly Type   $expected,
        public readonly Type   $actual,
    ) {
        parent::__construct($reference, "$parameter expects $expected, got $actual");
    }
}
