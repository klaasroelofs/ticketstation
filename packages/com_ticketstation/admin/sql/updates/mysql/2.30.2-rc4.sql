-- The countries table still had a "requires VAT" flag from the original Ticketmaster component.
-- Nothing reads it any more, so the column is removed.

ALTER TABLE `#__ticketstation_country` DROP COLUMN `requires_vat` /** CAN FAIL **/;
