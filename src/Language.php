<?php
declare(strict_types=1);

namespace Mineflow\Language;

use Mineflow\Language\Definition\Catalog;
use Mineflow\Language\Diagnostics\Notices;
use Mineflow\Language\Error\LanguageException;
use Mineflow\Language\Runtime\Engine\ArgumentBinder;
use Mineflow\Language\Runtime\Engine\Context;
use Mineflow\Language\Runtime\Engine\Evaluator;
use Mineflow\Language\Runtime\Engine\MemberAccess;
use Mineflow\Language\Runtime\Engine\MemberInvoker;
use Mineflow\Language\Runtime\Engine\OperatorResolution;
use Mineflow\Language\Runtime\Engine\ValueConversion;
use Mineflow\Language\Runtime\EvaluationContext;
use Mineflow\Language\Runtime\MemberTable;
use Mineflow\Language\Runtime\Scope;
use Mineflow\Language\Serialization\ValueSerializer;
use Mineflow\Language\Syntax\Node\Node;
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
        $conversion = new ValueConversion($members->catalog->types);
        $this->invoker = new MemberInvoker(new ArgumentBinder($conversion));
        $this->memberAccess = new MemberAccess($members, $this->invoker);
        $this->operatorResolution = new OperatorResolution($members, $this->invoker, $conversion);
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

    /**
     * @throws LanguageException
     */
    public function evaluate(string|Expression $expression, ?Scope $scope = null, ?Notices $notices = null): Value {
        $expression = is_string($expression) ? new Expression($expression) : $expression;
        return $this->evaluateNode($expression->node(), $scope, $notices);
    }

    /**
     * @throws LanguageException
     */
    public function render(string|Template $template, ?Scope $scope = null, ?Notices $notices = null): string {
        $template = is_string($template) ? new Template($template) : $template;
        $text = $template->plainText();
        return $text ?? (string)$this->evaluateNode($template->node(), $scope, $notices);
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

        $context = $this->context($scope, new Notices());
        $value = $scope->get($first);
        foreach ($names as $name) {
            $value = $context->findProperty($value, $name);
            if ($value === null) {
                return null;
            }
        }

        return $value;
    }

    /**
     * @throws LanguageException
     */
    private function evaluateNode(Node $node, ?Scope $scope, ?Notices $notices): Value {
        return $this->evaluator->evaluate($node, $this->context($scope ?? $this->scope(), $notices ?? new Notices()));
    }

    private function context(Scope $scope, Notices $notices): EvaluationContext {
        return new Context($scope, $notices, $this->valueMapper, $this->memberAccess, $this->operatorResolution);
    }
}
