<?php
namespace Digitalwerk\DwLogNotifier\Controller;

use Digitalwerk\DwLogNotifier\Domain\Model\Main;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Annotation\Inject;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;

/**
 * Class BackendController
 * @package Digitalwerk\DwLogNotifier\Controller
 */
class BackendController extends ActionController
{
    /**
     * Backend Template Container
     *
     * @var string
     */
    protected $defaultViewObjectName = \TYPO3\CMS\Backend\View\BackendTemplateView::class;

    /**
     * @var \Digitalwerk\DwLogNotifier\Domain\Repository\MainRepository
     * @Inject()
     */
    protected $mainRepository = null;

    /**
     * Set up the doc header properly here
     *
     * @param ViewInterface $view
     * @return void
     */
    protected function initializeView(ViewInterface $view)
    {
        /** @var BackendTemplateView $view */
        parent::initializeView($view);
        if ($view instanceof BackendTemplateView) {
            $view->getModuleTemplate()->getPageRenderer()->loadRequireJsModule('TYPO3/CMS/Backend/Modal');
        }
    }

    /**
     * The index action
     * @return void
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException
     */
	function indexAction()
	{
	    /** @var Main $main */
        $main = $this->mainRepository->getCurrent();
        if (empty($main)) {
	        $main = new Main();
            $this->mainRepository->add($main);
            $this->mainRepository->getPersistenceManager()->persistAll();
        }

        $this->updateSerializedObject($main);
        $this->view->assignMultiple([
			'main' => $main,
		]);
	}

    /**
     * Status action
     * @param Main $main
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\UnsupportedRequestTypeException
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException
     */
    public function statusAction(Main $main)
    {
        if ($main->isEnable()) {
            $main->setEnable(false);
        } else {
            $main->setEnable(true);
        }
        $this->mainRepository->update($main);
        $this->mainRepository->getPersistenceManager()->persistAll();
        $this->redirect('index');
    }

    /**
     * Update action
     * @param Main $main
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\UnsupportedRequestTypeException
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException
     */
    public function updateAction(Main $main)
    {
        $this->mainRepository->update($main);
        $this->mainRepository->getPersistenceManager()->persistAll();
        $this->redirect('index');
    }

    /**
     * Delete omit exception action
     * @param Main $main
     * @param $id
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\UnsupportedRequestTypeException
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException
     */
    public function deleteOmitExceptionsItemAction(Main $main, int $id)
    {
        $omitExceptions = $main->getArrayOfProperty($main->getOmitExceptions());
        unset($omitExceptions[$id]);
        $main->setStringOfArrayProperty($omitExceptions, 'omitExceptions');
        $this->updateAction($main);
    }

    /**
     * Delete email action
     * @param Main $main
     * @param int $id
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\UnsupportedRequestTypeException
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException
     */
    public function deleteEmailsItemAction(Main $main, int $id)
    {
        $emails = $main->getArrayOfProperty($main->getEmails());
        unset($emails[$id]);
        $main->setStringOfArrayProperty($emails, 'emails');
        $this->updateAction($main);
    }

    /**
     * Delete email action
     * @param Main $main
     * @param int $id
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\UnsupportedRequestTypeException
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException
     */
    public function deleteDisabledTypo3ContextItemAction(Main $main, int $id)
    {
        $disabledTypo3Context = $main->getArrayOfProperty($main->getDisabledTypo3Context());
        unset($disabledTypo3Context[$id]);
        $main->setStringOfArrayProperty($disabledTypo3Context, 'disabledTypo3Context');
        $this->updateAction($main);
    }

    /**
     * @param Main $main
     */
    private function updateSerializedObject(Main $main)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_dwlognotifier_domain_model_main');
        $queryBuilder
            ->update('tx_dwlognotifier_domain_model_main')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($main->getUid()))
            )
            ->set('serializable_log_notifier_object', serialize($main))
            ->execute();
    }
}
