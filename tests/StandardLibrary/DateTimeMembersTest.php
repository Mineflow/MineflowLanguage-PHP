<?php
declare(strict_types=1);

namespace Mineflow\Language\Tests\StandardLibrary;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Mineflow\Language\Attribute\DeclareType;
use Mineflow\Language\Error\InvalidIntegerConversion;
use Mineflow\Language\Error\InvalidValue;
use Mineflow\Language\Error\UnsupportedCalculation;
use Mineflow\Language\Language;
use Mineflow\Language\LanguageBuilder;
use Mineflow\Language\Runtime\Scope;
use Mineflow\Language\Values\DateTimeValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[DeclareType("time_of_day")]
final readonly class TimeOfDayValue extends DateTimeValue {
}

final class DateTimeMembersTest extends TestCase {
    private const string NOW = "2026-11-05 12:34:56.250000";

    private Language $language;
    private DateTimeZone $tokyo;

    protected function setUp(): void {
        $this->tokyo = new DateTimeZone("Asia/Tokyo");
        $this->language = $this->languageIn($this->tokyo);
    }

    private function languageIn(DateTimeZone $timezone): Language {
        return (new LanguageBuilder())
            ->addType(TimeOfDayValue::class)
            ->setTimezone($timezone)
            ->setClock(static fn() => new DateTimeImmutable(self::NOW." UTC"))
            ->build();
    }

    private function scope(string $time, string $format = DateTimeValue::DEFAULT_FORMAT): Scope {
        return $this->language->scope(["t" => new DateTimeValue(new DateTimeImmutable($time, $this->tokyo), $format)]);
    }

    public function testNowIsTheClockInTheGivenZone(): void {
        self::assertSame("2026-11-05 21:34:56", $this->language->render("{now()}"));
        self::assertSame("datetime", (string)$this->language->evaluate("now()")->type());
    }

    public function testEveryLanguageHasDatesInPhpsDefaultZone(): void {
        $zone = date_default_timezone_get();
        date_default_timezone_set("Asia/Tokyo");
        try {
            self::assertSame("1970-01-01 09:00:00", Language::create()->render("{datetime(0)}"));
        } finally {
            date_default_timezone_set($zone);
        }
    }

