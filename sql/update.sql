-- Copyright (C) 2021-2023 EVARISK <technique@evarisk.com>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see https://www.gnu.org/licenses/.

-- 1.4.0
ALTER TABLE `llx_dolisirh_timesheet` DROP `last_main_doc`, DROP `model_pdf`, DROP `model_odt`;
ALTER TABLE `llx_element_workinghours` CHANGE `tms` `tms` TIMESTAMP on update CURRENT_TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE `llx_element_workinghours` ADD `fk_user_modif` INT NULL AFTER `fk_user_creat`;

RENAME TABLE `llx_categorie_invoice` TO `llx_categorie_facture`;
ALTER TABLE `llx_categorie_facture` CHANGE `fk_invoice` `fk_facture` INT NOT NULL;

RENAME TABLE `llx_categorie_invoicerec` TO `llx_categorie_facturerec`;
ALTER TABLE `llx_categorie_facturerec` CHANGE `fk_invoicerec` `fk_facturerec` INT NOT NULL;

ALTER TABLE `llx_dolisirh_timesheetdet` ADD `entity` INT NOT NULL DEFAULT '1' AFTER `rowid`;
