<?php
declare(strict_types=1);
namespace Digitalwerk\DwLogNotifier\Domain\Repository;

use Digitalwerk\DwLogNotifier\Domain\Model\Main;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * Class CategoryRepository
 * @package Digitalwerk\DwPageTypes\Domain\Repository
 */
class MainRepository extends Repository
{
    /**
     * @return PersistenceManagerInterface
     */
    public function getPersistenceManager(): PersistenceManagerInterface
    {
        return $this->persistenceManager;
    }

    /**
     * @return Main|null
     */
    public function getCurrent()
    {
        $main = $this->findAll();

        return $main->toArray()[0];
    }
}
