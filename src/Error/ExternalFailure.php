<?php
declare(strict_types=1);

namespace Mineflow\Language\Error;

use Closure;
use Mineflow\Language\Definition\MemberReference;
use Throwable;

final class ExternalFailure extends EvaluationError {
    public function __construct(Throwable $previous, public readonly ?MemberReference $reference = null) {
        parent::__construct($previous->getMessage(), $previous);
    }

    /**
     * Runs host code. An exception it throws that is not a language exception becomes an ExternalFailure.
     * @template T
     * @param Closure(): T $hostCode
     * @param MemberReference|null $reference The member being run, if it is a member.
     * @return T
     */
    public static function guard(Closure $hostCode, ?MemberReference $reference = null): mixed {
        try {
            return $hostCode();
        } catch (LanguageException $error) {
            throw $error;
        } catch (Throwable $error) {
            throw new self($error, $reference);
        }
    }
}
