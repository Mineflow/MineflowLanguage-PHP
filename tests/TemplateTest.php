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
        $this->scope = $this->language->scope([
            "name" => "aieuo",
            "scores" => [3, 1, 2],
            "n" => 5,
            "players" => ["a", "b"],
            "nested" => [[1, 2], 3],
            "warps" => ["spawn" => "s", "shop" => "t"],
            "empty" => [],
        ]);
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

    public function testExpandingGivesOneTextForEachItemOfACollection(): void {
        self::assertSame(["3th", "1th", "2th"], $this->language->expand("{scores}th", $this->scope));
        self::assertSame(["3", "1", "2"], $this->language->expand("{scores}", $this->scope));
        self::assertSame(["s", "t"], $this->language->expand("{warps}", $this->scope));
        self::assertSame(["[1,2]!", "3!"], $this->language->expand("{nested}!", $this->scope));
    }

    public function testExpandingPairsCollectionsByPositionWithEmptyTextPastTheShorterOne(): void {
        self::assertSame(["a:3", "b:1", ":2"], $this->language->expand("{players}:{scores}", $this->scope));
        self::assertSame(["a-s", "b-t"], $this->language->expand("{players}-{warps}", $this->scope));
        self::assertSame(["[]a", "[]b"], $this->language->expand("[{empty}]{players}", $this->scope));
    }

    public function testExpandingATemplateWithNoCollectionGivesWhatRenderGives(): void {
        self::assertSame(["hi aieuo, 5"], $this->language->expand("hi {name}, {n}", $this->scope));
        self::assertSame(["3,1,2 points"], $this->language->expand("{scores.join()} points", $this->scope));
        self::assertSame(["plain"], $this->language->expand("plain", $this->scope));
        self::assertSame([""], $this->language->expand("", $this->scope));
    }

    public function testExpandingOnlyEmptyCollectionsGivesNoText(): void {
        self::assertSame([], $this->language->expand("{empty}", $this->scope));
        self::assertSame([], $this->language->expand("no {empty}", $this->scope));
    }
}
