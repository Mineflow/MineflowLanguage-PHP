<?php
declare(strict_types=1);

namespace Mineflow\Language\Types;

use Mineflow\Language\Error\DefinitionError;
use Mineflow\Language\Values\TypeDeclarations;

/**
 * The type of a CollectionValue class, with the types of its keys and of its items.
 * @internal Hosts get it as a DeclaredType.
 */
final readonly class CollectionType extends DeclaredType {
    /**
     * @param class-string $class
     */
    public function __construct(string $name, string $class, public Type $key, public Type $element) {
        parent::__construct($name, $class);
    }

    /**
     * The same type with the given key and element types, each of which this type has to accept.
     */
    public function withArguments(?Type $key = null, ?Type $element = null): self {
        foreach (["key" => [$this->key, $key], "element" => [$this->element, $element]] as $what => [$own, $given]) {
            if ($given !== null && !$given instanceof PlaceholderType && !$own->accepts($given)) {
                throw new DefinitionError("The $what type of $this is $own, which does not accept $given");
            }
        }

        return new self($this->name, $this->class, $key ?? $this->key, $element ?? $this->element);
    }

    public function equals(Type $other): bool {
        return parent::equals($other) && $other instanceof self && $this->key->equals($other->key) && $this->element->equals($other->element);
    }

    protected function acceptsSingle(Type $actual): bool {
        return parent::acceptsSingle($actual) && $actual instanceof self
            && $this->key->accepts($actual->key) && $this->element->accepts($actual->element);
    }

    public function resolvePlaceholders(?Type $targetType): Type {
        return new self($this->name, $this->class, $this->key->resolvePlaceholders($targetType), $this->element->resolvePlaceholders($targetType));
    }

    /**
     * Writes the type arguments its declaration leaves undecided, unless both are what any value of the class has.
     */
    public function __toString(): string {
        $any = TypeDeclarations::of($this->class);
        if ($this->equals($any)) {
            return $this->name;
        }

        $shown = [];
        foreach (TypeDeclarations::undecided($this->class) as $placeholder) {
            $shown[] = $placeholder === Placeholder::Key ? $this->key : $this->element;
        }
        return $shown === [] ? $this->name : "$this->name<".implode(",", $shown).">";
    }
}
