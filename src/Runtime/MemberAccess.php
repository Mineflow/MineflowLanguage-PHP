<?php
declare(strict_types=1);

namespace Mineflow\Language\Runtime;

use Mineflow\Language\Definition\Catalog;
use Mineflow\Language\Definition\MemberReference;
use Mineflow\Language\Error\InternalError;
use Mineflow\Language\Error\UndefinedMember;
use Mineflow\Language\Types\CollectionType;
use Mineflow\Language\Values\CollectionValue;
use Mineflow\Language\Values\ListValue;
use Mineflow\Language\Values\MapValue;
use Mineflow\Language\Values\NullValue;
use Mineflow\Language\Values\StringValue;
use Mineflow\Language\Values\Value;

/**
 * Resolves `value.name`, looking in this order:
 *
 * 1. A property declared on the value's type
 * 2. The entry with that name (an element, for collections)
 * 3. The property with the same name on each entry
 *
 * For object types with #[Entries], collection properties (such as `length`) are looked up after 1.
 * Step 2 calls #[Index]. Without #[Index], it looks for the entry with that name.
 *
 * `value.name(arguments)` calls a method of the value's type. A method that an object type with #[Entries] lacks
 * is called on its collection of entries, if the collection has it.
 */
final readonly class MemberAccess {
    public function __construct(private Catalog $catalog, private MemberInvoker $invoker) {
    }

    /**
     * @param list<Value> $arguments
     */
    public function callMethod(Value $target, string $name, array $arguments, EvaluationContext $context): Value {
        $reference = MemberReference::method($target->type->name, $name);
        $entriesType = $this->entriesType($target->type->name);
        if ($entriesType !== null && $this->catalog->declaration($reference) === null
            && $this->catalog->declaration(MemberReference::method($entriesType->name, $name)) !== null) {
            return $this->callMethod($this->entries($target, $context) ?? $target, $name, $arguments, $context);
        }

        return $this->invoke($reference, $target, $arguments, $context);
    }

    /**
     * @param list<Value> $arguments
     */
    public function callFunction(string $name, array $arguments, EvaluationContext $context): Value {
        return $this->invoke(MemberReference::function($name), null, $arguments, $context);
    }

    public function find(Value $value, string $name, EvaluationContext $context): ?Value {
        $typeName = $value->type->name;
        $declared = $this->catalog->declaration(MemberReference::property($typeName, $name));
        if ($declared !== null) {
            return $this->invoker->invoke($declared, $value, [], $context);
        }

        if ($value instanceof CollectionValue) {
            return $value->item($name) ?? $this->pluck($value, $name, $context);
        }

        $entriesType = $this->entriesType($typeName);
        $shared = $entriesType === null ? null : $this->catalog->declaration(MemberReference::property($entriesType->name, $name));
        if ($shared !== null) {
            return $this->invoker->invoke($shared, $this->entries($value, $context), [], $context);
        }

        $index = $this->catalog->declaration(MemberReference::index($typeName));
        $entry = $index === null ? null : $this->invoker->invoke($index, $value, [new StringValue($name)], $context);
        if ($entry !== null && !$entry instanceof NullValue) {
            return $entry;
        }

        $entries = $this->entries($value, $context);
        if ($entries === null) {
            return null;
        }

        return ($index === null ? $entries->item($name) : null) ?? $this->pluck($entries, $name, $context);
    }

    /**
     * Gets the same property from each element, keeping keys for a map. Returns null if any element lacks it.
     */
    public function pluck(CollectionValue $value, string $name, EvaluationContext $context): ?CollectionValue {
        $items = [];
        foreach ($value->items() as $key => $item) {
            $result = $this->find($item, $name, $context);
            if ($result === null) {
                return null;
            }

            $items[$key] = $result;
        }

        return $value instanceof ListValue ? new ListValue(array_values($items)) : new MapValue($items);
    }

    private function entriesType(string $typeName): ?CollectionType {
        $returns = $this->catalog->definition(MemberReference::entries($typeName))?->returns;
        return $returns instanceof CollectionType ? $returns : null;
    }

    public function entries(Value $value, EvaluationContext $context): ?CollectionValue {
        if ($value instanceof CollectionValue) {
            return $value;
        }

        $declared = $this->catalog->declaration(MemberReference::entries($value->type->name));
        if ($declared === null) {
            return null;
        }

        $entries = $this->invoker->invoke($declared, $value, [], $context);
        return $entries instanceof CollectionValue ? $entries : throw new InternalError("The entries of $value->type are a $entries->type.");
    }

    /**
     * @param list<Value> $arguments
     */
    private function invoke(MemberReference $reference, ?Value $target, array $arguments, EvaluationContext $context): Value {
        $declaration = $this->catalog->declaration($reference) ?? throw new UndefinedMember($reference);
        return $this->invoker->invoke($declaration, $target, $arguments, $context);
    }
}
