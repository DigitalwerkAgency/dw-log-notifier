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
                if ($this->configuration['slack'] && $this->configuration['slack']['webHookUrl']) {
                    $slackClient = new Client($this->configuration['slack']['webHookUrl'], [
                        'username' => $this->configuration['slack']['username'] ?: 'Typo3 notification bot',
                        'channel' => $this->configuration['slack']['channel'],
                        'link_names' => true,
                    ]);

                    $slackClient
                        ->attach([
                            'title' => $this->getSubject($logRecord),
                            'title_link' => $GLOBALS['TYPO3_REQUEST'] ? (string)$GLOBALS['TYPO3_REQUEST']->getUri() : '',
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
                                    'value' => $GLOBALS['TYPO3_REQUEST'] ? (string)$GLOBALS['TYPO3_REQUEST']->getUri() : '',
                                    'short' => true,
                                ],
                            ],
                            'ts' => $logRecord->getCreated()
                        ])
                        ->send();
                }

                if ($this->configuration['email']['addresses']) {
                    $mailMessage = new MailMessage();
                    $mailMessage
                        ->setSubject($this->getSubject($logRecord))
                        ->setTo(MailUtility::parseAddresses($this->configuration['email']['addresses']))
                        ->setSender(MailUtility::getSystemFrom())
                        ->setContentType('text/html')
                        ->setBody($this->getBody($logRecord));
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
            ]);
            return $view->render();
        } catch (\Exception $e) {
            return $e->getMessage();
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
