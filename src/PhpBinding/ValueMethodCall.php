<?php
declare(strict_types=1);

namespace Mineflow\Language\PhpBinding;

use Mineflow\Language\Definition\MemberDefinition;
use Mineflow\Language\Error\InternalError;
use Mineflow\Language\Runtime\EvaluationContext;
use Mineflow\Language\Runtime\MemberImplementation;
use Mineflow\Language\Values\Value;

/**
 * A member a Value class declares: its method is called on the target value itself.
 * @phpstan-import-type Extractor from ArgumentExtractors
 */
final readonly class ValueMethodCall implements MemberImplementation {
    /**
     * @param list<Extractor> $extractors
     */
    public function __construct(
        private MemberDefinition $definition,
        private string           $method,
        private array            $extractors,
    ) {
    }

    /**
     * @param list<Value> $arguments
     */
    public function call(?Value $target, array $arguments, EvaluationContext $context): Value {
        if ($target === null) {
            throw new InternalError("Missing the target value of a type member.");
        }

        $phpArguments = [];
        foreach ($this->extractors as $extract) {
            array_push($phpArguments, ...$extract($target, $arguments, $context));
        }

        return $context->wrap($target->{$this->method}(...$phpArguments), $this->definition->resultType($target->type()));
    }
}
