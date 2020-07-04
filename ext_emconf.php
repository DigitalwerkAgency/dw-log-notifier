<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Typo3 Log notifier',
    'description' => 'It sends message to slack channel or to user email when typo3 catch error.',
    'category' => 'be',
    'author' => 'Samuel Mihal, Ondrej Grosko',
    'author_email' => 'samuel.mihal@digitalwerk.agency,ondrej@digitalwerk.agency',
    'author_company' => 'Digitalwerk',
    'state' => 'stable',
    'version' => '0.0.11',
    'constraints' => [
        'depends' => [
            'typo3' => '8.7.0-9.5.999',
            'php' => '7.1.0-7.3.999',
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'Digitalwerk\\DwLogNotifier\\' => 'Classes'
        ]
    ],
];
