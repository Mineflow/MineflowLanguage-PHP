<?php
declare(strict_types=1);

namespace Mineflow\Language;

use Mineflow\Language\Definition\Catalog;
use Mineflow\Language\Error\LanguageException;
use Mineflow\Language\Runtime\Engine\Context;
use Mineflow\Language\Runtime\Engine\Evaluator;
use Mineflow\Language\Runtime\Engine\MemberAccess;
use Mineflow\Language\Runtime\Engine\MemberInvoker;
use Mineflow\Language\Runtime\Engine\OperatorResolution;
use Mineflow\Language\Runtime\EvaluationContext;
use Mineflow\Language\Runtime\MemberTable;
use Mineflow\Language\Runtime\Scope;
use Mineflow\Language\Serialization\ValueSerializer;
use Mineflow\Language\Syntax\Node\ConcatenationNode;
use Mineflow\Language\Syntax\Node\Node;
use Mineflow\Language\Syntax\Parser;
use Mineflow\Language\Values\Value;
use Mineflow\Language\Values\ValueMapper;

final readonly class Language {
    public Catalog $catalog;
    public ValueSerializer $serializer;
    private ValueMapper $valueMapper;
    private MemberInvoker $invoker;
    private MemberAccess $memberAccess;
    private OperatorResolution $operatorResolution;
    private Evaluator $evaluator;

    public function __construct(MemberTable $members, ValueSerializer $serializer) {
        $this->catalog = $members->catalog;
        $this->serializer = $serializer;
        $this->valueMapper = new ValueMapper($members->catalog->types);
        $this->invoker = new MemberInvoker();
        $this->memberAccess = new MemberAccess($members, $this->invoker);
        $this->operatorResolution = new OperatorResolution($members, $this->invoker);
        $this->evaluator = new Evaluator();
    }

    public static function create(): self {
        return (new LanguageBuilder())->build();
    }

    /**
     * @param array<string, mixed> $variables
     */
    public function scope(array $variables = []): Scope {
        return Scope::root($this->valueMapper, $variables);
    }

    /**
     * Converts a PHP value to a language value: a Value as it is, an array to a list or a map, and others with #[FromNative].
     */
    public function wrap(mixed $value): Value {
        return $this->valueMapper->wrap($value);
    }

    public function parseExpression(string $source): Node {
        return (new Parser())->parseExpression($source);
    }

    public function parseTemplate(string $source): Node {
        return (new Parser())->parseTemplate($source);
    }

    /**
     * @throws LanguageException
     */
    public function evaluate(string|Expression $expression, ?Scope $scope = null): Value {
        $expression = is_string($expression) ? new Expression($expression) : $expression;
        return $this->evaluator->evaluate($expression->node(), $this->context($scope ?? $this->scope()));
    }

    /**
     * @throws LanguageException
     */
    public function render(string|Template $template, ?Scope $scope = null): string {
        $template = is_string($template) ? new Template($template) : $template;
        $text = $template->plainText();
        if ($text !== null) {
            return $text;
        }

        $node = $template->node();
        if (!$node instanceof ConcatenationNode) {
            $node = new ConcatenationNode([$node], $node->start, $node->end);
        }

        return $this->evaluator->render($node, $this->context($scope ?? $this->scope()));
    }

    /**
     * The value a path of names reaches, read one name after another the way `a.b.c` reads them, or
     * null when a name on it reaches nothing: a variable the scope does not have, or a name the value
     * before it answers with no property, entry or index. Only the path itself is reported this way;
     * what a member reached on it throws reaches the caller as evaluate() would raise it. A name is
     * never evaluated, so `{x}` is only the name `{x}`.
     * @throws LanguageException
     */
    public function lookup(string $path, Scope $scope): ?Value {
        $names = explode(".", $path);
        $first = array_shift($names);
        if (!$scope->has($first)) {
            return null;
        }

        $context = $this->context($scope);
        $value = $scope->get($first);
        foreach ($names as $name) {
            $value = $context->findProperty($value, $name);
            if ($value === null) {
                return null;
            }
        }

        return $value;
    }

    private function context(Scope $scope): EvaluationContext {
        return new Context($scope, $this->valueMapper, $this->memberAccess, $this->operatorResolution);
    }
}
