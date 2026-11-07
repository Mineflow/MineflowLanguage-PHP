<?php
declare(strict_types=1);

namespace Mineflow\Language\Values;

use DateTimeImmutable;
use DateTimeInterface;
use Mineflow\Language\Attribute\DeclareType;
use Mineflow\Language\Attribute\FromNative;
use Mineflow\Language\Attribute\Method;
use Mineflow\Language\Attribute\Operator;
use Mineflow\Language\Attribute\Property;
use Mineflow\Language\Error\InvalidIntegerConversion;
use Mineflow\Language\Syntax\BinaryOperator;

#[DeclareType("datetime")]
readonly class DateTimeValue extends Value {
    public const string DEFAULT_FORMAT = "Y-m-d H:i:s";

    /**
     * Final so that a calculation makes a value of the class of its target.
     * @param string $format The PHP date() format the value is written in. A calculation keeps it.
     */
    final public function __construct(public DateTimeImmutable $value, public string $format = self::DEFAULT_FORMAT) {
    }

    #[FromNative]
    public static function fromNative(DateTimeInterface $value): self {
        return new self(DateTimeImmutable::createFromInterface($value));
    }

    public function native(): DateTimeImmutable {
        return $this->value;
    }

    public function __toString(): string {
        return $this->value->format($this->format);
    }

    /**
     * The moment that many seconds later, written in the same format. A fraction of a second is kept to the
     * microsecond.
     * @throws InvalidIntegerConversion When the seconds are not finite or do not fit a PHP integer.
     */
    public function plusSeconds(int|float $seconds): static {
        if (is_int($seconds)) {
            return $this->with($this->value->modify(sprintf("%+d seconds", $seconds)));
        }

        $whole = floor($seconds);
        if (!is_finite($whole) || $whole < PHP_INT_MIN || $whole >= PHP_INT_MAX) {
            throw new InvalidIntegerConversion($seconds);
        }

        $microseconds = (int)round(($seconds - $whole) * 1_000_000);

        return $this->with($this->value->modify(sprintf("%+d seconds %+d microseconds", (int)$whole, $microseconds)));
    }

    #[Property(description: "The year, such as 2026.")]
    public function year(): int {
        return (int)$this->value->format("Y");
    }

    #[Property(description: "The month, from 1 to 12.")]
    public function month(): int {
        return (int)$this->value->format("n");
    }

    #[Property(description: "The day of the month, from 1 to 31.")]
    public function day(): int {
        return (int)$this->value->format("j");
    }

    #[Property(aliases: ["hour"], description: "The hour, from 0 to 23.")]
    public function hours(): int {
        return (int)$this->value->format("G");
    }

    #[Property(aliases: ["minute"], description: "The minute, from 0 to 59.")]
    public function minutes(): int {
        return (int)$this->value->format("i");
    }

    #[Property(aliases: ["second"], description: "The second, from 0 to 59.")]
    public function seconds(): int {
        return (int)$this->value->format("s");
    }

    #[Property(description: "The day of the week, from 0 for Sunday to 6 for Saturday, as PHP's date(\"w\").")]
    public function weekday(): int {
        return (int)$this->value->format("w");
    }

    #[Property(description: "The whole seconds since 1970-01-01 00:00:00 UTC.")]
    public function timestamp(): int {
        return $this->value->getTimestamp();
    }

    #[Method(description: "The date and time written in a PHP date() format, such as \"H:i\".")]
    public function format(string $format): string {
        return $this->value->format($format);
    }

    #[Operator(BinaryOperator::Add, description: "The date and time that many seconds later.")]
    public function add(int|float $seconds): static {
        return $this->plusSeconds($seconds);
    }

    #[Operator(BinaryOperator::Add, reflected: true, description: "The date and time that many seconds later, with the number on the left.")]
    public function addReflected(int|float $seconds): static {
        return $this->plusSeconds($seconds);
    }

    /**
     * @throws InvalidIntegerConversion
     */
    #[Operator(BinaryOperator::Subtract, description: "The date and time that many seconds earlier, or the seconds from another date and time to this one.")]
    public function subtract(int|float|DateTimeInterface $right): int|float|static {
        if (!$right instanceof DateTimeInterface) {
            return $this->plusSeconds(-$right);
        }

        $seconds = $this->value->getTimestamp() - $right->getTimestamp();
        $microseconds = (int)$this->value->format("u") - (int)$right->format("u");

        return $microseconds === 0 ? $seconds : $seconds + $microseconds / 1_000_000;
    }

    private function with(DateTimeImmutable $value): static {
        return new static($value, $this->format);
    }
}
