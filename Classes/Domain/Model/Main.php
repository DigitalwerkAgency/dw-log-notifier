<?php
declare(strict_types=1);
namespace Digitalwerk\DwLogNotifier\Domain\Model;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * Class Main
 * @package Digitalwerk\DwLogNotifier\Domain\Model
 */
class Main extends AbstractEntity
{
    /**
     * @var bool
     */
    protected $enable = false;

    /**
     * @var string
     */
    protected $omitExceptions = '';

    /**
     * @var string
     */
    protected $emails = '';

    /**
     * @var string
     */
    protected $disabledTypo3Context = '';

    /**
     * @var string
     */
    protected $slackWebHookUrl = '';

    /**
     * @var string
     */
    protected $slackChannel = '';

    /**
     * @var string
     */
    protected $slackUsername = '';

    /**
     * @return bool
     */
    public function isEnable(): bool
    {
        return $this->enable;
    }

    /**
     * @param bool $enable
     */
    public function setEnable(bool $enable): void
    {
        $this->enable = $enable;
    }

    /**
     * @return string
     */
    public function getOmitExceptions(): string
    {
        return $this->omitExceptions;
    }

    /**
     * @param $property
     * @return array
     */
    public function getArrayOfProperty($property): array
    {
        return array_filter(explode(',', $property));
    }

    /**
     * @param string $omitExceptions
     */
    public function setOmitExceptions(string $omitExceptions): void
    {
        $omitExceptionsArray = $this->getArrayOfProperty($this->getOmitExceptions());
        $omitExceptionsArray[] = $omitExceptions;
        $this->setStringOfArrayProperty($omitExceptionsArray, 'omitExceptions');
    }

    /**
     * @param array $array
     * @param $propertyName
     * @return void
     */
    public function setStringOfArrayProperty(array $array, $propertyName): void
    {
        $this->$propertyName = $array ? implode(',', array_unique($array)) : '';
    }

    /**
     * @return string
     */
    public function getEmails(): string
    {
        return $this->emails;
    }

    /**
     * @param $emails
     */
    public function setEmails($emails): void
    {
        $emailsArray = $this->getArrayOfProperty($this->getEmails());
        $emailsArray[] = $emails;
        $this->setStringOfArrayProperty($emailsArray, 'emails');
    }

    /**
     * @return string
     */
    public function getDisabledTypo3Context()
    {
        return trim($this->disabledTypo3Context);
    }

    /**
     * @param string $disabledTypo3Context
     */
    public function setDisabledTypo3Context($disabledTypo3Context): void
    {
        $emailsArray = $this->getArrayOfProperty($this->getDisabledTypo3Context());
        $emailsArray[] = $disabledTypo3Context;
        $this->setStringOfArrayProperty($emailsArray, 'disabledTypo3Context');
    }

    /**
     * @return string
     */
    public function getSlackWebHookUrl(): string
    {
        return $this->slackWebHookUrl;
    }

    /**
     * @param string $slackWebHookUrl
     */
    public function setSlackWebHookUrl(string $slackWebHookUrl): void
    {
        $this->slackWebHookUrl = $slackWebHookUrl;
    }

    /**
     * @return string
     */
    public function getSlackChannel(): string
    {
        return $this->slackChannel;
    }

    /**
     * @param string $slackChannel
     */
    public function setSlackChannel(string $slackChannel): void
    {
        $this->slackChannel = $slackChannel;
    }

    /**
     * @return string
     */
    public function getSlackUsername(): string
    {
        return $this->slackUsername;
    }

    /**
     * @param string $slackUsername
     */
    public function setSlackUsername(string $slackUsername): void
    {
        $this->slackUsername = $slackUsername;
    }

    /**
     * @return Main
     */
    public function initialize(): Main
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('tx_dwlognotifier_domain_model_main')->createQueryBuilder();
        $queryBuilder->select('*')->from('tx_dwlognotifier_domain_model_main');

        return unserialize(
            $queryBuilder->execute()->fetchAll()[0]['serializable_log_notifier_object']
        );
    }
}
