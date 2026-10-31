<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\Runtime;

use Mineflow\Language\Attribute\ExtendType;
use Mineflow\Language\Attribute\FromContext;
use Mineflow\Language\Attribute\Method;
use Mineflow\Language\LanguageBuilder;
use Mineflow\Language\Runtime\EvaluationContext;
use Mineflow\Language\Tests\Fixtures\Settings;
use Mineflow\Language\Tests\Fixtures\SettingsType;
use Mineflow\Language\Types\Builtin;
use Mineflow\Language\Values\Value;
use PHPUnit\Framework\TestCase;

/**
 * What a member that takes #[FromContext] can do with the values it is given.
 */
final class EvaluationContextTest extends TestCase {
    public function testAMemberCallsOtherMembersByTheValueTheyAreCalledOn(): void {
        $extension = new #[ExtendType(Builtin::List)]
        class {
            #[Method]
            public function sentence(Value $list, #[FromContext] EvaluationContext $context): string {
                $joined = $context->callMethod($list, "join", " and ")->native();
                $count = $context->property($list, "length")->native();
                return $context->callFunction("shout", "$joined ($count)")->native();
            }
        };

        $functions = new class {
            #[Method]
            public function shout(string $text): string {
                return strtoupper($text);
            }
        };

        $language = (new LanguageBuilder())->addExtension($extension)->addFunctions($functions)->build();
        $scope = $language->scope(["names" => ["a", "b"]]);

        self::assertSame("A AND B (2)", $language->evaluate("names.sentence()", $scope)->native());
    }

    public function testAMemberIteratesAnObjectTypeThroughItsEntries(): void {
        $extension = new #[ExtendType(Settings::class)]
        class {
            #[Method]
            public function size(Value $settings, #[FromContext] EvaluationContext $context): int {
                return count($context->asCollection($settings)?->items() ?? []);
            }
        };

        $language = (new LanguageBuilder())->addType(new SettingsType())->addExtension($extension)->build();
        $settings = new Settings("config.yml", ["money" => 100, "user" => ["name" => "aieuo"]]);

        self::assertSame(2, $language->evaluate("s.size()", $language->scope(["s" => $settings]))->native());
    }
}
