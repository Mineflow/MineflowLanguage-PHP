<?php
declare(strict_types=1);

namespace Mineflow\Language\DateTime;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Mineflow\Language\Attribute\Method;
use Mineflow\Language\Error\InvalidIntegerConversion;

final readonly class DateTimeFunctions {
    private DateTimeZone $timezone;
    /** @var Closure(): DateTimeImmutable */
    private Closure $clock;

    /**
     * @param DateTimeZone|null $timezone The zone every date and time these functions make is in; null takes PHP's
     *     default zone when they are made.
     * @param (Closure(): DateTimeImmutable)|null $clock The current moment; null reads the system clock.
     */
    public function __construct(?DateTimeZone $timezone = null, ?Closure $clock = null) {
        $this->timezone = $timezone ?? new DateTimeZone(date_default_timezone_get());
        $this->clock = $clock ?? static fn() => new DateTimeImmutable();
    }

    #[Method(description: "The current date and time.")]
    public function now(): DateTimeValue {
        return new DateTimeValue(($this->clock)()->setTimezone($this->timezone));
    }

    /**
     * @throws InvalidIntegerConversion
     */
    #[Method(description: "The date and time a number of seconds since 1970-01-01 00:00:00 UTC stands for.")]
    public function datetime(int|float $timestamp): DateTimeValue {
        return (new DateTimeValue((new DateTimeImmutable("@0"))->setTimezone($this->timezone)))->plusSeconds($timestamp);
    }
}
