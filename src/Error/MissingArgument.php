<?php
declare(strict_types=1);

namespace Mineflow\Language\Error;

use Mineflow\Language\Definition\MemberReference;

final class MissingArgument extends ArgumentError {
    public function __construct(MemberReference $reference, public readonly string $parameter) {
        parent::__construct($reference, "Missing argument: $parameter");
    }
}
