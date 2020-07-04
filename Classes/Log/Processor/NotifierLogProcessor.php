<?php
declare(strict_types=1);
namespace Digitalwerk\DwLogNotifier\Log\Processor;

use Digitalwerk\DwLogNotifier\Domain\Model\Main;
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
     * @var string
     */
    protected $typo3Context = '';

    /**
     * @return string
     */
    public function getTypo3Context(): string
    {
        return $this->typo3Context;
    }

    /**
     * @param string $typo3Context
     */
    public function setTypo3Context(string $typo3Context): void
    {
        $this->typo3Context = $typo3Context;
    }

    /**
     * Processes a log record and adds additional data.
     *
     * @param \TYPO3\CMS\Core\Log\LogRecord $logRecord The log record to process
     * @return \TYPO3\CMS\Core\Log\LogRecord The processed log record with additional data
     * @throws \TYPO3\CMS\Core\Exception
     */
    public function processLogRecord(\TYPO3\CMS\Core\Log\LogRecord $logRecord)
    {
        /** @var Main $main */
        $main = GeneralUtility::makeInstance(Main::class)->initialize();
        if (
            isset($main) && $main &&
            $main->isEnable() &&
            !in_array($this->typo3Context, explode(',', $main->getDisabledTypo3Context()))
        ) {
            /** Omit processing for defined Exceptions codes */
            if ($main->getOmitExceptions()) {
                $omitExceptions = GeneralUtility::intExplode(',', $main->getOmitExceptions());
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
                    if ($main->getEmails()) {
                        $mailMessage = new MailMessage();
                        $mailMessage
                            ->setSubject($this->getSubject($logRecord))
                            ->setTo(MailUtility::parseAddresses($main->getEmails()))
                            ->setSender(MailUtility::getSystemFrom())
                            ->setContentType('text/html')
                            ->setBody($this->getBody($logRecord));
                        $mailer = new Mailer();
                        $mailer->send($mailMessage);
                    }

                    try {
                        if ($main->getSlackWebHookUrl()) {
                            $slackClient = new Client($main->getSlackWebHookUrl(), [
                                'username' => $main->getSlackUsername() ?: 'Typo3 notification bot',
                                'channel' => $main->getSlackChannel(),
                                'link_names' => true,
                            ]);

                            /** Different get current link in Typo3 v8 */
                            if (StringUtility::beginsWith(TYPO3_branch, '8')) {
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
                            ->setTo(MailUtility::parseAddresses($main->getEmails()))
                            ->setSender(MailUtility::getSystemFrom())
                            ->setContentType('text/html')
                            ->setBody($e->getMessage());
                        $mailer = new Mailer();
                        $mailer->send($mailMessage);
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
     * Register log processor
     */
    public static function initialize()
    {
        if (StringUtility::beginsWith(TYPO3_branch, '8')) {
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['debugExceptionHandler'] = DebugExceptionHandler::class;
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['productionExceptionHandler'] = ProductionExceptionHandler::class;
            $typo3Context = (string)GeneralUtility::getApplicationContext();
            self::setProcessor($typo3Context);
        } else {
            $typo3Context = \TYPO3\CMS\Core\Core\Environment::getContext()->__toString();
            self::setProcessor($typo3Context);
        }
    }

    /**
     * @param $typo3Context
     */
    private static function setProcessor($typo3Context)
    {
        $GLOBALS['TYPO3_CONF_VARS']['LOG']['processorConfiguration'] = [
            \TYPO3\CMS\Core\Log\LogLevel::ERROR => [
                \Digitalwerk\DwLogNotifier\Log\Processor\NotifierLogProcessor::class => [
                    'typo3Context' => $typo3Context
                ]
            ]
        ];
    }
}
