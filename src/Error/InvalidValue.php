<?php
declare(strict_types=1);

namespace Mineflow\Language\Error;

use InvalidArgumentException;

final class InvalidValue extends InvalidArgumentException implements LanguageException {
}
