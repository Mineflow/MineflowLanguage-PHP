<?php
declare(strict_types=1);

namespace Mineflow\Language\Runtime\Engine;

use Closure;
use Mineflow\Language\Error\LanguageException;
use Mineflow\Language\Error\SourceError;
use Mineflow\Language\Runtime\EvaluationContext;
use Mineflow\Language\Runtime\Scope;
use Mineflow\Language\Syntax\BinaryOperator;
use Mineflow\Language\Syntax\Node\BinaryExpressionNode;
use Mineflow\Language\Syntax\Node\ComputedIdentifierNode;
use Mineflow\Language\Syntax\Node\ComputedNameNode;
use Mineflow\Language\Syntax\Node\ConcatenationNode;
use Mineflow\Language\Syntax\Node\FunctionCallNode;
use Mineflow\Language\Syntax\Node\IdentifierNode;
use Mineflow\Language\Syntax\Node\MethodCallNode;
use Mineflow\Language\Syntax\Node\NameNode;
use Mineflow\Language\Syntax\Node\Node;
use Mineflow\Language\Syntax\Node\NumberNode;
use Mineflow\Language\Syntax\Node\ParenthesizedNode;
use Mineflow\Language\Syntax\Node\PropertyNode;
use Mineflow\Language\Syntax\Node\StringNode;
use Mineflow\Language\Syntax\Node\UnaryExpressionNode;
use Mineflow\Language\Values\NumberValue;
use Mineflow\Language\Values\StringValue;
use Mineflow\Language\Values\Value;

final readonly class Evaluator {
    /**
     * @throws LanguageException
     */
    public function evaluate(Node $node, EvaluationContext $context): Value {
        return $this->locate($node, fn() => $this->dispatch($node, $context));
    }

    /**
     * @throws LanguageException
     */
    public function render(ConcatenationNode $template, EvaluationContext $context): string {
        return $this->locate($template, fn() => $this->concatenate($template, $context));
    }

    /**
     * @template T
     * @param Node $node
     * @param Closure(): T $step
     * @return T
     * @throws LanguageException
     */
    private function locate(Node $node, Closure $step): mixed {
        try {
            return $step();
        } catch (SourceError $error) {
            throw $error->at($node->start, $node->end);
        }
    }

    /**
     * @throws LanguageException
     */
    private function dispatch(Node $node, EvaluationContext $context): Value {
        return match ($node::class) {
            StringNode::class => new StringValue($node->text),
            NumberNode::class => new NumberValue($node->value),
            NameNode::class => new StringValue($node->name),
            ComputedNameNode::class => $this->evaluate($node->name, $context),
            ConcatenationNode::class => new StringValue($this->concatenate($node, $context)),
            IdentifierNode::class => $context->scope()->get($node->name),
            ComputedIdentifierNode::class => $this->resolve($this->name($node->name, $context), $context->scope()),
            ParenthesizedNode::class => $this->evaluate($node->expression, $context),
            BinaryExpressionNode::class => $context->operate($node->operator, $this->evaluate($node->left, $context), $this->evaluate($node->right, $context)),
            UnaryExpressionNode::class => $this->evaluatePrefix($node->operator, $this->evaluate($node->operand, $context), $context),
            PropertyNode::class => $context->property($this->evaluate($node->target, $context), $this->name($node->name, $context)),
            MethodCallNode::class => $this->call($this->evaluate($node->target, $context), $node->name, $node->arguments, $context),
            FunctionCallNode::class => $this->call(null, $node->name, $node->arguments, $context),
        };
    }

    /**
     * @throws LanguageException
     */
    private function concatenate(ConcatenationNode $node, EvaluationContext $context): string {
        return implode("", array_map(
            fn(Node $part) => $part instanceof StringNode ? $part->text : (string)$this->evaluate($part, $context),
            $node->parts,
        ));
    }

    /**
     * @throws LanguageException
     */
    private function name(Node $node, EvaluationContext $context): string {
        return match (true) {
            $node instanceof NameNode => $node->name,
            $node instanceof StringNode => $node->text,
            $node instanceof ComputedNameNode => $this->name($node->name, $context),
            $node instanceof ConcatenationNode => $this->concatenate($node, $context),
            default => (string)$this->evaluate($node, $context),
        };
    }

    private function resolve(string $name, Scope $scope): Value {
        $number = NumberNode::parse($name);
        return $number === null ? $scope->get($name) : new NumberValue($number);
    }

    private function evaluatePrefix(BinaryOperator $operator, Value $operand, EvaluationContext $context): Value {
        return $context->operate($operator, new NumberValue(0), $operand);
    }

    /**
     * @param list<Node> $arguments
     * @throws LanguageException
     */
    private function call(?Value $target, Node $nameNode, array $arguments, EvaluationContext $context): Value {
        $name = $this->name($nameNode, $context);
        $values = array_map(fn(Node $n) => $this->evaluate($n, $context), $arguments);

        return $target === null ? $context->callFunction($name, ...$values) : $context->callMethod($target, $name, ...$values);
    }
}
