<?php
namespace Digitalwerk\DwLogNotifier\Error;

/**
 * Class ProductionExceptionHandler
 * @package Digitalwerk\DwLogNotifier\Error
 */
class ProductionExceptionHandler extends \TYPO3\CMS\Core\Error\ProductionExceptionHandler
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
