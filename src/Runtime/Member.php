<?php
declare(strict_types=1);

namespace Mineflow\Language\Runtime;

use Mineflow\Language\Definition\Declaration;

final readonly class Member {
    public function __construct(
        public Declaration          $declaration,
        public MemberImplementation $implementation,
    ) {
    }
}
