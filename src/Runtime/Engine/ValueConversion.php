<?php
declare(strict_types=1);

namespace Mineflow\Language\Runtime\Engine;

use Mineflow\Language\Diagnostics\Notices;
use Mineflow\Language\Types\DeclaredType;
use Mineflow\Language\Types\Type;
use Mineflow\Language\Types\TypeRegistry;
use Mineflow\Language\Types\UnionType;
use Mineflow\Language\Values\Value;

final readonly class ValueConversion {
    public function __construct(private TypeRegistry $types) {
    }

    public function convert(Type $expected, Value $value, Notices $notices): ?Value {
        if ($expected->accepts($value->type())) {
            return $value;
        }

        $fallback = null;
        $fallbackNotices = null;
        foreach ($expected instanceof UnionType ? $expected->members : [$expected] as $member) {
            if (!$member instanceof DeclaredType) {
                continue;
            }

            $trial = new Notices();
            $converted = $this->types->convertValue($member, $value, $trial);
            if ($converted === null) {
                continue;
            }
            if ($trial->count() === 0) {
                return $converted;
            }
            if ($fallback === null) {
                $fallback = $converted;
                $fallbackNotices = $trial;
            }
        }

        if ($fallback === null || $fallbackNotices === null) {
            return null;
        }

        $notices->merge($fallbackNotices);
        return $fallback;
    }
}
