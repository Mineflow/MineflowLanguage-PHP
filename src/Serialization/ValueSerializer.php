<?php
declare(strict_types=1);

namespace Mineflow\Language\Serialization;

use Closure;
use Mineflow\Language\Error\DefinitionError;
use Mineflow\Language\Error\InvalidValue;
use Mineflow\Language\Types\TypeRegistry;
use Mineflow\Language\Values\Value;

/**
 * Items are written with their type too, since JSON alone tells neither an empty map from an empty list nor 2.0 from 2.
 */
final readonly class ValueSerializer {
    /**
     * @param array<class-string, ValueCodec<*>> $codecs
     * @param array<int|string, string> $typeAliases
     * @param (Closure(Value): Value)|null $fallback
     */
    private function __construct(
        private TypeRegistry $types,
        private array $codecs,
        private array $typeAliases,
        private ?Closure $fallback,
    ) {
    }

    /**
     * @param list<ValueCodec<*>> $codecs
     * @param array<int|string, string> $typeAliases
     * @param (Closure(Value): Value)|null $fallback
     */
    public static function from(TypeRegistry $types, array $codecs, array $typeAliases = [], ?Closure $fallback = null): self {
        $byClass = [];
        foreach ($codecs as $codec) {
            $class = $codec->valueClass();
            if ($types->find($class) === null) {
                throw new DefinitionError("A codec for $class, whose type the language does not have");
            }
            if (isset($byClass[$class])) {
                throw new DefinitionError("Both ".$byClass[$class]::class." and ".$codec::class." write the values of ".$types->of($class));
            }

            $byClass[$class] = $codec;
        }

        foreach ($typeAliases as $alias => $name) {
            if ($types->named((string)$alias) !== null) {
                throw new DefinitionError("The alias \"$alias\" is already the name of a type");
            }

            $type = $types->named($name) ?? throw new DefinitionError("The alias \"$alias\" stands for an unknown type: $name");
            if (!isset($byClass[$type->class])) {
                throw new DefinitionError("The alias \"$alias\" stands for $name, which has no codec");
            }
        }

        return new self($types, $byClass, $typeAliases, $fallback);
    }

    public function hasCodecFor(Value $value): bool {
        return $this->codecFor($value) !== null;
    }

    /**
     * @return array{type: string, value: mixed}
     * @throws InvalidValue
     */
    public function serialize(Value $value): array {
        $codec = $this->codecFor($value);
        if ($codec === null) {
            if ($this->fallback === null) {
                throw new InvalidValue("No codec writes a value of the type ".$value->type()."; add one with LanguageBuilder::addCodec(), or give a fallback");
            }

            $substitute = ($this->fallback)($value);
            $codec = $this->codecFor($substitute)
                ?? throw new InvalidValue("The fallback gave a value of the type ".$substitute->type().", which no codec writes either");
            $value = $substitute;
        }

        return [
            "type" => $this->types->of($codec->valueClass())->name,
            // @phpstan-ignore argument.type (codecFor() gives only a codec whose valueClass() the value is an instance of)
            "value" => $codec->encode($value, $this),
        ];
    }

    /**
     * @throws InvalidValue
     */
    public function deserialize(mixed $data): Value {
        if (!is_array($data) || !array_key_exists("type", $data) || !array_key_exists("value", $data)) {
            throw new InvalidValue("A saved value is an array of \"type\" and \"value\", not ".json_encode($data));
        }

        $name = $data["type"];
        if (!is_string($name) && !is_int($name)) {
            throw new InvalidValue("The type of a saved value is a name, not ".json_encode($name));
        }

        $name = $this->typeAliases[$name] ?? (string)$name;
        $type = $this->types->named($name) ?? throw new InvalidValue("Unknown type of a saved value: $name");
        $codec = $this->codecs[$type->class] ?? throw new InvalidValue("No codec reads a value of the type $name");
        $value = $codec->decode($data["value"], $this);
        if (!$value instanceof $type->class) {
            throw new InvalidValue($codec::class." made a value of the type {$value->type()} for one of $name");
        }

        return $value;
    }

    /**
     * Interfaces are not taken in the order of class_implements(), which can put one a parent implements before one
     * extending it, but the most specific first, as TypeRegistry orders them.
     * @return ValueCodec<*>|null
     */
    private function codecFor(Value $value): ?ValueCodec {
        foreach ([$value::class, ...array_values(class_parents($value))] as $class) {
            if (isset($this->codecs[$class])) {
                return $this->codecs[$class];
            }
        }

        $interfaces = array_filter(array_keys($this->codecs), static fn(string $class) => interface_exists($class) && $value instanceof $class);
        foreach ($interfaces as $interface) {
            if (array_filter($interfaces, static fn(string $other) => is_subclass_of($other, $interface)) === []) {
                return $this->codecs[$interface];
            }
        }

        return null;
    }
}
