<?php
declare(strict_types=1);

namespace Mineflow\Language\Values;

use Mineflow\Language\Error\InvalidValue;
use Mineflow\Language\StandardLibrary\StandardTypes;
use Mineflow\Language\Types\CollectionType;
use Mineflow\Language\Types\Type;
use Mineflow\Language\Types\TypeRegistry;
use Mineflow\Language\Types\UnionType;

/**
 * Converts PHP values to language values: a Value as it is, a PHP array to a list or a map, and any other PHP value
 * with the #[FromNative] method for its PHP type.
 */
final readonly class ValueMapper {
    private TypeRegistry $conversionRegistry;

    /**
     * @param TypeRegistry|null $registry The types a Value may be of, and the conversions of PHP values. null
     *     takes a Value of any type and converts with those of the standard types, as a Value class does that
     *     converts the PHP data it holds into its items.
     */
    public function __construct(private ?TypeRegistry $registry = null) {
        $this->conversionRegistry = $registry ?? new TypeRegistry(StandardTypes::CLASSES);
    }

    public function wrap(mixed $value, ?Type $expected = null): Value {
        if ($value instanceof Value) {
            $type = $value->type();
            if ($this->registry !== null && $this->registry->find($type->class)?->name !== $type->name) {
                throw new InvalidValue("Unregistered type: $type");
            }

            return $value;
        }

        if (is_array($value)) {
            return $this->collection($value, $expected);
        }

        return $this->conversionRegistry->convertNative($value) ?? throw new InvalidValue(is_object($value)
            ? "No language value for a PHP object of ".$value::class."; pass a Value of its type, or declare #[FromNative] for it"
            : "No language value for a PHP ".get_debug_type($value));
    }

    /**
     * Converts a PHP array to a list or a map: the one the expected type names, or else the one its keys make.
     * @param array<mixed> $value
     */
    private function collection(array $value, ?Type $expected): CollectionValue {
        $candidates = array_values(array_filter(
            $expected instanceof UnionType ? $expected->members : [$expected],
            static fn(?Type $type) => $type instanceof CollectionType,
        ));
        $collectionType = count($candidates) === 1 ? $candidates[0] : null;
        $class = $collectionType?->class;
        $asList = $class !== null && is_a($class, ListValue::class, true);
        $asMap = $class !== null && is_a($class, MapValue::class, true);
        if ($asList && !array_is_list($value)) {
            throw new InvalidValue("Expected a PHP list, got an associative array.");
        }

        $element = $collectionType?->element;
        $items = array_map(fn($item) => $this->wrap($item, $element), $value);
        return $asMap || (!$asList && !array_is_list($items)) ? new MapValue($items, $element) : new ListValue(array_values($items), $element);
    }
}
