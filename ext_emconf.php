<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Factory Core',
    'description' => 'TYPO3 extension for the LABOR.digital Factory headless CMS boilerplate — Content Blocks, record types, tenant-scope enforcement, and provisioning CLIs.',
    'category' => 'plugin',
    'author' => 'LABOR.digital',
    'author_email' => 'info@labor.digital',
    'author_company' => 'LABOR.digital - Agentur für moderne Kommunikation GmbH',
    'state' => 'beta',
    'version' => '0.3.2', // x-release-please-version
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
