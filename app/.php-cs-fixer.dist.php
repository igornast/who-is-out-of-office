<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests');
;

return new PhpCsFixer\Config()
    ->setParallelConfig(\PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setUnsupportedPhpVersionAllowed(true)
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        '@PSR1' => true,
        '@PSR2' => true,
        '@PSR12' => true,
        'array_indentation' => true,
        'assign_null_coalescing_to_coalesce_equal' => true,
        'attribute_empty_parentheses' => true,
        'combine_consecutive_issets' => true,
        'combine_consecutive_unsets' => true,
        'simplified_null_return' => true,
        'ternary_to_null_coalescing' => true,
        'trim_array_spaces' => true,
        'use_arrow_functions' => true,
        'modifier_keywords' => true,
        'method_argument_space' => ['on_multiline' => 'ensure_fully_multiline'],
        'binary_operator_spaces' => [
            'operators' => ['=' => 'single_space'],
        ],
    ])
    ->setFinder($finder)
;
