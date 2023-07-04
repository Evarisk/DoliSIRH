INSERT INTO llx_c_actioncomm (id, code, type, libelle, module, active, todo, color, picto, position) VALUES (70, 'AC_TEL_IN', 'system', 'Incoming phone call', NULL, 1, NULL, NULL, NULL, 10);
INSERT INTO llx_c_actioncomm (id, code, type, libelle, module, active, todo, color, picto, position) VALUES (71, 'AC_TEL_OUT', 'system', 'Outgoing phone call', NULL, 1, NULL, NULL, NULL, 11);

INSERT INTO `llx_c_timesheet_attendants_role` (`rowid`, `entity`, `ref`, `label`, `description`, `active`, `position`) VALUES(1, 0, 'Responsible', 'Responsible', '', 1, 1);
INSERT INTO `llx_c_timesheet_attendants_role` (`rowid`, `entity`, `ref`, `label`, `description`, `active`, `position`) VALUES(2, 0, 'Signatory', 'Signatory', '', 1, 20);
