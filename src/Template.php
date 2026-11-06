<?php
declare(strict_types=1);

namespace Mineflow\Language;

use Mineflow\Language\Error\SyntaxError;
use Mineflow\Language\Syntax\Node\ConcatenationNode;
use Mineflow\Language\Syntax\Node\Node;
use Mineflow\Language\Syntax\Node\StringNode;
use Mineflow\Language\Syntax\Parser;

/**
 * A template source kept with what parsing it gave, so rendering it again does not parse it again.
 * Parsing needs no types, so a template can be made before the language is built. The source is
 * parsed the first time something asks for it, and a syntax error is kept and thrown again as well.
 */
final class Template {
    private Node|SyntaxError|null $parsed = null;
    private ?string $plainText = null;

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

    /**
     * The text the template always renders, or null when it has an embedding to evaluate.
     * @throws SyntaxError
     */
    public function plainText(): ?string {
        $this->node();

        return $this->plainText;
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
            $node = (new Parser())->parseTemplate($this->source);
        } catch (SyntaxError $error) {
            return $error;
        }

        $parts = $node instanceof ConcatenationNode ? $node->parts : [$node];
        $texts = [];
        foreach ($parts as $part) {
            if (!$part instanceof StringNode) {
                return $node;
            }
            $texts[] = $part->text;
        }
        $this->plainText = implode("", $texts);

        return $node;
    }
}
