-- Checkout fields can be linked to a custom field of the user (Users > Fields), to fill them in
-- for a logged-in visitor. JSON: checkout field name => id of the user field.
ALTER TABLE `#__ticketstation_config` ADD COLUMN `checkout_field_map` varchar(1000) NOT NULL DEFAULT '' /** CAN FAIL **/;
