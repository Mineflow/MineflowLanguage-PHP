<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\Fixtures;

use Mineflow\Language\Attribute\DeclareType;
use Mineflow\Language\Attribute\FromNative;
use Mineflow\Language\Attribute\Method;
use Mineflow\Language\Attribute\Property;
use Mineflow\Language\Types\Expression\CollectionOf;
use Mineflow\Language\Types\Expression\MapOf;
use Mineflow\Language\Types\Special;
use Mineflow\Language\Values\CollectionValue;
use Mineflow\Language\Values\MapValue;
use Mineflow\Language\Values\StringValue;
use Mineflow\Language\Values\TypeDeclarations;
use Mineflow\Language\Values\Value;
use Mineflow\Language\Values\ValueMapper;

/**
 * A collection over a PHP object the language cannot change, read afresh every time its items are reached.
 */
#[DeclareType("settings", typeHint: new CollectionOf(Special::Mixed, StringValue::class))]
final readonly class SettingsValue extends CollectionValue {
    public function __construct(public Settings $settings) {
    }

    #[FromNative]
    public static function fromSettings(Settings $settings): self {
        return new self($settings);
    }

    public function items(): array {
        $entries = (new ValueMapper())->wrap($this->settings->data, TypeDeclarations::of(MapValue::class));
        return $entries instanceof MapValue ? $entries->items() : [];
    }

    public function native(): Settings {
        return $this->settings;
    }

    public function __toString(): string {
        return (string)new MapValue($this->items());
    }

    #[Property]
    public function path(): string {
        return $this->settings->path;
    }

    /**
     * @return array<string, mixed>
     */
    #[Method(type: new MapOf(Special::Mixed, StringValue::class))]
    public function all(): array {
        return $this->settings->data;
    }

    #[Method(type: StringValue::class, description: "Declared here, so it wins over the collection's own first().")]
    public function first(): Value {
        return new StringValue("declared");
    }
}
