<?php
namespace Digitalwerk\DwLogNotifier\Controller;

use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Core\Utility\GeneralUtility;
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
    public function indexAction()
    {
        if (isset($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['dw_log_notifier'])
            && is_array($GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['dw_log_notifier'])
        ) {
            $dwLogNotifierConfiguration =
                GeneralUtility::makeInstance(\TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class)
                    ->get('dw_log_notifier');
            $dwLogNotifierConfiguration = $dwLogNotifierConfiguration['errorLogReporting'];
            $this->view->assignMultiple([
                'enable' => $dwLogNotifierConfiguration['enabled'],
                'emails' => $dwLogNotifierConfiguration['email']['addresses'],
                'omitExceptions' => $dwLogNotifierConfiguration['omitExceptions'],
                'disabledContext' => $dwLogNotifierConfiguration['disabledTypo3Context'],
                'slack' => $dwLogNotifierConfiguration['slack'],
            ]);
        }
    }
}