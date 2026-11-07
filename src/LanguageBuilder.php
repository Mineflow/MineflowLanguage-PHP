<?php
declare(strict_types=1);

namespace Mineflow\Language;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
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
    private ?DateTimeZone $timezone = null;
    /** @var (Closure(): DateTimeImmutable)|null */
    private ?Closure $clock = null;

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

    /**
     * Sets the zone of the dates and times now() and datetime() make. Without one, they are in PHP's default zone.
     */
    public function setTimezone(DateTimeZone $timezone): self {
        $this->timezone = $timezone;
        return $this;
    }

    /**
     * @param Closure(): DateTimeImmutable $clock The current moment now() reads instead of the system clock.
     */
    public function setClock(Closure $clock): self {
        $this->clock = $clock;
        return $this;
    }

    public function build(): Language {
        $functions = [new BuiltinFunctions($this->timezone, $this->clock), ...$this->functions];
        $members = AttributeReader::read([...StandardTypes::CLASSES, ...$this->types], $this->extensions, $functions);
        $serializer = ValueSerializer::from(
            $members->catalog->types,
            [...StandardCodecs::all(), ...$this->codecs],
            $this->deserializationAliases,
            $this->serializationFallback,
        );

        return new Language($members, $serializer);
    }
}
