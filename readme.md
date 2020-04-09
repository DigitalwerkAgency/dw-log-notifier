


# <img src="https://github.com/DigitalwerkAgency/dw-log-notifier/raw/master/Resources/Public/Icons/Extension.svg?sanitize=true" width="40" height="40"/> Log notifier
Typo3 extension send message to slack channel or email, when typo3 catch error.


## Install
Install extension via composer `composer req digitalwerk-agency/dw-log-notifier` and activate it in Extension module


## Setup
After activating extension, write to `public/typo3conf/AdditionalConfiguration.php`:
```php
/**
 * Notify by mail when error is logged
 */
$dwLogNotifierConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class)
    ->get('dw_log_notifier');
if (isset($dwLogNotifierConfiguration['errorLogReporting'])
    && $dwLogNotifierConfiguration['errorLogReporting']['enabled'] === '1'
    && !in_array(Environment::getContext()->__toString(), explode(',', $dwLogNotifierConfiguration['errorLogReporting']['disabledTypo3Context']))
) {
    $GLOBALS['TYPO3_CONF_VARS']['LOG']['processorConfiguration'] = [
        \TYPO3\CMS\Core\Log\LogLevel::ERROR => [
            \Digitalwerk\DwLogNotifier\Log\Processor\NotifierLogProcessor::class => [
                'configuration' => $dwLogNotifierConfiguration['errorLogReporting'],
            ]
        ]
    ];

}
```

And to `public/typo3conf/LocalConfiguration.php` to `EXTENSIONS`:
```php
'EXTENSIONS' => [
    'dw_log_notifier' => [
        'errorLogReporting' => [
            'disabledTypo3Context' => '',
            'email' => [
                'addresses' => '',
            ],
            'enabled' => '1',
            'omitExceptions' => '',
            'slack' => [
                'channel' => '',
                'username' => '',
                'webHookUrl' => '',
            ],
        ],
    ],
];
```

## Configuration
You can configure dw_log_notifier extension in extensions settings.

