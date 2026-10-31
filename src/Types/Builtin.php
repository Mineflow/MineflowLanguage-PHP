<?php
declare(strict_types=1);

namespace Mineflow\Language\Types;

enum Builtin: string implements TypeExpression {
    case String = "string";
    case Number = "number";
    case Boolean = "boolean";
    case Null = "null";
    case List = "list";
    case Map = "map";

    public function resolve(TypeResolver $resolver): Type {
        return $this->type();
    }

    public function type(): NamedType|CollectionType {
        return match ($this) {
            self::String => Type::string(),
            self::Number => Type::number(),
            self::Boolean => Type::boolean(),
            self::Null => Type::null(),
            self::List => Type::list(Type::mixed()),
            self::Map => Type::map(Type::mixed()),
        };
    }
}
