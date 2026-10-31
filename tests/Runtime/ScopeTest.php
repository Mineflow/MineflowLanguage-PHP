<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\Runtime;

use Mineflow\Language\Language;
use Mineflow\Language\Runtime\Scope;
use Mineflow\Language\Types\Builtin;
use Mineflow\Language\Types\Type;
use Mineflow\Language\Values\Value;
use PHPUnit\Framework\TestCase;

final class ScopeTest extends TestCase {
    private Scope $globals;
    private Scope $local;

    protected function setUp(): void {
        $this->globals = Language::create()->scope(["server" => "lobby", "name" => "global"]);
        $this->local = $this->globals->child(["name" => "aieuo"]);
    }

    public function testAChildReadsThroughToItsParent(): void {
        self::assertSame("lobby", $this->local->get("server")->native());
        self::assertTrue($this->local->has("server"));
        self::assertFalse($this->local->has("missing"));
    }

    public function testAChildEntryShadowsTheParentsWithoutChangingIt(): void {
        self::assertSame("aieuo", $this->local->get("name")->native());
        self::assertSame("global", $this->globals->get("name")->native());
    }

    public function testAChildEntryHoldingNullStillShadowsTheParents(): void {
        $child = $this->globals->child(["name" => null]);
        self::assertTrue($child->has("name"));
        self::assertNull($child->get("name")->native());
    }

    public function testRemoveSaysWhetherItTookAnythingAway(): void {
        self::assertTrue($this->local->remove("name"));
        self::assertFalse($this->local->remove("name"));
        // The parent still has a value with the same name, so the name can still be referred to.
        self::assertSame("global", $this->local->get("name")->native());
    }

    public function testSetAndRemoveChangeOnlyThisScope(): void {
        $inner = $this->local->child();
        $inner->set("name", "inner");
        self::assertSame("aieuo", $this->local->get("name")->native());

        // None of the child scope's own entries are removed, and the parent keeps its entry.
        self::assertFalse($this->local->remove("server"));
        self::assertSame("lobby", $this->local->get("server")->native());
    }

    public function testAVariableOfTheRunIsPutInItsScopeOnly(): void {
        $run = $this->globals->childRun(["target" => "aieuo"]);
        $loop = $run->child();
        $step = $loop->child();

        $step->setInRun("found", true);
        self::assertTrue($run->get("found")->native());
        self::assertTrue($step->get("found")->native());
        // Only the run scope holds values, so there is only one place to change.
        self::assertFalse($step->remove("found"));
        self::assertFalse($loop->remove("found"));
        self::assertFalse($this->globals->has("found"));
    }

    public function testAVariableOfTheRunIsNotHiddenByAnEntryOnTheWayToItsScope(): void {
        $run = $this->globals->childRun();
        $loop = $run->child(["count" => 1]);
        $step = $loop->child(["count" => 2]);

        $step->setInRun("count", 3);
        self::assertSame(3, $step->get("count")->native());
        self::assertSame(3, $loop->get("count")->native());
    }

    public function testAVariableOfTheRunShadowsAGlobalWithoutChangingIt(): void {
        $loop = $this->globals->childRun()->child();
        $loop->setInRun("server", "survival");
        self::assertSame("survival", $loop->get("server")->native());
        self::assertSame("lobby", $this->globals->get("server")->native());
    }

    public function testRemovingFromTheRunKeepsTheGlobals(): void {
        $run = $this->globals->childRun(["server" => "survival"]);
        $loop = $run->child(["server" => "loop"]);

        self::assertTrue($loop->removeFromRun("server"));
        self::assertFalse($run->removeFromRun("server"));
        self::assertSame("lobby", $loop->get("server")->native());
    }

    public function testTheOutermostScopeIsTheRunWhenThereIsNone(): void {
        $this->local->child()->setInRun("score", 3);
        self::assertSame(3, $this->globals->get("score")->native());
        self::assertTrue($this->local->removeFromRun("score"));
        self::assertFalse($this->globals->has("score"));
    }

    public function testTheGlobalsAreWhatIsReadOutsideTheRun(): void {
        $world = $this->globals->child(["world" => "lobby"]);
        $run = $world->childRun(["target" => "aieuo"]);
        self::assertSame(["server", "name", "world"], array_keys($run->child()->globals()));
        self::assertSame(["server", "name"], array_keys($this->local->globals()));
        self::assertSame($this->globals->all(), $this->local->globals());
    }

    public function testAllFlattensTheChainWithTheChildWinning(): void {
        self::assertSame(["server" => "lobby", "name" => "aieuo"],
            array_map(static fn(Value $value) => $value->native(), $this->local->all()));
    }

    public function testTypesAnswerForEveryReadableName(): void {
        $this->local->set("score", 3);
        self::assertSame(
            ["server" => Builtin::String->value, "name" => Builtin::String->value, "score" => Builtin::Number->value],
            array_map(static fn(Type $type) => (string)$type, $this->local->types()),
        );
    }
}
