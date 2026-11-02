<?php
declare(strict_types=1);

namespace Mineflow\Language\PhpBinding;

use Mineflow\Language\Definition\MemberDefinition;
use Mineflow\Language\Runtime\EvaluationContext;
use Mineflow\Language\Runtime\MemberImplementation;
use Mineflow\Language\Values\Value;

/**
 * A member of an object added to the language, an extension or a class of functions: its method is called on that
 * object, and a type member receives the target value as its first parameter.
 * @phpstan-import-type Extractor from ArgumentExtractors
 */
final readonly class ObjectMethodCall implements MemberImplementation {
    /**
     * @param list<Extractor> $extractors
     */
    public function __construct(
        private MemberDefinition $definition,
        private object           $object,
        private string           $method,
        private array            $extractors,
    ) {
    }

    /**
     * @param list<Value> $arguments
     */
    public function call(?Value $target, array $arguments, EvaluationContext $context): Value {
        $phpArguments = [];
        foreach ($this->extractors as $extract) {
            array_push($phpArguments, ...$extract($target, $arguments, $context));
        }

        return $context->wrap($this->object->{$this->method}(...$phpArguments), $this->definition->resultType($target?->type()));
    }
}
