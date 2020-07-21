<?php
declare(strict_types=1);
namespace Digitalwerk\DwLogNotifier\Log\Processor;

use Digitalwerk\DwLogNotifier\Error\DebugExceptionHandler;
use Digitalwerk\DwLogNotifier\Error\ProductionExceptionHandler;
use Digitalwerk\DwLogNotifier\Log\AntiSpam\NotifierLogAntiSpam;
use Maknz\Slack\Client;
use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Log\LogRecord;
use TYPO3\CMS\Core\Log\Processor\AbstractProcessor;
use TYPO3\CMS\Core\Mail\Mailer;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MailUtility;
use TYPO3\CMS\Core\Utility\StringUtility;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * Class NotifierLogProcessor
 * @package Digitalwerk\DwLogNotifier\Log\Processor
 */
class NotifierLogProcessor extends AbstractProcessor
{
    /**
     * Processor configurations
     * @var array
     */
    protected $configuration = [];

    /**
     * @var string
     * Empty string, in Typo3 v8 "dot"
     */
    protected $keyPostFix = '';

    /**
     * Processes a log record and adds additional data.
     *
     * @param \TYPO3\CMS\Core\Log\LogRecord $logRecord The log record to process
     * @return \TYPO3\CMS\Core\Log\LogRecord The processed log record with additional data
     * @throws \TYPO3\CMS\Core\Exception
     */
    public function processLogRecord(\TYPO3\CMS\Core\Log\LogRecord $logRecord)
    {
        /** Omit processing for defined Exceptions codes */
        if ($this->configuration['omitExceptions']) {
            $omitExceptions = GeneralUtility::intExplode(',', $this->configuration['omitExceptions']);
            $exception = $logRecord->getData()['exception'];
            if ($exception instanceof \Exception && \in_array($exception->getCode(), $omitExceptions, true)) {
                return $logRecord;
            }
        }

        $notifierLogAntiSpam = GeneralUtility::makeInstance(NotifierLogAntiSpam::class);
        $notifierLogAntiSpam->setMessage($logRecord->getMessage());

        if ($notifierLogAntiSpam->needSendErrorMessage()) {
            $notifierLogAntiSpam->autoCleanUpJson();
            $notifierLogAntiSpam->writeErrorToJson();

            if (LogLevel::isValidLevel($logRecord->getLevel())) {
                if ($this->configuration['email' . $this->keyPostFix]['addresses']) {
                    $mailMessage = new MailMessage();
                    $mailMessage
                        ->setSubject($this->getSubject($logRecord))
                        ->setTo(MailUtility::parseAddresses($this->configuration['email' . $this->keyPostFix]['addresses']))
                        ->setSender(MailUtility::getSystemFrom())
                        ->setContentType('text/html')
                        ->setBody($this->getBody($logRecord));
                    $mailer = new Mailer();
                    $mailer->send($mailMessage);
                }

                try {
                    if ($this->configuration['slack' . $this->keyPostFix] && $this->configuration['slack' . $this->keyPostFix]['webHookUrl']) {
                        $slackClient = new Client($this->configuration['slack' . $this->keyPostFix]['webHookUrl'], [
                            'username' => $this->configuration['slack' . $this->keyPostFix]['username'] ?: 'Typo3 notification bot',
                            'channel' => $this->configuration['slack' . $this->keyPostFix]['channel'],
                            'link_names' => true,
                        ]);

                        /** Different get current link in Typo3 v8 */
                        if ($this->keyPostFix) {
                            $link = GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL');
                        } else {
                            $link = $GLOBALS['TYPO3_REQUEST'] ? (string)$GLOBALS['TYPO3_REQUEST']->getUri() : '';
                        }

                        $slackClient
                            ->attach([
                                'title' => $this->getSubject($logRecord),
                                'title_link' => $link,
                                'text'     => $logRecord->getMessage(),
                                'color'    => 'danger',
                                'fields' => [
                                    [
                                        'title' => 'Level',
                                        'value' => LogLevel::getName($logRecord->getLevel()),
                                        'short' => true,
                                    ],
                                    [
                                        'title' => 'Link',
                                        'value' => $link,
                                        'short' => true,
                                    ],
                                ],
                                'ts' => $logRecord->getCreated()
                            ])
                            ->send();
                    }
                } catch (\Exception $e) {
                    $mailMessage = new MailMessage();
                    $mailMessage
                        ->setSubject('Log notifier - slack error')
                        ->setTo(MailUtility::parseAddresses($this->configuration['email' . $this->keyPostFix]['addresses']))
                        ->setSender(MailUtility::getSystemFrom())
                        ->setContentType('text/html')
                        ->setBody($e->getMessage());
                    $mailer = new Mailer();
                    $mailer->send($mailMessage);
                }
            }
        }
        return $logRecord;
    }

