<?php
namespace Digitalwerk\DwLogNotifier\Error;

use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class LogException
 * @package Digitalwerk\DwLogNotifier\Error
 */
class LogException
{
    /**
     * LogException constructor.
     * @param \Throwable $exception
     */
    public function __construct(\Throwable $exception)
    {
        $this->log($exception);
    }

    /**
     * @param \Throwable $exception
     */
    private function log(\Throwable $exception) {
        /** @var Logger $logger */
        $logger = GeneralUtility::makeInstance(LogManager::class)->getLogger('TYPO3.CMS.Core.Error.ExceptionHandler');
        $exceptionCodeNumber = $exception->getCode() > 0 ? '#' . $exception->getCode() . ': ' : '';
        $logTitle = 'Core: Exception handler';
        $logMessage = 'Uncaught TYPO3 Exception: ' . $exceptionCodeNumber . $exception->getMessage() . ' | '
            . get_class($exception) . ' thrown in file ' . $exception->getFile() . ' in line ' . $exception->getLine();
        $logger->critical($logTitle . ': ' . $logMessage, [
            'TYPO3_MODE' => TYPO3_MODE,
            'exception' => $exception
        ]);
    }
}
