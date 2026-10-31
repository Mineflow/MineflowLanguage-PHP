<?php
declare(strict_types=1);

namespace Mineflow\Language\PhpBinding;

use Mineflow\Language\Attribute\DeclareType;
use Mineflow\Language\Error\DefinitionError;
use Mineflow\Language\Types\NamedType;
use Mineflow\Language\Types\ObjectTypes;
use Mineflow\Language\Types\Type;
use ReflectionClass;

/**
 * Types declared with #[DeclareType], and the objects that implement their members.
 */
final readonly class DeclaredTypes {
    /**
     * @param ObjectTypes $objectTypes The type for each declared PHP class.
     * @param list<array{NamedType, object}> $declarers Pairs of a declared type and the object that implements its members.
     */
    private function __construct(public ObjectTypes $objectTypes, public array $declarers) {
    }

    /**
     * Reads the attributes of classes marked with #[DeclareType]. Type::object() and ObjectTypes check the type name and class (so the check is the same as for types without attributes).
     * @param list<object> $declarers Instances of classes marked with #[DeclareType].
     */
    public static function read(array $declarers): self {
        /** @var list<array{NamedType, object}> $read */
        $read = [];

        foreach ($declarers as $declarer) {
            $attribute = ((new ReflectionClass($declarer))->getAttributes(DeclareType::class)[0] ?? null)?->newInstance()
                ?? throw new DefinitionError("Missing #[DeclareType] on ".$declarer::class);
            if (!class_exists($attribute->class) && !interface_exists($attribute->class)) {
                throw new DefinitionError("Unknown class on #[DeclareType] of ".$declarer::class.": {$attribute->class}");
            }

            $name = $attribute->name ?? (new ReflectionClass($attribute->class))->getShortName();
            $read[] = [Type::object($name, $attribute->class), $declarer];
        }

        return new self(new ObjectTypes(...array_column($read, 0)), $read);
    }
}
