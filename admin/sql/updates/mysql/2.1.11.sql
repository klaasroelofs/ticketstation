-- Removes the show_eventlistnote config column: it was never read anywhere and there was no note text to show.
ALTER TABLE `#__ticketstation_config` DROP COLUMN `show_eventlistnote`;
