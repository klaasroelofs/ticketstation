-- The notation of prices is set with four settings instead of one of twelve fixed notations:
-- the number of decimals (-1 = only when the amount isn't whole), the decimal point, the thousands
-- separator and whether the currency symbol follows the amount. The old notation is carried over.
ALTER TABLE `#__ticketstation_config` ADD COLUMN `price_decimals` tinyint(2) NOT NULL DEFAULT 2 /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` ADD COLUMN `price_decimal_sep` varchar(4) NOT NULL DEFAULT ',' /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` ADD COLUMN `price_thousands_sep` varchar(4) NOT NULL DEFAULT '' /** CAN FAIL **/;
ALTER TABLE `#__ticketstation_config` ADD COLUMN `price_symbol_after` tinyint(1) NOT NULL DEFAULT 0 /** CAN FAIL **/;
UPDATE `#__ticketstation_config` SET
  `price_decimals` = IF(`priceformat` IN (9, 10, 11, 12), 0, 2),
  `price_decimal_sep` = IF(`priceformat` IN (5, 6, 7, 8), '.', ','),
  `price_thousands_sep` = CASE
      WHEN `priceformat` IN (1, 2, 11, 12) THEN '.'
      WHEN `priceformat` IN (7, 8, 9, 10) THEN ','
      ELSE '' END,
  `price_symbol_after` = IF(`priceformat` IN (2, 4, 6, 7, 9, 11), 1, 0);

-- Custom date and time notations of up to 32 characters
ALTER TABLE `#__ticketstation_config` MODIFY `dateformat` varchar(32) NOT NULL;
ALTER TABLE `#__ticketstation_config` MODIFY `time_format` varchar(32) NOT NULL DEFAULT 'H:i';
