<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests;

use Mineflow\Language\Error\UnexpectedToken;
use Mineflow\Language\Language;
use Mineflow\Language\Runtime\Scope;
use Mineflow\Language\Template;
use PHPUnit\Framework\TestCase;

final class TemplateTest extends TestCase {
    private Language $language;
    private Scope $scope;

    protected function setUp(): void {
        $this->language = Language::create();
        $this->scope = $this->language->scope(["name" => "aieuo", "scores" => [3, 1, 2], "n" => 5]);
    }

    public function testATemplateRendersWhatItsSourceRendersInEveryScope(): void {
        $template = new Template("hi {name}, best {scores.first()}");

        self::assertSame("hi aieuo, best 3", $this->language->render($template, $this->scope));
        self::assertSame("hi b, best 1", $this->language->render($template, $this->language->scope(["name" => "b", "scores" => [1]])));
    }

    public function testTheSourceIsParsedOnceAndOnlyWhenAsked(): void {
        $broken = new Template("{1 +}");
        self::assertSame("{1 +}", $broken->source);

        $template = new Template("{name}");
        self::assertSame($template->node(), $template->node());
    }

    public function testATemplateWithNoEmbeddingIsItsTextAndNeedsNoVariables(): void {
        self::assertSame("plain", (new Template("plain"))->plainText());
        self::assertSame("", (new Template(""))->plainText());
        self::assertSame("hi {name}", (new Template("hi \\{name}"))->plainText());
        self::assertNull((new Template("hi {name}"))->plainText());

        self::assertSame("hi {name}", $this->language->render(new Template("hi \\{name}"), $this->language->scope()));
    }

    public function testASyntaxErrorIsKeptAndThrownAgainWithItsPosition(): void {
        $template = new Template("hi {1 +}");

        $error = $template->syntaxError();
        self::assertInstanceOf(UnexpectedToken::class, $error);
        self::assertSame(7, $error->start());

        foreach ([1, 2] as $_) {
            try {
                $this->language->render($template, $this->scope);
                self::fail("Expected a syntax error.");
            } catch (UnexpectedToken $thrown) {
                self::assertSame($error, $thrown);
            }
        }
        self::assertNull((new Template("hi {name}"))->syntaxError());
    }
}
