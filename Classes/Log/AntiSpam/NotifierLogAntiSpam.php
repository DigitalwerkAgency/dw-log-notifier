<?php
declare(strict_types=1);
namespace Digitalwerk\DwLogNotifier\Log\AntiSpam;

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Security\Cryptography\HashService;

/**
 * Class NotifierLogAntiSpam
 * @package Digitalwerk\DwLogNotifier\Log\AntiSpam
 */
class NotifierLogAntiSpam
{
    /**
     * @var int
     * In hours
     */
    protected $validMessageTime = 1;

    /**
     * @var string
     */
    protected $message = '';

    /**
     * @var int
     */
    protected $maxJsonFileBytes = 8000;

    /**
     * @var string
     */
    protected $jsonFilePath = 'EXT:dw_log_notifier/Classes/Log/AntiSpam/NotifierLogAntiSpamJson.json';

    /**
     * @var HashService
     */
    protected $hashService = null;

    /**
     * NotifierLogAntiSpam constructor.
     */
    public function __construct()
    {
        $this->hashService = GeneralUtility::makeInstance(HashService::class);
    }

    /**
     * @return string
     */
    public function getJsonFilePath(): string
    {
        return GeneralUtility::getFileAbsFileName($this->jsonFilePath);
    }

    /**
     * @return mixed
     */
    public function getJsonContent()
    {
        $content = $this->getJsonFilePath() ? file_get_contents($this->getJsonFilePath()) : '';
        if ($content) {
            return json_decode($content, true);
        }
    }

    /**
     * @return string
     */
    public function getError()
    {
        return $this->hashService->generateHmac($this->getMessage());
    }

    /**
     * @return bool
     */
    public function isErrorInJson()
    {
        if ($this->getJsonContent()) {
            return array_key_exists($this->getError(), $this->getJsonContent());
        }
    }

    /**
     * @return bool
     */
    public function isErrorTimeValid()
    {
        if ($this->isErrorInJson()) {
            $errorTime = $this->getJsonContent()[$this->getError()];

            return $errorTime > time();
        }
    }

    /**
     * @return bool
     */
    public function needSendErrorMessage(): bool
    {
        return !$this->isErrorInJson() || !$this->isErrorTimeValid();
    }


    public function writeErrorToJson()
    {
        if ($this->getJsonContent()) {
            $jsonContent = $this->getJsonContent();
        }

        $jsonContent[$this->getError()] = time() + $this->validMessageTime * 60 * 60;
        if ($this->getJsonFilePath()) {
            file_put_contents(
                $this->getJsonFilePath(),
                json_encode($jsonContent, JSON_PRETTY_PRINT)
            );
        }
    }

    /**
     * Auto delete errors from json
     */
    public function autoCleanUpJson()
    {
        if ($this->getJsonFilePath() && (filesize($this->getJsonFilePath()) >= $this->maxJsonFileBytes)) {
            file_put_contents(
                $this->getJsonFilePath(),
                json_encode([], JSON_PRETTY_PRINT)
            );
        }
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @param string $message
     */
    public function setMessage(string $message)
    {
        $this->message = $message;
    }
}
