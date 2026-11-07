<?php
declare(strict_types=1);

namespace Mineflow\Language\StandardLibrary;

use Mineflow\Language\Types\CollectionType;
use Mineflow\Language\Types\DeclaredType;
use Mineflow\Language\Types\Type;
use Mineflow\Language\Values\BooleanValue;
use Mineflow\Language\Values\CollectionValue;
use Mineflow\Language\Values\DateTimeValue;
use Mineflow\Language\Values\ListValue;
use Mineflow\Language\Values\MapValue;
use Mineflow\Language\Values\NullValue;
use Mineflow\Language\Values\NumberValue;
use Mineflow\Language\Values\StringValue;
use Mineflow\Language\Values\TypeDeclarations;

/**
 * The types every language has. Their Value classes declare them and their members, as a host's do.
 */
final class StandardTypes {
    /** @var list<class-string> */
    public const array CLASSES = [
        StringValue::class,
        NumberValue::class,
        BooleanValue::class,
        NullValue::class,
        CollectionValue::class,
        ListValue::class,
        MapValue::class,
        DateTimeValue::class,
    ];

    public static function string(): DeclaredType {
        return TypeDeclarations::of(StringValue::class);
    }

    public static function number(): DeclaredType {
        return TypeDeclarations::of(NumberValue::class);
    }

    public static function boolean(): DeclaredType {
        return TypeDeclarations::of(BooleanValue::class);
    }

    public static function null(): DeclaredType {
        return TypeDeclarations::of(NullValue::class);
    }

    public static function datetime(): DeclaredType {
        return TypeDeclarations::of(DateTimeValue::class);
    }

    public static function collection(?Type $element = null, ?Type $key = null): DeclaredType {
        return self::collectionOf(CollectionValue::class, $element, $key);
    }

    public static function list(?Type $element = null): DeclaredType {
        return self::collectionOf(ListValue::class, $element, null);
    }

    public static function map(?Type $element = null, ?Type $key = null): DeclaredType {
        return self::collectionOf(MapValue::class, $element, $key);
    }

    /**
     * @param class-string $class
     */
    private static function collectionOf(string $class, ?Type $element, ?Type $key): DeclaredType {
        $type = TypeDeclarations::of($class);
        assert($type instanceof CollectionType);

        return $type->withArguments($key, $element);
    }
}
