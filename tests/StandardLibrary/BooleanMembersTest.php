<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\StandardLibrary;

use Mineflow\Language\Language;
use PHPUnit\Framework\TestCase;

final class BooleanMembersTest extends TestCase {
    public function testOperatorsAreLogical(): void {
        $language = Language::create();
        $scope = $language->scope(["yes" => true, "no" => false]);

        self::assertTrue($language->evaluate("yes + no", $scope)->native());
        self::assertFalse($language->evaluate("no + no", $scope)->native());
        self::assertFalse($language->evaluate("yes * no", $scope)->native());
        self::assertTrue($language->evaluate("yes * yes", $scope)->native());
    }
}