    /**
     * @param LogRecord $logRecord
     * @return string
     */
    protected function getSubject(LogRecord $logRecord): string
    {
        return \sprintf(
            "New %s occured on %s",
            LogLevel::getName($logRecord->getLevel()),
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename']
        );
    }

    /**
     * @param LogRecord $logRecord
     * @return string
     */
    protected function getBody(LogRecord $logRecord): string
    {
        try {
            $view = GeneralUtility::makeInstance(StandaloneView::class);
            $view->setTemplatePathAndFilename(
                GeneralUtility::getFileAbsFileName('EXT:dw_log_notifier/Resources/Private/Templates/Log/Processor/EmailProcessorTemplate.html')
            );
            $view->assignMultiple([
                'logLevelName' => LogLevel::getName($logRecord->getLevel()),
                'siteName' => $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'],
                'logRecordDump' => DebuggerUtility::var_dump($logRecord, 'Log Record', 8, false, true, true),
                'request' => $GLOBALS['TYPO3_REQUEST'],
                'time' => \date(\DATE_RFC1036, (int)$logRecord->getCreated()),
                'logRecord' => $logRecord,
                'GET' => $_GET,
                'POST' => $_POST,
                'SERVER' => $_SERVER
            ]);
            return $view->render();
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * Register log processor
     */
    public static function initialize()
    {
        if (StringUtility::beginsWith(TYPO3_branch, '8')) {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['debugExceptionHandler'] = DebugExceptionHandler::class;
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['productionExceptionHandler'] = ProductionExceptionHandler::class;
            $dwLogNotifierConfiguration = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['dw_log_notifier']);
            $typo3Context = (string)GeneralUtility::getApplicationContext();
            self::setProcessor($dwLogNotifierConfiguration, $typo3Context, '.');
        } else {
            if (isset($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['dw_log_notifier']) && is_array($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['dw_log_notifier'])) {
                $dwLogNotifierConfiguration = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class)->get('dw_log_notifier');
                $typo3Context = \TYPO3\CMS\Core\Core\Environment::getContext()->__toString();
                self::setProcessor($dwLogNotifierConfiguration, $typo3Context, '');
            }
        }
    }

    /**
     * @param $dwLogNotifierConfiguration
     * @param $typo3Context
     * @param $keyPostFix
     */
    private static function setProcessor($dwLogNotifierConfiguration, $typo3Context, $keyPostFix) {
        if ($dwLogNotifierConfiguration
            && isset($dwLogNotifierConfiguration['errorLogReporting' . $keyPostFix])
            && $dwLogNotifierConfiguration['errorLogReporting' . $keyPostFix]['enabled'] === '1'
            && !in_array($typo3Context, explode(',', $dwLogNotifierConfiguration['errorLogReporting' . $keyPostFix]['disabledTypo3Context']))
        ) {
            $GLOBALS['TYPO3_CONF_VARS']['LOG']['processorConfiguration'] = [
                \TYPO3\CMS\Core\Log\LogLevel::ERROR => [
                    \Digitalwerk\DwLogNotifier\Log\Processor\NotifierLogProcessor::class => [
                        'configuration' => $dwLogNotifierConfiguration['errorLogReporting' . $keyPostFix],
                        'keyPostFix' => $keyPostFix,
                    ]
                ]
            ];
        }
    }

    /**
     * @return array
     */
    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    /**
     * @param array $configuration
     */
    public function setConfiguration(array $configuration): void
    {
        $this->configuration = $configuration;
    }

    /**
     * @return string
     */
    public function getKeyPostFix(): string
    {
        return $this->keyPostFix;
    }

    /**
     * @param string $keyPostFix
     */
    public function setKeyPostFix(string $keyPostFix): void
    {
        $this->keyPostFix = $keyPostFix;
    }
}