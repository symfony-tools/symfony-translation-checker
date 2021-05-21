<?php

if (!file_exists(__DIR__.'/src')) {
    exit(0);
}

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        'php_unit_dedicate_assert' => ['target' => '5.6'],
        'array_syntax' => ['syntax' => 'short'],
        'fopen_flags' => false,
        'protected_to_private' => false,
        'native_constant_invocation' => true,
        'combine_nested_dirname' => true,
    ])
    ->setRiskyAllowed(true)
    ->setFinder(
        (new PhpCsFixer\Finder())
            ->in(__DIR__.'/src')

    )
    ->setCacheFile('.php-cs-fixer.cache')
;
