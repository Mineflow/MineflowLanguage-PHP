<?php
declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules([
        "encoding" => true,
        "full_opening_tag" => true,
        "declare_strict_types" => true,
        "blank_line_after_opening_tag" => false,
        "blank_line_after_namespace" => true,
        "single_line_after_imports" => true,
        "no_leading_import_slash" => true,
        "no_unused_imports" => true,
        "ordered_imports" => ["sort_algorithm" => "alpha", "imports_order" => ["class", "function", "const"]],
        "array_syntax" => ["syntax" => "short"],
        "trailing_comma_in_multiline" => ["elements" => ["arrays"]],
        "no_empty_statement" => true,
        "no_useless_else" => true,
        "no_useless_return" => true,
        "no_unneeded_control_parentheses" => true,
        "lowercase_keywords" => true,
        "lowercase_static_reference" => true,
        "constant_case" => true,
        "visibility_required" => true,
        "return_type_declaration" => ["space_before" => "none"],
        "short_scalar_cast" => true,
        "cast_spaces" => ["space" => "none"],
        "concat_space" => ["spacing" => "none"],
        "indentation_type" => true,
        "line_ending" => true,
        "no_trailing_whitespace" => true,
        "no_whitespace_in_blank_line" => true,
        "single_blank_line_at_eof" => true,
        "phpdoc_line_span" => [
            "class" => "multi",
            "function" => "multi",
            "method" => "multi",
            "property" => "single",
            "const" => "single",
            "case" => "single",
        ],
    ])
    ->setFinder((new Finder())->in([__DIR__."/src", __DIR__."/tests"]));
