<?php
declare(strict_types=1);

namespace Mineflow\Language\Definition;

enum MemberKind: string {
    /** target.name */
    case Property = "property";
    /** target.name(arguments) */
    case Method = "method";
    /** name(arguments) */
    case Function = "function";
    /** left symbol right */
    case Operator = "operator";
    /** left symbol right */
    case ReflectedOperator = "reflected operator";
    case Index = "index";

    public function takesArguments(): bool {
        return match ($this) {
            self::Property => false,
            self::Method, self::Function, self::Operator, self::ReflectedOperator, self::Index => true,
        };
    }
}
