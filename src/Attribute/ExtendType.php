<?php
declare(strict_types=1);

namespace Mineflow\Language\Attribute;

use Attribute;
use Mineflow\Language\Types\Builtin;

/**
 * Adds members to a type declared elsewhere: a built-in type or a type registered with #[DeclareType].
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class ExtendType {
    /**
     * @param Builtin|class-string $type
     */
    public function __construct(public Builtin|string $type) {
    }
}
