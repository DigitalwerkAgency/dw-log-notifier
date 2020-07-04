#
# Table structure for table 'tx_dwlognotifier_domain_model_main'
#
CREATE TABLE tx_dwlognotifier_domain_model_main (
    enable int(3) DEFAULT '0' NOT NULL,
    omit_exceptions text DEFAULT '' NOT NULL,
    emails text DEFAULT '' NOT NULL,
    slack_web_hook_url text DEFAULT '' NOT NULL,
    slack_channel text DEFAULT '' NOT NULL,
    slack_username text DEFAULT '' NOT NULL,
    disabled_typo3_context text DEFAULT '' NOT NULL,
    serializable_log_notifier_object text DEFAULT '' NOT NULL
);
