<?php
declare(strict_types=1);

namespace Mineflow\Language;

use Mineflow\Language\Definition\Catalog;
use Mineflow\Language\Error\LanguageException;
use Mineflow\Language\Format\DefaultValueFormatter;
use Mineflow\Language\Format\ValueFormatter;
use Mineflow\Language\Runtime\Interpreter;
use Mineflow\Language\Syntax\Node\ConcatenationNode;
use Mineflow\Language\Syntax\Node\Node;
use Mineflow\Language\Syntax\Parser;

final readonly class Language {
    public Catalog $catalog;
    private Interpreter $interpreter;

    public function __construct(Catalog $catalog, ValueFormatter $formatter = new DefaultValueFormatter()) {
        $this->catalog = $catalog;
        $this->interpreter = new Interpreter($catalog, $formatter);
    }

    public static function create(): self {
        return (new LanguageBuilder())->build();
    }

    /**
     * @param array<string, mixed> $variables
     */
    public function scope(array $variables = []): Scope {
        return $this->interpreter->scope($variables);
    }

    /**
     * Converts a PHP value to a language value. An object becomes a value of the type declared for its class.
     */
    public function wrap(mixed $value): Value {
        return $this->interpreter->valueMapper->wrap($value);
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
    public function evaluate(string|Node $expression, ?Scope $scope = null): Value {
        $node = is_string($expression) ? $this->parseExpression($expression) : $expression;
        return $this->interpreter->evaluate($node, $scope ?? $this->scope());
    }

    /**
     * @throws LanguageException
     */
    public function render(string|Node $template, ?Scope $scope = null): string {
        $node = is_string($template) ? $this->parseTemplate($template) : $template;
        if (!$node instanceof ConcatenationNode) {
            $node = new ConcatenationNode([$node], $node->start, $node->end);
        }

        return $this->interpreter->render($node, $scope ?? $this->scope());
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

        $context = $this->interpreter->context($scope);
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
    public function text(Value $value): string {
        return $this->interpreter->context($this->scope())->text($value);
    }
}
