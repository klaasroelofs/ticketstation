-- The Box Office "Order Reference" gets its own name: the table #__ticketstation_remarks becomes
-- #__ticketstation_order_references, its column remarks becomes order_reference, and the history
-- entries of the reference get event types that say so.
RENAME TABLE `#__ticketstation_remarks` TO `#__ticketstation_order_references` /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_order_references` CHANGE `remarks` `order_reference` varchar(255) NOT NULL DEFAULT '' /** CAN FAIL **/;
UPDATE `#__ticketstation_history` SET `event_type` = 'order_reference_updated' WHERE `event_type` = 'remark_updated';
UPDATE `#__ticketstation_history` SET `event_type` = 'order_reference_removed' WHERE `event_type` = 'remark_removed';
