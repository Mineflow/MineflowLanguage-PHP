<?php
declare(strict_types=1);

namespace Mineflow\Language\Attribute;

use Attribute;

/**
 * Put on a public static method of a Value class that makes its value from a PHP value. The PHP type of its one
 * parameter is what it converts, and its return type the Value class it makes. A PHP value of that type then
 * becomes such a value wherever the language takes one: in a scope, as the result of a member, and in a member
 * parameter of that PHP type, which receives the value's native().
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class FromNative {
}
