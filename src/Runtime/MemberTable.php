<?php
declare(strict_types=1);

namespace Mineflow\Language\Runtime;

use Mineflow\Language\Definition\Catalog;
use Mineflow\Language\Definition\MemberReference;
use Mineflow\Language\Types\TypeRegistry;

final readonly class MemberTable {
    /**
     * @param array<string, array<string, array<string, MemberImplementation>>> $implementations Keyed by the declaring type name ("" for functions), kind, then name.
     */
    private function __construct(
        public Catalog $catalog,
        private array  $implementations,
    ) {
    }

    /**
     * @param list<Member> $members
     */
    public static function from(TypeRegistry $types, array $members): self {
        $catalog = Catalog::from($types, array_map(static fn(Member $member) => $member->declaration, $members));
        $implementations = [];
        foreach ($members as $member) {
            $reference = $member->declaration->reference;
            $implementations[$reference->typeName ?? ""][$reference->kind->value][$reference->name] = $member->implementation;
        }
        return new self($catalog, $implementations);
    }

    /**
     * Returns the member the reference points to. If the type doesn't declare it, returns the one from the nearest ancestor.
     */
    public function find(MemberReference $reference): ?Member {
        $declaration = $this->catalog->declaration($reference);
        $origin = $this->catalog->origin($reference);
        if ($declaration === null || $origin === null) {
            return null;
        }

        return new Member($declaration, $this->implementations[$origin->typeName ?? ""][$origin->kind->value][$origin->name]);
    }
}
