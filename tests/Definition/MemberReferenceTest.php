<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\Definition;

use Mineflow\Language\Definition\MemberReference;
use Mineflow\Language\Error\DefinitionError;
use PHPUnit\Framework\TestCase;

final class MemberReferenceTest extends TestCase {
    public function testAFunctionCannotBeReachedFromAType(): void {
        $this->expectException(DefinitionError::class);
        $this->expectExceptionMessage("A function belongs to no type: dice");

        MemberReference::function("dice")->withTypeName("string");
    }
}
