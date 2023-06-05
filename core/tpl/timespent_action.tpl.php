<?php

$error = 0;

// Purge criteria
if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) { // All tests are required to be compatible with all browsers
    $action = '';
    $search_usertoprocessid = $user->id;
    $search_task_ref = '';
    $search_task_label = '';
    $search_project_ref = '';
    $search_thirdparty = '';

    $search_category_array = array();

    // We redefine $usertoprocess
    $usertoprocess = $user;
}
if (GETPOST('button_search_x', 'alpha') || GETPOST('button_search.x', 'alpha') || GETPOST('button_search', 'alpha')) {
    $action = '';
}

if (GETPOST('submitdateselect')) {
    if (GETPOST('remonth', 'int') && GETPOST('reday', 'int') && GETPOST('reyear', 'int')) {
        $daytoparse = dol_mktime(0, 0, 0, GETPOST('remonth', 'int'), GETPOST('reday', 'int'), GETPOST('reyear', 'int'));
    }

    $action = '';
}

if ($action == 'addtime' && $user->rights->projet->lire && GETPOST('assigntask') && GETPOST('formfilteraction') != 'listafterchangingselectedfields') {
    $action = 'assigntask';

    if ($taskid > 0) {
        $result = $object->fetch($taskid, $ref);
        if ($result < 0) {
            $error++;
        }
    } else {
        setEventMessages($langs->transnoentitiesnoconv('ErrorFieldRequired', $langs->transnoentitiesnoconv('Task')), '', 'errors');
        $error++;
    }
    if (!GETPOST('type')) {
        setEventMessages($langs->transnoentitiesnoconv('ErrorFieldRequired', $langs->transnoentitiesnoconv('Type')), '', 'errors');
        $error++;
    }

    if (!$error) {
        $idfortaskuser = $usertoprocess->id;
        $result = $object->add_contact($idfortaskuser, GETPOST('type'), 'internal');

        if ($result >= 0 || $result == -2) {	// Contact add ok or already contact of task
            // Test if we are already contact of the project (should be rare but sometimes we can add as task contact without being contact of project, like when admin user has been removed from contact of project)
            $sql = 'SELECT ec.rowid FROM '.MAIN_DB_PREFIX.'element_contact as ec, '.MAIN_DB_PREFIX.'c_type_contact as tc WHERE tc.rowid = ec.fk_c_type_contact';
            $sql .= ' AND ec.fk_socpeople = '.((int) $idfortaskuser). ' AND ec.element_id = ' .((int) $object->fk_project)." AND tc.element = 'project' AND source = 'internal'";
            $resql = $db->query($sql);
            if ($resql) {
                $obj = $db->fetch_object($resql);
                if (!$obj) {	// User is not already linked to project, so we will create link to first type
                    $project = new Project($db);
                    $project->fetch($object->fk_project);
                    // Get type
                    $listofprojcontact = $project->liste_type_contact('internal');

                    if (count($listofprojcontact)) {
                        $typeforprojectcontact = reset(array_keys($listofprojcontact));
                        $result = $project->add_contact($idfortaskuser, $typeforprojectcontact, 'internal');
                    }
                }
            } else {
                dol_print_error($db);
            }
        }
    }

    if ($result < 0) {
        $error++;
        if ($object->error == 'DB_ERROR_RECORD_ALREADY_EXISTS') {
            $langs->load('errors');
            setEventMessages($langs->trans('ErrorTaskAlreadyAssigned'), null, 'warnings');
        } else {
            setEventMessages($object->error, $object->errors, 'errors');
        }
    }

    if (!$error) {
        setEventMessages('TaskAssignedToEnterTime', null);
        $taskid = 0;
    }

    $action = '';
}

if ($action == 'showOnlyFavoriteTasks') {
    $data = json_decode(file_get_contents('php://input'), true);

    $showOnlyFavoriteTasks = $data['showOnlyFavoriteTasks'];

    $tabparam['DOLISIRH_SHOW_ONLY_FAVORITE_TASKS'] = $showOnlyFavoriteTasks;

    dol_set_user_param($db, $conf, $user, $tabparam);
}

if ($action == 'showOnlyTasksWithTimeSpent') {
    $data = json_decode(file_get_contents('php://input'), true);

    $showOnlyTasksWithTimeSpent = $data['showOnlyTasksWithTimeSpent'];

    $tabparam['DOLISIRH_SHOW_ONLY_TASKS_WITH_TIMESPENT'] = $showOnlyTasksWithTimeSpent;

    dol_set_user_param($db, $conf, $user, $tabparam);
}

if ($action == 'addTimeSpent' && $permissiontoadd) {
    $data = json_decode(file_get_contents('php://input'), true);

    $taskID    = $data['taskID'];
    $timestamp = $data['timestamp'];
    $datehour  = $data['datehour'];
    $datemin   = $data['datemin'];
    $comment   = $data['comment'];
    $hour      = (int) $data['hour'];
    $min       = (int) $data['min'];

    $object->fetch($taskID);

    $object->timespent_date     = $timestamp;
    $object->timespent_datehour = $timestamp + ($datehour * 3600) + ($datemin * 60);
    if ($datehour > 0 || $datemin > 0) {
        $object->timespent_withhour = 1;
    }
    $object->timespent_note     = $comment;
    $object->timespent_duration = ($hour * 3600) + ($min * 60);
    $object->timespent_fk_user  = $user->id;

    if ($object->timespent_duration > 0) {
        $object->addTimeSpent($user);
    }
}
