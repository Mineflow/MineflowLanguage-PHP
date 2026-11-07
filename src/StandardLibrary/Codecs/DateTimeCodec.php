<?php
declare(strict_types=1);

namespace Mineflow\Language\StandardLibrary\Codecs;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Mineflow\Language\Error\InvalidValue;
use Mineflow\Language\Serialization\ValueCodec;
use Mineflow\Language\Serialization\ValueSerializer;
use Mineflow\Language\Values\DateTimeValue;
use Mineflow\Language\Values\Value;

/**
 * The zone is written by name as well as in the offset of the time, since an offset cannot tell which zone, and so
 * which daylight saving time, a later calculation is in.
 * @implements ValueCodec<DateTimeValue>
 */
final readonly class DateTimeCodec implements ValueCodec {
    private const string TIME_FORMAT = "Y-m-d\\TH:i:s.uP";

    public function valueClass(): string {
        return DateTimeValue::class;
    }

    /**
     * @return array{time: string, timezone: string, format: string}
     */
    public function encode(Value $value, ValueSerializer $serializer): array {
        return [
            "time" => $value->value->format(self::TIME_FORMAT),
            "timezone" => $value->value->getTimezone()->getName(),
            "format" => $value->format,
        ];
    }

    public function decode(mixed $data, ValueSerializer $serializer): DateTimeValue {
        if (!is_array($data) || !is_string($data["time"] ?? null) || !is_string($data["timezone"] ?? null) || !is_string($data["format"] ?? null)) {
            throw new InvalidValue("A saved datetime has a time, a timezone and a format, not ".json_encode($data));
        }

        $time = DateTimeImmutable::createFromFormat("!".self::TIME_FORMAT, $data["time"]);
        if ($time === false) {
            throw new InvalidValue("A saved datetime has its time in the format ".self::TIME_FORMAT.", not ".json_encode($data["time"]));
        }

        try {
            $timezone = new DateTimeZone($data["timezone"]);
        } catch (Exception) {
            throw new InvalidValue("Unknown timezone of a saved datetime: ".json_encode($data["timezone"]));
        }

        return new DateTimeValue($time->setTimezone($timezone), $data["format"]);
    }
}
