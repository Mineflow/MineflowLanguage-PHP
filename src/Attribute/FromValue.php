<?php
declare(strict_types=1);

namespace Mineflow\Language\Attribute;

use Attribute;

/**
 * Put on a public static method of a Value class that makes its value from a language value of another type:
 * `fromValue(Value $value, Notices $notices): ?self`. The language uses it where a value of the class is expected
 * and one of another type is given: an argument, or an operand. It returns null when it cannot make one, and adds a
 * notice when what it makes loses something the value had, such as text read as the number 0. The method converts
 * to its own class only; a subclass that is to be converted to declares its own.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class FromValue {
}
