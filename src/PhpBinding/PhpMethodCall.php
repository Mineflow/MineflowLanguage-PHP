<?php
declare(strict_types=1);

namespace Mineflow\Language\PhpBinding;

use Closure;
use Mineflow\Language\Definition\MemberDefinition;
use Mineflow\Language\Runtime\EvaluationContext;
use Mineflow\Language\Runtime\MemberImplementation;
use Mineflow\Language\Value;

/**
 * @phpstan-type Extractor Closure(?Value, list<Value>, EvaluationContext): list<mixed>
 */
final readonly class PhpMethodCall implements MemberImplementation {
    /**
     * @param list<Extractor> $extractors
     */
    public function __construct(
        private MemberDefinition $definition,
        private object           $instance,
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

        return $context->wrap($this->instance->{$this->method}(...$phpArguments), $this->definition->resultType($target?->type));
    }
}
