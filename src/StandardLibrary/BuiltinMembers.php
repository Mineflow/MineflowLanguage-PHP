<?php
declare(strict_types=1);

namespace Mineflow\Language\StandardLibrary;

final class BuiltinMembers {
    /**
     * @return list<object>
     */
    public static function all(): array {
        return [
            new StringMembers(),
            new NumberMembers(),
            new BooleanMembers(),
            new NullMembers(),
            new ListMembers(),
            new MapMembers(),
        ];
    }
}
