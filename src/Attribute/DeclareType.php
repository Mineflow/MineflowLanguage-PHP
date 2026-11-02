<?php
declare(strict_types=1);

namespace Mineflow\Language\Attribute;

use Attribute;
use Mineflow\Language\Types\Expression\CollectionExpression;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class DeclareType {
    /**
     * @param string|null $name The name of the type. Defaults to the short name of the class.
     * @param CollectionExpression|null $typeHint For a CollectionValue class, the key and element types its values
     *     are hinted to have, as its parent class's type with them given (`new ListOf(ItemValue::class)`). One it
     *     leaves out stays as the parent class leaves it; one no class decides is any. A hint is not checked.
     */
    public function __construct(public ?string $name = null, public ?CollectionExpression $typeHint = null) {
    }
}
