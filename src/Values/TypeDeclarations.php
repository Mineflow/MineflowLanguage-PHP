<?php
declare(strict_types=1);

namespace Mineflow\Language\Values;

use Mineflow\Language\Attribute\DeclareType;
use Mineflow\Language\Error\DefinitionError;
use Mineflow\Language\Types\CollectionType;
use Mineflow\Language\Types\DeclaredType;
use Mineflow\Language\Types\Placeholder;
use Mineflow\Language\Types\PlainType;
use Mineflow\Language\Types\Special;
use Mineflow\Language\Types\Type;
use Mineflow\Language\Types\TypeExpressions;
use Mineflow\Language\Types\TypeResolver;
use ReflectionClass;

/**
 * The types that Value classes and interfaces declare with #[DeclareType]. A type follows from its class alone,
 * so a value knows its type without a language.
 *
 * The type of a CollectionValue class has a key type and an element type. Its declaration decides them with typeHint,
 * or leaves them undecided; a value of the class then has the ones its items have.
 */
final class TypeDeclarations {
    /** @var array<string, array{type: DeclaredType, undecided: list<Placeholder>}|null> */
    private static array $declared = [];
    /** @var array<string, true> */
    private static array $reading = [];

    /**
     * Returns the type of a value of the class: the one the class declares, or else its nearest parent class.
     * The key and element types it leaves undecided are the widest ones.
     * @param class-string $class
     */
    public static function of(string $class): DeclaredType {
        return self::declaration($class)["type"];
    }

    /**
     * Returns the type the class or interface itself declares, or null when it declares none.
     * @param class-string $class
     */
    public static function declaredBy(string $class): ?DeclaredType {
        return self::read($class)["type"] ?? null;
    }

    /**
     * The type arguments the declaration of the class leaves undecided, key first.
     * @param class-string $class
     * @return list<Placeholder>
     */
    public static function undecided(string $class): array {
        return self::declaration($class)["undecided"];
    }

    /**
     * Returns the type of the collection: that of its class, with the type arguments the class leaves undecided
     * taken from its items. The items are read only when one is undecided.
     */
    public static function ofCollection(CollectionValue $collection): CollectionType {
        ["type" => $type, "undecided" => $undecided] = self::declaration($collection::class);
        if (!$type instanceof CollectionType) {
            throw new DefinitionError("The type $type of ".$collection::class." is not one of a collection");
        }
        if ($undecided === []) {
            return $type;
        }

        $items = $collection->items();
        $key = in_array(Placeholder::Key, $undecided, true)
            ? Type::union(...array_map(static fn(string|int $key) => self::of(is_int($key) ? NumberValue::class : StringValue::class), array_keys($items)))
            : $type->key;
        $element = in_array(Placeholder::Element, $undecided, true)
            ? Type::union(...array_values(array_map(static fn(Value $item) => $item->type(), $items)))
            : $type->element;

        return new CollectionType($type->name, $type->class, $key, $element);
    }

    /**
     * @param class-string $class
     * @return array{type: DeclaredType, undecided: list<Placeholder>}
     */
    private static function declaration(string $class): array {
        for ($declaring = $class; $declaring !== false; $declaring = get_parent_class($declaring)) {
            $declaration = self::read($declaring);
            if ($declaration !== null) {
                return $declaration;
            }
        }

        throw new DefinitionError("Missing #[DeclareType] on $class or its parent classes");
    }

