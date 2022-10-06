UPDATE llx_extrafields SET list = "preg_match('/public/',$_SERVER['PHP_SELF'])?0:1" WHERE name = 'fk_task' AND elementtype = 'ticket' AND type = 'select';
