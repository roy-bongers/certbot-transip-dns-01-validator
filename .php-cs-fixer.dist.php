<?php

use Symfony\Component\Finder\Finder;
use PhpCsFixer\Config;

$finder = Finder::create()
    ->in(__DIR__)
    ->exclude(['config', 'docs', 'logs', 'vendor'])
    ->name('auth-hook')
    ->name('cleanup-hook')
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new Config())
    ->setRules(
        [
            '@Symfony'                          => true,
            'array_syntax'                      => ['syntax' => 'short'],
            'no_unused_imports'                 => true,
            'trailing_comma_in_multiline'       => ['elements' => ['arrays']],
            'increment_style'                   => ['style' => 'post'],
            'concat_space'                      => ['spacing' => 'one'],
            'single_line_throw'                 => false,
        ]
    )->setFinder($finder);
