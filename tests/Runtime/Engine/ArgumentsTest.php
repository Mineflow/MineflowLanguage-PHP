<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\Runtime\Engine;

use Mineflow\Language\Attribute\Method;
use Mineflow\Language\Attribute\Param;
use Mineflow\Language\Diagnostics\Notice;
use Mineflow\Language\Diagnostics\Notices;
use Mineflow\Language\Error\MissingArgument;
use Mineflow\Language\Error\SourceError;
use Mineflow\Language\Language;
use Mineflow\Language\LanguageBuilder;
use Mineflow\Language\Types\Expression\ListOf;
use Mineflow\Language\Values\NumberValue;
use Mineflow\Language\Values\StringValue;
use PHPUnit\Framework\TestCase;

final class Padding {
    #[Method]
    public function pad(string $value, int $width): string {
        return str_pad($value, $width);
    }

    #[Method]
    public function tag(string ...$parts): string {
        return implode("|", $parts);
    }

    /**
     * @param list<string> $names
     */
    #[Method]
    public function total(#[Param(type: new ListOf(StringValue::class))] array $names): int {
        return count($names);
    }

    /**
     * @param list<float|int> $numbers
     */
    #[Method]
    public function sum(#[Param(type: new ListOf(NumberValue::class))] array $numbers): float|int {
        return array_sum($numbers);
    }

    /**
     * @return list<mixed>
     */
    #[Method(type: new ListOf(StringValue::class))]
    public function labels(): array {
        return ["a", 1];
    }
}

/**
 * Arguments that can be passed to declared members, and the errors when they don't match.
 */
final class ArgumentsTest extends TestCase {
    private Language $language;

    protected function setUp(): void {
        $this->language = (new LanguageBuilder())->addFunctions(new Padding())->build();
    }

    /**
     * @param array<string, mixed> $variables
     */
    private function refusal(string $source, array $variables = []): SourceError {
        try {
            $this->language->evaluate($source, $this->language->scope($variables));
        } catch (SourceError $error) {
            return $error;
        }

        self::fail("Expected $source to be refused.");
    }

    /**
     * @param array<string, mixed> $variables
     * @return array{mixed, list<string>}
     */
    private function evaluateWithNotices(string $source, array $variables = []): array {
        $notices = new Notices();
        $value = $this->language->evaluate($source, $this->language->scope($variables), $notices)->native();
        return [$value, array_map(static fn(Notice $notice) => $notice->message, $notices->all())];
    }

    public function testAParameterWithoutADefaultHasToBePassed(): void {
        $error = $this->refusal("pad(x)");

        self::assertInstanceOf(MissingArgument::class, $error);
        self::assertSame("Missing argument: width", $error->getMessage());
        self::assertSame(["pad", "width"], [$error->reference->name, $error->parameter]);
    }

    public function testAnExtraArgumentIsIgnored(): void {
        self::assertSame("x   ", $this->language->evaluate("pad(x, 4, 5)")->native());
        self::assertSame(1, $this->language->evaluate("total({names}, a, b)", $this->language->scope(["names" => ["a"]]))->native());
        self::assertSame(1, $this->language->evaluate("labels().last(a)")->native());
    }

    public function testAVariadicParameterTakesEveryRemainingArgument(): void {
        self::assertSame("", $this->language->evaluate("tag()")->native());
        self::assertSame("a|b|c", $this->language->evaluate("tag(a, b, c)")->native());
    }

    public function testAVariadicParameterConvertsEachArgument(): void {
        self::assertSame(["a|1|true|null", []], $this->evaluateWithNotices("tag(a, {1}, {t}, {z})", ["t" => true, "z" => null]));
    }

    public function testAnIntegerParameterTakesANumberWithoutItsFraction(): void {
        self::assertSame(["x ", []], $this->evaluateWithNotices("pad(x, 2.0)"));
        self::assertSame(["x", ["1.5 is not a whole number, so 1 is used."]], $this->evaluateWithNotices("pad(x, {1.5})"));
        self::assertSame(["x", ["-1.9 is not a whole number, so -1 is used."]], $this->evaluateWithNotices("pad(x, -1.9)"));
        self::assertSame(["x", ["INF is not a whole number, so 0 is used."]], $this->evaluateWithNotices("pad(x, {n})", ["n" => INF]));
    }

    public function testTextThatIsNoNumberIsZeroWithANoticeOfTheParameter(): void {
        self::assertSame(["x  ", []], $this->evaluateWithNotices("pad(x, 3)"));
        self::assertSame(["x  ", []], $this->evaluateWithNotices("pad(x, {n})", ["n" => "3"]));
        self::assertSame(["x", []], $this->evaluateWithNotices("pad(x, {n})", ["n" => null]));
        self::assertSame(["x", ["width of pad: \"wide\" is not a number, so 0 is used."]], $this->evaluateWithNotices("pad(x, wide)"));
    }

    public function testEachItemIsConvertedAsAnArgumentOfItsOwn(): void {
        self::assertSame([2, []], $this->evaluateWithNotices("total({names})", ["names" => ["a", 1]]));
        self::assertSame([3, ["numbers[1] of sum: \"two\" is not a number, so 0 is used."]], $this->evaluateWithNotices("sum({numbers})", ["numbers" => ["3", "two"]]));
    }

    public function testAValueThatIsNoCollectionIsAListOfIt(): void {
        self::assertSame([1, []], $this->evaluateWithNotices("total(a)"));
        self::assertSame([0, []], $this->evaluateWithNotices("total({z})", ["z" => null]));
    }

    public function testAResultIsNotRefusedForTheItemsItHolds(): void {
        self::assertSame(["a", 1], $this->language->evaluate("labels()")->native());
        // The result carries the declared hint, which its last item does not fit.
        self::assertSame("list<string>", (string)$this->language->evaluate("labels()")->type());
        self::assertSame(1, $this->language->evaluate("labels().last()")->native());
    }

    public function testAListReachesAnArrayParameterAsAPhpList(): void {
        $scope = $this->language->scope(["names" => ["a", "b"]]);

        self::assertSame(2, $this->language->evaluate("total({names})", $scope)->native());
    }
}
