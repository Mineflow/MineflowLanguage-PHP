<?php
declare(strict_types=1);

namespace Mineflow\Language\Error;

use Mineflow\Language\Definition\MemberReference;

final class TooManyArguments extends ArgumentError {
    public function __construct(MemberReference $reference, public readonly int $givenCount) {
        parent::__construct($reference, "Too many arguments for $reference");
    }
}
