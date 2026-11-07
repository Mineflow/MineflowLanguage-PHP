<?php
declare(strict_types=1);

namespace Mineflow\Language;

use Mineflow\Language\Error\SyntaxError;
use Mineflow\Language\Syntax\Node\Node;
use Mineflow\Language\Syntax\Parser;

/**
 * An expression source kept with what parsing it gave, so evaluating it again does not parse it again.
 * Parsing needs no types, so an expression can be made before the language is built. The source is
 * parsed the first time something asks for it, and a syntax error is kept and thrown again as well.
 */
final class Expression {
    private Node|SyntaxError|null $parsed = null;

    public function __construct(public readonly string $source) {
    }

    /**
     * @throws SyntaxError
     */
    public function node(): Node {
        $parsed = $this->parsed ??= $this->parse();
        if ($parsed instanceof SyntaxError) {
            throw $parsed;
        }

        return $parsed;
    }

    public function syntaxError(): ?SyntaxError {
        try {
            $this->node();
            return null;
        } catch (SyntaxError $error) {
            return $error;
        }
    }

    private function parse(): Node|SyntaxError {
        try {
            return (new Parser())->parseExpression($this->source);
        } catch (SyntaxError $error) {
            return $error;
        }
    }
}
