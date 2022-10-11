-- Copyright (C) 2023 EVARISK <dev@evarisk.com>
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

ALTER TABLE llx_dolisirh_certificate ADD INDEX idx_dolisirh_certificate_rowid (rowid);
ALTER TABLE llx_dolisirh_certificate ADD INDEX idx_dolisirh_certificate_ref (ref);
ALTER TABLE llx_dolisirh_certificate ADD INDEX idx_dolisirh_certificate_status (status);
ALTER TABLE llx_dolisirh_certificate ADD INDEX idx_dolisirh_certificate_fk_element (fk_element);
ALTER TABLE llx_dolisirh_certificate ADD INDEX idx_dolisirh_certificate_fk_product (fk_product);
ALTER TABLE llx_dolisirh_certificate ADD INDEX idx_dolisirh_certificate_fk_societe (fk_societe);
ALTER TABLE llx_dolisirh_certificate ADD INDEX idx_dolisirh_certificate_fk_project (fk_project);
ALTER TABLE llx_dolisirh_certificate ADD UNIQUE INDEX uk_dolisirh_certificate_ref (ref, entity);
ALTER TABLE llx_dolisirh_certificate ADD CONSTRAINT llx_dolisirh_certificate_fk_user_creat FOREIGN KEY (fk_user_creat) REFERENCES llx_user(rowid);