    public function testACalculationKeepsTheClassOfASubtype(): void {
        $scope = $this->language->scope(["t" => new TimeOfDayValue(new DateTimeImmutable("2026-11-05 07:00:00", $this->tokyo), "H:i")]);

        $later = $this->language->evaluate("t + 60", $scope);

        self::assertInstanceOf(TimeOfDayValue::class, $later);
        self::assertSame("07:01", (string)$later);
        self::assertSame(7, $this->language->evaluate("t.hours", $scope)->native());
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function properties(): iterable {
        yield "year" => ["t.year", 2026];
        yield "month" => ["t.month", 3];
        yield "day" => ["t.day", 8];
        yield "hours" => ["t.hours", 7];
        yield "hour" => ["t.hour", 7];
        yield "minutes" => ["t.minutes", 5];
        yield "minute" => ["t.minute", 5];
        yield "seconds" => ["t.seconds", 9];
        yield "second" => ["t.second", 9];
        yield "weekday counts from Sunday" => ["t.weekday", 0];
        yield "timestamp" => ["t.timestamp", 1772921109];
    }

    #[DataProvider("properties")]
    public function testProperties(string $expression, int $expected): void {
        self::assertSame($expected, $this->language->evaluate($expression, $this->scope("2026-03-08 07:05:09.75"))->native());
    }

    public function testTheValueIsWrittenInItsOwnFormat(): void {
        self::assertSame("07:05:09 / 03/08 / 07:05", $this->language->render(
            "{t} / {d} / {t.format(H:i)}",
            $this->language->scope([
                "t" => new DateTimeValue(new DateTimeImmutable("2026-03-08 07:05:09", $this->tokyo), "H:i:s"),
                "d" => new DateTimeValue(new DateTimeImmutable("2026-03-08 07:05:09", $this->tokyo), "m/d"),
            ]),
        ));
    }

    public function testAddingSecondsKeepsTheFormat(): void {
        $scope = $this->scope("2026-12-31 23:59:30", "H:i:s");
        self::assertSame("00:01:00", $this->language->render("{t + 90}", $scope));
        self::assertSame("00:01:00", $this->language->render("{90 + t}", $scope));
        self::assertSame("23:58:00", $this->language->render("{t - 90}", $scope));
        self::assertSame("2027-01-01 00:01:00.500000", $this->language->evaluate("t + 90.5", $scope)->native()->format("Y-m-d H:i:s.u"));
        self::assertSame("2026-12-31 23:59:28.750000", $this->language->evaluate("t - 1.25", $scope)->native()->format("Y-m-d H:i:s.u"));
    }

    public function testSecondsAreAddedAcrossDaylightSavingTime(): void {
        $newYork = $this->languageIn(new DateTimeZone("America/New_York"));
        $scope = $newYork->scope(["t" => $newYork->evaluate("datetime(1772953199)")]);

        self::assertSame("2026-03-08 01:59:59", $newYork->render("{t}", $scope));
        self::assertSame("2026-03-08 03:00:00", $newYork->render("{t + 1}", $scope));
        self::assertSame(1, $newYork->evaluate("t + 1 - t", $scope)->native());
    }

    public function testSubtractingTwoDatesGivesTheSecondsBetweenThem(): void {
        $scope = $this->language->scope([
            "start" => new DateTimeValue(new DateTimeImmutable("2026-11-05 00:00:00", $this->tokyo)),
            "end" => new DateTimeValue(new DateTimeImmutable("2026-11-06 01:00:00", new DateTimeZone("UTC"))),
            "half" => new DateTimeValue(new DateTimeImmutable("2026-11-05 00:00:01.5", $this->tokyo)),
        ]);

        self::assertSame(122400, $this->language->evaluate("end - start", $scope)->native());
        self::assertSame(-122400, $this->language->evaluate("start - end", $scope)->native());
        self::assertSame(1.5, $this->language->evaluate("half - start", $scope)->native());
    }

    public function testATimestampBecomesADateInTheGivenZone(): void {
        self::assertSame("1970-01-01 09:00:00", $this->language->render("{datetime(0)}"));
        self::assertSame("2026-11-05 21:34:56", $this->language->render("{datetime(1793882096)}"));
        self::assertSame("1970-01-01 08:59:58.500000", $this->language->evaluate("datetime(-1.5)")->native()->format("Y-m-d H:i:s.u"));
    }

    public function testSecondsBeyondTheIntegerRangeAreRefused(): void {
        $this->expectException(InvalidIntegerConversion::class);
        $this->language->evaluate("now() + 99999999999999999999999");
    }

    public function testADateIsNotCalculatedWithText(): void {
        $this->expectException(UnsupportedCalculation::class);
        $this->expectExceptionMessage("Unsupported calculation: datetime - string");
        $this->language->evaluate("now() - name", $this->language->scope(["name" => "aieuo"]));
    }

    public function testAPhpDateBecomesADatetime(): void {
        $value = $this->language->wrap(new DateTime("2026-11-05 12:00:00", $this->tokyo));

        self::assertInstanceOf(DateTimeValue::class, $value);
        self::assertSame("2026-11-05 12:00:00", (string)$value);
    }

    public function testTheZoneAndTheFormatAreSavedWithTheTime(): void {
        $value = new DateTimeValue(new DateTimeImmutable("2026-03-08 01:59:59.25", new DateTimeZone("America/New_York")), "H:i");

        $json = json_encode($this->language->serializer->serialize($value), JSON_THROW_ON_ERROR);
        $read = $this->language->serializer->deserialize(json_decode($json, true));

        self::assertInstanceOf(DateTimeValue::class, $read);
        self::assertSame("H:i", $read->format);
        self::assertSame("America/New_York", $read->value->getTimezone()->getName());
        self::assertSame("2026-03-08 01:59:59.250000", $read->value->format("Y-m-d H:i:s.u"));
        self::assertSame("2026-03-08 03:00:00", $read->plusSeconds(1)->value->format("Y-m-d H:i:s"));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function brokenSaves(): iterable {
        yield "not an array" => ["2026-11-05"];
        yield "no format" => [["time" => "2026-11-05T00:00:00.000000+09:00", "timezone" => "Asia/Tokyo"]];
        yield "a time in another format" => [["time" => "2026-11-05", "timezone" => "Asia/Tokyo", "format" => "Y"]];
        yield "an unknown zone" => [["time" => "2026-11-05T00:00:00.000000+09:00", "timezone" => "Mars/Olympus", "format" => "Y"]];
    }

    #[DataProvider("brokenSaves")]
    public function testABrokenSaveIsRefused(mixed $data): void {
        $this->expectException(InvalidValue::class);
        $this->language->serializer->deserialize(["type" => "datetime", "value" => $data]);
    }
}
