<?php
declare(strict_types=1);

namespace Mineflow\Language\Runtime\Engine;

use Mineflow\Language\Definition\MemberDefinition;
use Mineflow\Language\Definition\MemberKind;
use Mineflow\Language\Definition\MemberReference;
use Mineflow\Language\Error\ArgumentTypeMismatch;
use Mineflow\Language\Error\MissingArgument;
use Mineflow\Language\Error\TooManyArguments;
use Mineflow\Language\Types\Type;
use Mineflow\Language\Values\NumberValue;
use Mineflow\Language\Values\StringValue;
use Mineflow\Language\Values\TypeDeclarations;
use Mineflow\Language\Values\Value;

final readonly class ArgumentBinder {
    /**
     * @param list<Value> $arguments
     * @return list<Value>
     */
    public function bind(MemberReference $reference, MemberDefinition $definition, array $arguments, ?Type $targetType): array {
        $variadic = false;

        foreach ($definition->parameters as $i => $parameter) {
            $expected = $parameter->type->resolvePlaceholders($targetType);
            if ($parameter->variadic) {
                $variadic = true;

                for ($j = $i; $j < count($arguments); $j++) {
                    $arguments[$j] = $this->coerceArgument($expected, $arguments[$j], $parameter->name, $reference);
                }
                break;
            }

            if (!isset($arguments[$i])) {
                if (!$parameter->optional) {
                    throw new MissingArgument($reference, $parameter->name);
                }
            } else {
                $arguments[$i] = $this->coerceArgument($expected, $arguments[$i], $parameter->name, $reference);
            }
        }

        if (!$variadic && count($arguments) > count($definition->parameters)) {
            throw new TooManyArguments($reference, count($arguments));
        }

        return $arguments;
    }

    private function coerceArgument(Type $expected, Value $value, string $name, MemberReference $reference): Value {
        if (!$expected->accepts($value->type())
            && in_array($reference->kind, [MemberKind::Method, MemberKind::Function], true)
            && $value instanceof StringValue && is_numeric($value->value)
            && $expected->accepts(TypeDeclarations::of(NumberValue::class))) {
            return new NumberValue($value->value + 0);
        }

        if (!$expected->accepts($value->type())) {
            throw new ArgumentTypeMismatch($reference, $name, $expected, $value->type());
        }

        return $value;
    }
}
