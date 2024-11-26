<?php
$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests']);

return (new PhpCsFixer\Config())
    // Currently disabled due to the current status of this feature (experimental)
    //->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setRiskyAllowed(false)
    ->setRules([
        '@PSR12' => true,
        '@Symfony' => true,
        '@PHP81Migration' => true,
        'lowercase_static_reference' => false,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_class_elements' => false,
        'fully_qualified_strict_types' => [
            'import_symbols' => true,
            'leading_backslash_in_global_namespace' => true,
        ],
        'global_namespace_import' => [
            'import_constants' => true,
            'import_functions' => true,
        ],
        'single_import_per_statement' => true,
        'ordered_imports' => true,
        'assign_null_coalescing_to_coalesce_equal' => false,
        'trailing_comma_in_multiline' => true,
        'blank_line_between_import_groups' => true,
        'nullable_type_declaration' => [
            'syntax' => 'union'
        ],
        'blank_line_before_statement' => [
            'statements' => ['break', 'continue', 'declare', 'return', 'throw', 'try']
        ],
        'list_syntax' => false,
        'no_unused_imports' => true,
        'single_space_around_construct' => true,
        'header_comment' => [
            'header' => '© Eloo <info@eloo.nl> This bundle of is a fork of the JMS Job Queue Bundle and is thus NOT part of the Trade Secret license.',
            'comment_type' => 'PHPDoc',
            'location' => 'after_open',
            'separate' => 'bottom'
        ]
    ])
    ->setFinder($finder);
