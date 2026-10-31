<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\Fixtures;

final class Settings {
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(public string $path, public array $data) {
    }
}
