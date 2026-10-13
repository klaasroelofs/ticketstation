-- Configuration columns that nothing reads or writes any more: settings of the Ticketmaster era
-- (ticket layout positions and barcode sizes, now stored per ticket; Mailchimp; redirects after
-- login; the old invoice and download-button switches) and a few that were never wired up.
ALTER TABLE `#__ticketstation_config` DROP COLUMN `bar_position` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` DROP COLUMN `barcode_1d_x` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` DROP COLUMN `barcode_1d_y` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` DROP COLUMN `barcode_2d_x` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` DROP COLUMN `barcode_2d_y` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` DROP COLUMN `barcode_type` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` DROP COLUMN `company_logo_position` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` DROP COLUMN `create_invoice` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` DROP COLUMN `currencytype` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` DROP COLUMN `date_position` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` DROP COLUMN `downloadid` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` DROP COLUMN `free_text1_position` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` DROP COLUMN `free_text2_position` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` DROP COLUMN `hide_invoice_msg_cpanel` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` DROP COLUMN `invoice_eid` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` DROP COLUMN `invoice_logo` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` DROP COLUMN `location_position` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` DROP COLUMN `mailchimp_api` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` DROP COLUMN `mailchimp_listid` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` DROP COLUMN `name_position` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` DROP COLUMN `orderid_position` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` DROP COLUMN `payment_email_send` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` DROP COLUMN `position_logo_confirmation` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` DROP COLUMN `position_seatnumber` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` DROP COLUMN `price_position` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` DROP COLUMN `redirect_after_login` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` DROP COLUMN `redirect_after_registration` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` DROP COLUMN `scan_api` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` DROP COLUMN `send_confirmation_pdf` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` DROP COLUMN `show_downoad_button_in_myorders` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` DROP COLUMN `show_mailchmp` /** CAN FAIL **/;
