<?php
declare(strict_types=1);

namespace Mineflow\Language\PhpBinding;

use Mineflow\Language\Definition\MemberKind;
use Mineflow\Language\Definition\Parameter;
use Mineflow\Language\Error\DefinitionError;

/**
 * @phpstan-import-type Extractor from PhpMethodCall
 */
final readonly class MethodSignature {
    /**
     * @param list<Parameter> $parameters The language arguments the method takes, in order.
     * @param list<Extractor> $extractors One per PHP parameter, giving what the method receives for it.
     */
    public function __construct(
        public array $parameters,
        public array $extractors,
    ) {
    }

    /**
     * Returns the parameters a member of the kind declares. A kind without language arguments declares none,
     * so the method may only have optional ones, which then always take their defaults.
     * @return list<Parameter>
     */
    public function parametersFor(MemberKind $kind): array {
        if ($kind->takesArguments()) {
            return $this->parameters;
        }

        foreach ($this->parameters as $parameter) {
            if (!$parameter->optional) {
                throw new DefinitionError("No language arguments for {$kind->value}.");
            }
        }

        return [];
    }
}
