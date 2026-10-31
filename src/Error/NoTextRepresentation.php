<?php
declare(strict_types=1);

namespace Mineflow\Language\Error;

use Mineflow\Language\Types\Type;

final class NoTextRepresentation extends EvaluationError {
    public function __construct(public readonly Type $type) {
        parent::__construct("Type $type has no text representation.");
    }
}
