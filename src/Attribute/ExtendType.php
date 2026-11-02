<?php
declare(strict_types=1);

namespace Mineflow\Language\Attribute;

use Attribute;

/**
 * Adds members to a type declared elsewhere with #[DeclareType], for members that need services of the host.
 * Each member takes the value of the type as its first parameter.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class ExtendType {
    /**
     * @param class-string $type The Value class or interface that declares the type.
     */
    public function __construct(public string $type) {
    }
}
