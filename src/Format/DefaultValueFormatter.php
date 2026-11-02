<?php
declare(strict_types=1);

namespace Mineflow\Language\Format;

use Mineflow\Language\Error\ExternalFailure;
use Mineflow\Language\Error\NoTextRepresentation;
use Mineflow\Language\Values\Value;
use Stringable;

/**
 * Writes a value as the text its class gives as a Stringable. A value of a class that gives none has no text.
 */
final readonly class DefaultValueFormatter implements ValueFormatter {
    public function format(Value $value): string {
        return $value instanceof Stringable
            ? ExternalFailure::guard(static fn() => (string)$value)
            : throw new NoTextRepresentation($value->type());
    }
}
