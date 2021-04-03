<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Typo3 Log notifier',
    'description' => 'It sends message to slack channel or to user email when typo3 catch error.',
    'category' => 'be',
    'author' => 'Samuel Mihal, Ondrej Grosko',
    'author_email' => 'samuel.mihal@digitalwerk.agency,ondrej@digitalwerk.agency',
    'author_company' => 'Digitalwerk',
    'state' => 'stable',
    'version' => '10.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '10.0.0-10.4.999',
            'php' => '7.2.5-7.3.999',
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'Digitalwerk\\DwLogNotifier\\' => 'Classes'
        ]
    ],
];
