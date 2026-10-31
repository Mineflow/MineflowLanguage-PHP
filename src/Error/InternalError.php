<?php
declare(strict_types=1);

namespace Mineflow\Language\Error;

use LogicException;

final class InternalError extends LogicException implements LanguageException {
}
