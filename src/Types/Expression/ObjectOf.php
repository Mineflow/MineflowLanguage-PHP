<?php
declare(strict_types=1);

namespace Mineflow\Language\Types\Expression;

use Mineflow\Language\Types\Type;
use Mineflow\Language\Types\TypeExpression;
use Mineflow\Language\Types\TypeResolver;

/**
 * The language type registered for a PHP class (`new ObjectOf(Player::class)`).
 */
final readonly class ObjectOf implements TypeExpression {
    /**
     * @param class-string $class
     */
    public function __construct(public string $class) {
    }

    public function resolve(TypeResolver $resolver): Type {
        return $resolver->object($this->class);
    }
}
