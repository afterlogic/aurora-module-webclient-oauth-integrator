<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->exclude([
        'Classes/OAuthClient', // 👈 вот это
    ]);

return (new PhpCsFixer\Config())
    ->setFinder($finder);