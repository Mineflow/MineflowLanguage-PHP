<?php
declare(strict_types=1);

namespace Mineflow\Language\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class DeclareType {
    /**
     * @param class-string $class The PHP class this type represents.
     * @param string|null $name The display name of the type. Defaults to the name of $class.
     */
    public function __construct(public string $class, public ?string $name = null) {
    }
}
