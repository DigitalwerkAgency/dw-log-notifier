<?php
/**
 * @var string $_EXTKEY
 */
defined('TYPO3_MODE') or die('Access denied.');

call_user_func(function ($extKey) {
    if (TYPO3_MODE === 'BE' && !(TYPO3_REQUESTTYPE & TYPO3_REQUESTTYPE_INSTALL)) {

        /**
         * Register Backend Module
         */
        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
            'Digitalwerk.' . $extKey,
            'tools',
            'backend',
            '',
            [
                'Backend' => 'index,status,update,deleteOmitExceptionsItem,deleteEmailsItem,deleteDisabledTypo3ContextItem',
            ],
            [
                'access' => 'user,group',
                'icon' => 'EXT:' . $extKey . '/Resources/Public/Icons/Extension.svg',
                'labels' => 'LLL:EXT:' . $extKey . '/Resources/Private/Languages/locallang_backend.xlf',
            ]
        );
    }
}, 'dw_log_notifier');
