-- What happens to the tickets of a refund is stored with the refund and only carried out once the
-- refund can't be cancelled any more at Mollie (processing or refunded), so cancelling a refund in
-- Mollie leaves the tickets as they were. treatments: JSON orderid => refund_state; applied: when
-- it was carried out.
ALTER TABLE `#__ticketstation_refunds` ADD COLUMN `treatments` text DEFAULT NULL /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_refunds` ADD COLUMN `applied` datetime DEFAULT NULL /** CAN FAIL **/;
