<?php
declare(strict_types=1);

namespace Mineflow\Language\StandardLibrary;

use Mineflow\Language\Serialization\ValueCodec;
use Mineflow\Language\StandardLibrary\Codecs\BooleanCodec;
use Mineflow\Language\StandardLibrary\Codecs\ListCodec;
use Mineflow\Language\StandardLibrary\Codecs\MapCodec;
use Mineflow\Language\StandardLibrary\Codecs\NullCodec;
use Mineflow\Language\StandardLibrary\Codecs\NumberCodec;
use Mineflow\Language\StandardLibrary\Codecs\StringCodec;

/**
 * A collection that is neither a list nor a map has none, since it may stand for something only the host can make
 * again.
 */
final class StandardCodecs {
    /**
     * @return list<ValueCodec<*>>
     */
    public static function all(): array {
        return [new StringCodec(), new NumberCodec(), new BooleanCodec(), new NullCodec(), new ListCodec(), new MapCodec()];
    }
}
