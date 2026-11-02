<?php
declare(strict_types=1);

namespace Mineflow\Language;

use Mineflow\Language\Format\DefaultValueFormatter;
use Mineflow\Language\Format\ValueFormatter;
use Mineflow\Language\PhpBinding\AttributeReader;
use Mineflow\Language\StandardLibrary\BuiltinFunctions;
use Mineflow\Language\StandardLibrary\StandardTypes;

final class LanguageBuilder {
    /** @var list<class-string> */
    private array $types = [];
    /** @var list<object> */
    private array $extensions = [];
    /** @var list<object> */
    private array $functions = [];
    private ?ValueFormatter $formatter = null;

    /**
     * Adds the type a Value class or interface declares with #[DeclareType].
     * @param class-string $class
     */
    public function addType(string $class): self {
        $this->types[] = $class;
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
        $members = AttributeReader::read([...StandardTypes::CLASSES, ...$this->types], $this->extensions, [new BuiltinFunctions(), ...$this->functions]);

        return new Language($members, $this->formatter ?? new DefaultValueFormatter());
    }
}
