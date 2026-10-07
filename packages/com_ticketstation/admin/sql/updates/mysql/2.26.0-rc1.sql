-- How the customer orders: 0 = the classic steps (cart, details, payment), 1 = the combined page
-- (details and payment on one page; from 2.26.0-rc2 the cart as well).
ALTER TABLE `#__ticketstation_config` ADD COLUMN `checkout_layout` tinyint(1) NOT NULL DEFAULT 0 /** CAN FAIL **/;
