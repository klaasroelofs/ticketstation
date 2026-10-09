-- Test mode is one switch for the whole shop, no longer a setting of the payment plugin: the
-- payment provider follows it. Sites that had test mode on in the active payment plugin get it
-- switched on here when the package is installed (pkg_script.php).

ALTER TABLE `#__ticketstation_config` ADD COLUMN `test_mode` tinyint(1) NOT NULL DEFAULT 0 /** CAN FAIL **/;
