<?php
declare(strict_types=1);

namespace Mineflow\Language;

use Closure;
use Mineflow\Language\Error\DefinitionError;
use Mineflow\Language\PhpBinding\AttributeReader;
use Mineflow\Language\Serialization\ValueCodec;
use Mineflow\Language\Serialization\ValueSerializer;
use Mineflow\Language\StandardLibrary\BuiltinFunctions;
use Mineflow\Language\StandardLibrary\StandardCodecs;
use Mineflow\Language\StandardLibrary\StandardTypes;
use Mineflow\Language\Values\Value;

final class LanguageBuilder {
    /** @var list<class-string> */
    private array $types = [];
    /** @var list<object> */
    private array $extensions = [];
    /** @var list<object> */
    private array $functions = [];
    /** @var list<ValueCodec<*>> */
    private array $codecs = [];
    /** @var array<int|string, string> */
    private array $deserializationAliases = [];
    /** @var (Closure(Value): Value)|null */
    private ?Closure $serializationFallback = null;

    /**
     * Adds the type a Value class or interface declares with #[DeclareType].
     * @param class-string $class
     */
    public function addType(string $class): self {
        $this->types[] = $class;
        return $this;
    }

    public function addExtension(object $extension): self {
        $this->extensions[] = $extension;
        return $this;
    }

    public function addFunctions(object $functions): self {
        $this->functions[] = $functions;
        return $this;
    }

    /**
     * @param ValueCodec<*> $codec
     */
    public function addCodec(ValueCodec $codec): self {
        $this->codecs[] = $codec;
        return $this;
    }

    /**
     * @param array<int|string, string> $aliases To the name of the type each stands for.
     */
    public function addDeserializationAliases(array $aliases): self {
        foreach ($aliases as $alias => $name) {
            if (isset($this->deserializationAliases[$alias])) {
                throw new DefinitionError("The alias \"$alias\" already stands for {$this->deserializationAliases[$alias]}");
            }

            $this->deserializationAliases[$alias] = $name;
        }
        return $this;
    }

    /**
     * @param Closure(Value): Value $fallback
     */
    public function setSerializationFallback(Closure $fallback): self {
        $this->serializationFallback = $fallback;
        return $this;
    }

    public function build(): Language {
        $members = AttributeReader::read([...StandardTypes::CLASSES, ...$this->types], $this->extensions, [new BuiltinFunctions(), ...$this->functions]);
        $serializer = ValueSerializer::from(
            $members->catalog->types,
            [...StandardCodecs::all(), ...$this->codecs],
            $this->deserializationAliases,
            $this->serializationFallback,
        );

        return new Language($members, $serializer);
    }
}
