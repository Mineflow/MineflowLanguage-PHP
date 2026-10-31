<?php
declare(strict_types=1);

namespace Mineflow\Language\Runtime;

use Mineflow\Language\Definition\Catalog;
use Mineflow\Language\Error\LanguageException;
use Mineflow\Language\Format\DefaultValueFormatter;
use Mineflow\Language\Format\ValueFormatter;
use Mineflow\Language\Syntax\Node\ConcatenationNode;
use Mineflow\Language\Syntax\Node\Node;
use Mineflow\Language\Values\Value;

final readonly class Interpreter {
    public ValueMapper $valueMapper;
    public MemberAccess $memberAccess;
    public OperatorResolution $operatorResolution;
    private Evaluator $evaluator;

    public function __construct(Catalog $catalog, public ValueFormatter $formatter = new DefaultValueFormatter()) {
        $this->valueMapper = new ValueMapper($catalog->objectTypes);
        $invoker = new MemberInvoker();
        $this->memberAccess = new MemberAccess($catalog, $invoker);
        $this->operatorResolution = new OperatorResolution($catalog, $invoker);
        $this->evaluator = new Evaluator();
    }

    /**
     * @param array<string, mixed> $variables
     */
    public function scope(array $variables = []): Scope {
        return Scope::root($this->valueMapper, $variables);
    }

    public function context(Scope $scope): EvaluationContext {
        return new EvaluationContext($scope, $this);
    }

    /**
     * @throws LanguageException
     */
    public function evaluate(Node $node, Scope $scope): Value {
        return $this->evaluator->evaluate($node, $this->context($scope));
    }

    /**
     * @throws LanguageException
     */
    public function render(ConcatenationNode $template, Scope $scope): string {
        return $this->evaluator->render($template, $this->context($scope));
    }
}
