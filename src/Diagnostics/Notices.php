<?php
declare(strict_types=1);

namespace Mineflow\Language\Diagnostics;

final class Notices {
    /** @var list<Notice> */
    private array $notices = [];

    public function add(string $message): void {
        $this->notices[] = new Notice($message);
    }

    /**
     * @return list<Notice>
     */
    public function all(): array {
        return $this->notices;
    }

    public function count(): int {
        return count($this->notices);
    }

    public function locate(int $from, int $start, int $end): void {
        $this->notices = array_map(
            static fn(int $i, Notice $notice) => $i >= $from && $notice->start === null ? new Notice($notice->message, $start, $end) : $notice,
            array_keys($this->notices),
            $this->notices,
        );
    }

    public function merge(self $other): void {
        array_push($this->notices, ...$other->notices);
    }
}
