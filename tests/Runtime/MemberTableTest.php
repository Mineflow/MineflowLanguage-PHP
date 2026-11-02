<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\Runtime;

use Mineflow\Language\Definition\Declaration;
use Mineflow\Language\Definition\MemberDefinition;
use Mineflow\Language\Definition\MemberReference;
use Mineflow\Language\Definition\Parameter;
use Mineflow\Language\Error\DefinitionError;
use Mineflow\Language\Language;
use Mineflow\Language\Runtime\EvaluationContext;
use Mineflow\Language\Runtime\Member;
use Mineflow\Language\Runtime\MemberImplementation;
use Mineflow\Language\Runtime\MemberTable;
use Mineflow\Language\StandardLibrary\StandardTypes;
use Mineflow\Language\Types\TypeRegistry;
use Mineflow\Language\Values\StringValue;
use Mineflow\Language\Values\Value;
use PHPUnit\Framework\TestCase;

/**
 * Members implemented without attributes.
 */
final class Shout implements MemberImplementation {
    public function call(?Value $target, array $arguments, EvaluationContext $context): Value {
        return new StringValue(strtoupper((string)($target ?? $arguments[0])));
    }
}

final class MemberTableTest extends TestCase {
    public function testAMemberNeedsNoAttributeToBeCalled(): void {
        $language = new Language(MemberTable::from(new TypeRegistry(StandardTypes::CLASSES), [
            $this->shout(MemberReference::method("string", "shout")),
            $this->shout(MemberReference::function("shout"), [new Parameter("text", StandardTypes::string())]),
        ]));
        $scope = $language->scope(["word" => "hi"]);

        self::assertSame("HI", $language->evaluate("word.shout()", $scope)->native());
        self::assertSame("HEY", $language->evaluate("shout(hey)")->native());
        self::assertSame("string", (string)$language->catalog->definition(MemberReference::function("shout"))?->returns);
    }

    public function testTheTableRefusesWhatTheCatalogRefuses(): void {
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage("Duplicate function: shout");

        MemberTable::from(new TypeRegistry(StandardTypes::CLASSES), [
            $this->shout(MemberReference::function("shout")),
            $this->shout(MemberReference::function("shout")),
        ]);
    }

    /**
     * @param list<Parameter> $parameters
     */
    private function shout(MemberReference $reference, array $parameters = []): Member {
        return new Member(new Declaration($reference, new MemberDefinition(StandardTypes::string(), $parameters)), new Shout());
    }
}
