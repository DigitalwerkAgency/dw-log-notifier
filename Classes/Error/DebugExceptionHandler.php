<?php
namespace Digitalwerk\DwLogNotifier\Error;

/**
 * Class DebugExceptionHandler
 * @package Digitalwerk\DwLogNotifier\Error
 */
class DebugExceptionHandler extends \TYPO3\CMS\Core\Error\DebugExceptionHandler
{
    /**
     * @param \Throwable $exception
     * @throws \Exception
     */
    public function handleException(\Throwable $exception)
    {
        parent::handleException($exception);
        new LogException($exception);
    }
}
