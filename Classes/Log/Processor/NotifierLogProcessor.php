<?php
declare(strict_types=1);
namespace Digitalwerk\DwLogNotifier\Log\Processor;

use Digitalwerk\DwLogNotifier\Log\AntiSpam\NotifierLogAntiSpam;
use Maknz\Slack\Client;
use TYPO3\CMS\Core\Log\LogLevel;
use TYPO3\CMS\Core\Log\LogRecord;
use TYPO3\CMS\Core\Log\Processor\AbstractProcessor;
use TYPO3\CMS\Core\Mail\Mailer;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MailUtility;
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
     * Processes a log record and adds additional data.
     *
     * @param \TYPO3\CMS\Core\Log\LogRecord $logRecord The log record to process
     * @return \TYPO3\CMS\Core\Log\LogRecord The processed log record with additional data
     * @throws \TYPO3\CMS\Core\Exception|\Symfony\Component\Mailer\Exception\TransportExceptionInterface
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

            if (LogLevel::isValidLevel((int)$logRecord->getLevel())) {
                if ($this->configuration['email']['addresses']) {
                    try {
                        /** @var MailMessage $email */
                        $email = GeneralUtility::makeInstance(MailMessage::class);
                        $email
                            ->setTo(MailUtility::parseAddresses(
                                $this->configuration['email']['addresses']
                            ))
                            ->subject($this->getSubject($logRecord))
                            ->setSender(MailUtility::getSystemFrom())
                            ->html($this->getBody($logRecord));

                        /** @var Mailer $mailer */
                        $mailer = GeneralUtility::makeInstance(Mailer::class);
                        $mailer->send($email);
                    } catch (\Exception $exception) {
                    }
                }

                try {
                    if ($this->configuration['slack'] && $this->configuration['slack']['webHookUrl']) {
                        $slackClient = new Client($this->configuration['slack']['webHookUrl'], [
                            'username' => $this->configuration['slack']['username'] ?: 'Typo3 notification bot',
                            'channel' => $this->configuration['slack']['channel'],
                            'link_names' => true,
                        ]);

                        $link = $GLOBALS['TYPO3_REQUEST'] ? (string)$GLOBALS['TYPO3_REQUEST']->getUri() : '';
                        $slackClient
                            ->attach([
                                'title' => $this->getSubject($logRecord),
                                'title_link' => $link,
                                'text'     => $logRecord->getMessage(),
                                'color'    => 'danger',
                                'fields' => [
                                    [
                                        'title' => 'Level',
                                        'value' => LogLevel::getName((int)$logRecord->getLevel()),
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
                    try {
                        /** @var MailMessage $email */
                        $email = GeneralUtility::makeInstance(MailMessage::class);
                        $email
                            ->setSubject('Log notifier - slack error')
                            ->setTo(MailUtility::parseAddresses($this->configuration['email']['addresses']))
                            ->setSender(MailUtility::getSystemFrom())
                            ->setBody($e->getMessage());

                        /** @var Mailer $mailer */
                        $mailer = GeneralUtility::makeInstance(Mailer::class);
                        $mailer->send($email);
                    } catch (\Exception $exception) {
                    }
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
            LogLevel::getName((int)$logRecord->getLevel()),
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
                GeneralUtility::getFileAbsFileName(
                    'EXT:dw_log_notifier/Resources/Private/Templates/Log/Processor/EmailProcessorTemplate.html'
                )
            );
            $view->assignMultiple([
                'logLevelName' => LogLevel::getName((int)$logRecord->getLevel()),
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
        if (isset($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['dw_log_notifier']) &&
            is_array($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['dw_log_notifier'])) {
            $dwLogNotifierConfiguration = GeneralUtility::makeInstance(
                \TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class
            )->get('dw_log_notifier');
            $typo3Context = \TYPO3\CMS\Core\Core\Environment::getContext()->__toString();
            self::setProcessor($dwLogNotifierConfiguration, $typo3Context);
        }
    }

    /**
     * @param $dwLogNotifierConfiguration
     * @param $typo3Context
     */
    private static function setProcessor($dwLogNotifierConfiguration, $typo3Context)
    {
        if ($dwLogNotifierConfiguration
            && isset($dwLogNotifierConfiguration['errorLogReporting'])
            && $dwLogNotifierConfiguration['errorLogReporting']['enabled'] === '1'
            && !in_array(
                $typo3Context,
                explode(',', $dwLogNotifierConfiguration['errorLogReporting']['disabledTypo3Context'])
            )
        ) {
            $GLOBALS['TYPO3_CONF_VARS']['LOG']['processorConfiguration'] = [
                \TYPO3\CMS\Core\Log\LogLevel::ERROR => [
                    \Digitalwerk\DwLogNotifier\Log\Processor\NotifierLogProcessor::class => [
                        'configuration' => $dwLogNotifierConfiguration['errorLogReporting']
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
}
