<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Factory Core',
    'description' => 'Factory Core TYPO3 Extension',
    'category' => 'plugin',
    'author' => 'My Agency',
    'state' => 'alpha',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.0.0-13.4.99',
            'content_blocks' => '',
            'headless' => '',
            'nb_headless_content_blocks' => '',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
