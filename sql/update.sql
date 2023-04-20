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
ALTER TABLE llx_dolisirh_object_signature ADD module_name VARCHAR(255) NULL AFTER element_type;
UPDATE llx_dolisirh_object_signature SET module_name = 'dolisirh';
INSERT INTO llx_saturne_object_signature (entity, date_creation, tms, import_key, status, role, firstname, lastname, email, phone, society_name, signature_date, signature_location, signature_comment, element_id, element_type, module_name, signature, stamp, last_email_sent_date, signature_url, transaction_url, object_type, fk_object)
SELECT entity, date_creation, tms, import_key, status, role, firstname, lastname, email, phone, society_name, signature_date, signature_location, signature_comment, element_id, element_type, module_name, signature, stamp, last_email_sent_date, signature_url, transaction_url, object_type, fk_object FROM llx_dolisirh_object_signature;
DROP TABLE llx_dolisirh_object_signature