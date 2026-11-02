<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\Runtime\Engine;

use Mineflow\Language\Attribute\Method;
use Mineflow\Language\Attribute\Param;
use Mineflow\Language\Error\ArgumentTypeMismatch;
use Mineflow\Language\Error\InvalidIntegerConversion;
use Mineflow\Language\Error\MissingArgument;
use Mineflow\Language\Error\SourceError;
use Mineflow\Language\Error\TooManyArguments;
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

    public function testAParameterWithoutADefaultHasToBePassed(): void {
        $error = $this->refusal("pad(x)");

        self::assertInstanceOf(MissingArgument::class, $error);
        self::assertSame("Missing argument: width", $error->getMessage());
        self::assertSame(["pad", "width"], [$error->reference->name, $error->parameter]);
    }

    public function testAnExtraArgumentIsRefusedAndNamesTheMember(): void {
        $error = $this->refusal("pad(x, 4, 5)");

        self::assertInstanceOf(TooManyArguments::class, $error);
        self::assertSame("Too many arguments for pad", $error->getMessage());
        self::assertSame(["pad", 3], [$error->reference->name, $error->givenCount]);
    }

    public function testAVariadicParameterTakesEveryRemainingArgument(): void {
        self::assertSame("", $this->language->evaluate("tag()")->native());
        self::assertSame("a|b|c", $this->language->evaluate("tag(a, b, c)")->native());
    }

    public function testAVariadicParameterStillChecksEachArgument(): void {
        $error = $this->refusal("tag(a, {1})");

        self::assertInstanceOf(ArgumentTypeMismatch::class, $error);
        self::assertSame("parts expects string, got number", $error->getMessage());
        self::assertSame(["parts", "string", "number"], [$error->parameter, (string)$error->expected, (string)$error->actual]);
    }

    public function testAnIntegerParameterTakesANumberThatHasNoFraction(): void {
        self::assertSame("x ", $this->language->evaluate("pad(x, 2.0)")->native());

        $error = $this->refusal("pad(x, {1.5})");
        self::assertInstanceOf(InvalidIntegerConversion::class, $error);
        self::assertSame(1.5, $error->actual);
    }

    public function testEachItemIsCheckedAsAnArgumentOfItsOwn(): void {
        $error = $this->refusal("total({names})", ["names" => ["a", 1]]);

        self::assertInstanceOf(ArgumentTypeMismatch::class, $error);
        self::assertSame("names[1] expects string, got number", $error->getMessage());
        self::assertSame(3, $this->language->evaluate("sum({numbers})", $this->language->scope(["numbers" => ["1", 2]]))->native());
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