    /**
     * @param class-string $class
     * @return array{type: DeclaredType, undecided: list<Placeholder>}|null
     */
    private static function read(string $class): ?array {
        if (array_key_exists($class, self::$declared)) {
            return self::$declared[$class];
        }
        if (!class_exists($class) && !interface_exists($class)) {
            throw new DefinitionError("Unknown class for a type: $class");
        }

        $reflection = new ReflectionClass($class);
        /** @var DeclareType|null $attribute */
        $attribute = ($reflection->getAttributes(DeclareType::class)[0] ?? null)?->newInstance();
        if ($attribute === null) {
            return self::$declared[$class] = null;
        }
        if (!$reflection->isInterface() && !$reflection->isSubclassOf(Value::class)) {
            throw new DefinitionError("#[DeclareType] goes on a Value class or an interface: $class");
        }

        $name = $attribute->name ?? $reflection->getShortName();
        if (preg_match("/^[A-Za-z_][A-Za-z0-9_]*$/D", $name) !== 1) {
            throw new DefinitionError("A type name is a word of letters, digits and _, not starting with a digit: \"$name\"");
        }
        if (Special::tryFrom($name) !== null) {
            throw new DefinitionError("Reserved type name: \"$name\"");
        }

        if (isset(self::$reading[$class])) {
            throw new DefinitionError("The typeHint of the type $name refers to the type itself");
        }
        self::$reading[$class] = true;
        try {
            return self::$declared[$class] = $class === CollectionValue::class || $reflection->isSubclassOf(CollectionValue::class)
                ? self::readCollection($name, $class, $attribute)
                : self::readPlain($name, $class, $attribute);
        } finally {
            unset(self::$reading[$class]);
        }
    }

    /**
     * @param class-string $class
     * @return array{type: DeclaredType, undecided: list<Placeholder>}
     */
    private static function readPlain(string $name, string $class, DeclareType $attribute): array {
        if ($attribute->typeHint !== null) {
            throw new DefinitionError("typeHint gives the key and element types of a collection, and $class holds no items");
        }

        return ["type" => new PlainType($name, $class), "undecided" => []];
    }

    /**
     * Reads the type of a CollectionValue class: its parent class's, with the type arguments typeHint decides.
     * @param class-string $class
     * @return array{type: DeclaredType, undecided: list<Placeholder>}
     */
    private static function readCollection(string $name, string $class, DeclareType $attribute): array {
        $parent = get_parent_class($class);
        if ($parent === false || $class === CollectionValue::class) {
            if ($attribute->typeHint !== null) {
                throw new DefinitionError("typeHint narrows the type of the parent class, and $class has none");
            }

            $key = Type::union(self::of(StringValue::class), self::of(NumberValue::class));
            return ["type" => new CollectionType($name, $class, $key, Type::mixed()), "undecided" => [Placeholder::Key, Placeholder::Element]];
        }

        $parentType = self::of($parent);
        $undecided = self::undecided($parent);
        if (!$parentType instanceof CollectionType) {
            throw new DefinitionError("The type $parentType of $parent is not one of a collection");
        }
        $hint = $attribute->typeHint;
        if ($hint === null) {
            return ["type" => new CollectionType($name, $class, $parentType->key, $parentType->element), "undecided" => $undecided];
        }

        if (!is_a($parentType->class, $hint->class, true)) {
            throw new DefinitionError("The typeHint of $class gives the type arguments of ".TypeDeclarations::of($hint->class)
                .", which its parent class's type $parentType is not");
        }

        $resolver = new class implements TypeResolver {
            public function declared(string $class): DeclaredType {
                return TypeDeclarations::of($class);
            }

            public function placeholder(Placeholder $placeholder): Type {
                throw new DefinitionError("Placeholder::{$placeholder->name} is only available in member declarations.");
            }
        };
        $key = $hint->key === null ? null : TypeExpressions::resolve($hint->key, $resolver);
        $element = $hint->element === null ? null : TypeExpressions::resolve($hint->element, $resolver);
        $narrowed = $parentType->withArguments($key, $element);

        // A type argument the hint gives says something when the parent class leaves it undecided or has another one.
        $addsInformation = static fn(?Type $given, Type $inherited, Placeholder $placeholder) => $given !== null
            && (in_array($placeholder, $undecided, true) || !$given->equals($inherited));
        if (!$addsInformation($key, $parentType->key, Placeholder::Key) && !$addsInformation($element, $parentType->element, Placeholder::Element)) {
            throw new DefinitionError("The typeHint of $class says only what its parent class's type $parentType does; leave it out");
        }

        return [
            "type" => new CollectionType($name, $class, $narrowed->key, $narrowed->element),
            "undecided" => array_values(array_filter(
                $undecided,
                static fn(Placeholder $placeholder) => ($placeholder === Placeholder::Key ? $key : $element) === null,
            )),
        ];
    }
}
