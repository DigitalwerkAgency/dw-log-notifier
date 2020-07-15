


# <img src="https://github.com/DigitalwerkAgency/dw-log-notifier/raw/master/Resources/Public/Icons/Extension.svg?sanitize=true" width="40" height="40"/> Log notifier
Typo3 extension send message to slack channel or email, when typo3 catch error.


## Install
Install extension via composer `composer req digitalwerk-agency/dw-log-notifier` and activate it in Extension module

## Setup
After activating extension, write to `public/typo3conf/AdditionalConfiguration.php`:
```php
Digitalwerk\DwLogNotifier\Log\Processor\NotifierLogProcessor::initialize();
```

## Configuration
You can configure dw_log_notifier extension in extensions settings.

