<?php
declare(strict_types=1);

namespace Mineflow\Language;

use Mineflow\Language\Definition\Catalog;
use Mineflow\Language\Format\DefaultValueFormatter;
use Mineflow\Language\Format\ValueFormatter;
use Mineflow\Language\PhpBinding\AttributeReader;
use Mineflow\Language\PhpBinding\DeclaredTypes;
use Mineflow\Language\StandardLibrary\BuiltinFunctions;
use Mineflow\Language\StandardLibrary\BuiltinMembers;

final class LanguageBuilder {
    /** @var list<object> */
    private array $declarers = [];
    /** @var list<object> */
    private array $extensions = [];
    /** @var list<object> */
    private array $functions = [];
    private ?ValueFormatter $formatter = null;

    public function addType(object $declarer): self {
        $this->declarers[] = $declarer;
        return $this;
    }

    public function addExtension(object $extension): self {
        $this->extensions[] = $extension;
        return $this;
    }

    public function addFunctions(object $functions): self {
        $this->functions[] = $functions;
        return $this;
    }

    public function withFormatter(ValueFormatter $formatter): self {
        $this->formatter = $formatter;
        return $this;
    }

    public function build(): Language {
        $declared = DeclaredTypes::read($this->declarers);
        $declarations = (new AttributeReader($declared))->read([...BuiltinMembers::all(), ...$this->extensions], [new BuiltinFunctions(), ...$this->functions]);

        return new Language(Catalog::from($declared->objectTypes, $declarations), $this->formatter ?? new DefaultValueFormatter());
    }
}
