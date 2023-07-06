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

-- 1.2.0
INSERT INTO llx_c_actioncomm (id, code, type, libelle, module, active, todo, color, picto, position) VALUES (70, 'AC_TEL_IN', 'system', 'Incoming phone call', NULL, 1, NULL, NULL, NULL, 10);
INSERT INTO llx_c_actioncomm (id, code, type, libelle, module, active, todo, color, picto, position) VALUES (71, 'AC_TEL_OUT', 'system', 'Outgoing phone call', NULL, 1, NULL, NULL, NULL, 11);

-- 1.4.0
INSERT INTO `llx_c_timesheet_attendants_role` (`rowid`, `entity`, `ref`, `label`, `description`, `active`, `position`) VALUES(1, 0, 'Responsible', 'Responsible', '', 1, 1);
INSERT INTO `llx_c_timesheet_attendants_role` (`rowid`, `entity`, `ref`, `label`, `description`, `active`, `position`) VALUES(2, 0, 'Signatory', 'Signatory', '', 1, 20);
