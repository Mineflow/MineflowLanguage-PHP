<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\Fixtures;

use Mineflow\Language\Attribute\DeclareType;
use Mineflow\Language\Attribute\Entries;
use Mineflow\Language\Attribute\Index;
use Mineflow\Language\Attribute\Method;
use Mineflow\Language\Attribute\Property;
use Mineflow\Language\Types\Builtin;
use Mineflow\Language\Types\Expression\MapOf;
use Mineflow\Language\Types\Special;

#[DeclareType(Settings::class, name: "settings")]
final class SettingsType {
    #[Property]
    public function path(Settings $settings): string {
        return $settings->path;
    }

    #[Index(description: "The value under a key.")]
    public function get(Settings $settings, string $key): mixed {
        return $settings->data[$key] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    #[Entries(type: new MapOf(Special::Mixed, Builtin::String))]
    #[Method(type: new MapOf(Special::Mixed, Builtin::String))]
    public function all(Settings $settings): array {
        return $settings->data;
    }

    #[Method(description: "Declared here, so it wins over the map's own first().")]
    public function first(Settings $settings): string {
        return "declared";
    }
}
