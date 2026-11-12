<?php
/* Copyright (C) 2026 EVARISK <technique@evarisk.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    core/tpl/frontend/dolisirh_pwa_timespent_actions_frontend.tpl.php
 * \ingroup dolisirh
 * \brief   Actions handler for the DoliSIRH Time Tracking PWA page
 */

/** @var Conf $conf */
/** @var DoliDB $db */
/** @var Translate $langs */
/** @var User $user */
/** @var string $action */
/** @var string $subDir */
/** @var string $uploadTokenContext */

// 1. Saturne media block actions (photo upload, photo delete, audio upload, audio delete)
// The medias wait in the temporary folder of the session until the time entry is saved. The folder
// comes from the session and the posted one must be the same: no other folder can be written to.
$tempDir     = $conf->dolisirh->dir_output . '/' . $subDir;
$isOwnFolder = GETPOST('sub_dir', 'alpha') === $subDir && GETPOST('module_name', 'aZ09') === 'dolisirh';

if (in_array($action, ['uploadPhoto', 'linkPhoto', 'deletePhoto', 'add_audio', 'delete_audio'], true) && $isOwnFolder && !dol_is_dir($tempDir)) {
	dol_mkdir($tempDir);
}

// Photos picked in the DoliSIRH media library (Saturne gallery): copied, the library keeps its own
if ($action == 'linkPhoto' && $isOwnFolder) {
	dolisirh_pwa_link_library_medias($conf, $tempDir, GETPOST('filenames', 'array'));
}

if ($action == 'uploadPhoto' && $isOwnFolder && !empty($_FILES['userfile']['tmp_name'])) {
	$tmpNames = (array) $_FILES['userfile']['tmp_name'];
	$names    = (array) $_FILES['userfile']['name'];
	$errors   = (array) $_FILES['userfile']['error'];

	// An edited photo comes back under its original name to replace the unedited file
	$allowOverwrite = GETPOSTINT('overwrite') ? 1 : 0;
	foreach ($tmpNames as $index => $tmpName) {
		$fileName = dol_sanitizeFileName((string) $names[$index]);
		if (empty($tmpName) || $fileName === '' || image_format_supported($fileName) < 0) {
			continue;
		}
		dol_move_uploaded_file($tmpName, $tempDir . '/' . $fileName, $allowOverwrite, 0, (int) $errors[$index], 0, 'userfile', $tempDir);
	}
}

if (in_array($action, ['deletePhoto', 'delete_audio'], true) && $isOwnFolder) {
	$fileName = dol_sanitizeFileName(basename(GETPOST('filename', 'alpha')));
	if ($fileName !== '' && dol_is_file($tempDir . '/' . $fileName)) {
		dol_delete_file($tempDir . '/' . $fileName, 0, 0, 0, null, false, 0);
	}
}

if ($action == 'add_audio' && $isOwnFolder && !empty($_FILES['audio']['tmp_name'])) {
	// The Saturne media block lists the recordings of its folder by their .wav name: the real format
	// is given back to the file once the time entry is saved
	$fileName = dol_print_date(dol_now(), 'dayhourlog') . '_audio.wav';
	dol_move_uploaded_file($_FILES['audio']['tmp_name'], $tempDir . '/' . $fileName, 0, 0, (int) $_FILES['audio']['error'], 0, 'audio', $tempDir);
}

// 2. AJAX JSON endpoints for the PWA
if ($action == 'pwa_get_state') {
	top_httphead('application/json');
	$selectedDate = GETPOST('selected_date', 'alphanohtml');
	$calMonth     = GETPOST('cal_month', 'alphanohtml');
	$state        = dolisirh_pwa_get_state($db, $langs, $user, $selectedDate, $calMonth);
	print json_encode(['success' => true, 'state' => $state]);
	exit;
}

if ($action == 'pwa_get_tasks') {
	top_httphead('application/json');
	$projectId = GETPOSTINT('project_id');
	$tasks     = dolisirh_pwa_get_project_tasks($db, $projectId);
	print json_encode(['success' => true, 'tasks' => $tasks]);
	exit;
}

if ($action == 'pwa_create_default_task') {
	top_httphead('application/json');
	if (!$user->hasRight('projet', 'creer') && !$user->hasRight('projet', 'lire') && !$user->hasRight('dolisirh', 'lire')) {
		print json_encode(['success' => false, 'error' => $langs->transnoentities('NotEnoughPermissions')]);
		exit;
	}
	$projectId = GETPOSTINT('project_id');
	$res       = dolisirh_pwa_create_default_task($db, $user, $projectId);
	if (empty($res) || empty($res['task'])) {
		print json_encode(['success' => false, 'error' => $langs->transnoentities('Error')]);
		exit;
	}
	print json_encode([
		'success' => true,
		'created' => $res['created'],
		'task'    => $res['task'],
		'tasks'   => $res['tasks'],
	]);
	exit;
}

if ($action == 'pwa_add_timespent') {
	top_httphead('application/json');
	if (!$user->hasRight('projet', 'lire') && !$user->hasRight('dolisirh', 'lire')) {
		print json_encode(['success' => false, 'error' => $langs->transnoentities('NotEnoughPermissions')]);
		exit;
	}

	$payload       = json_decode(file_get_contents('php://input'), true);
	$taskId        = (int) ($payload['task_id'] ?? GETPOSTINT('task_id'));
	$dateStr       = (string) ($payload['date'] ?? GETPOST('date', 'alphanohtml'));
	$durationHm    = (string) ($payload['duration_hm'] ?? GETPOST('duration_hm', 'alphanohtml'));
	$startHm       = (string) ($payload['start_hm'] ?? '');
	$endHm         = (string) ($payload['end_hm'] ?? '');
	$note          = trim(dol_string_nohtmltag((string) ($payload['note'] ?? GETPOST('note', 'restricthtml')), 0, 'UTF-8', 0, 0));
	$isProjectMode = !empty($payload['is_project_mode']);
	$calMonth      = (string) ($payload['cal_month'] ?? GETPOST('cal_month', 'alphanohtml'));

	// A start and an end give the duration and keep the hour, otherwise only the duration is known
	$startMin = dolisirh_pwa_parse_clock_time($startHm);
	$endMin   = dolisirh_pwa_parse_clock_time($endHm);
	$withHour = $startMin >= 0 && $endMin >= 0;
	if ($withHour) {
		// An end before the start is a shift going past midnight
		$durationSec = (($endMin - $startMin + 1440) % 1440) * 60;
	} else {
		$durationSec = dolisirh_pwa_parse_hm_to_seconds($durationHm);
	}

	if ($taskId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr) || $durationSec <= 0 || $durationSec > 86400) {
		print json_encode(['success' => false, 'error' => $langs->transnoentities($withHour ? 'ErrorPwaEndEqualsStart' : 'ErrorFieldRequired', $langs->transnoentities('Duration'))]);
		exit;
	}

	$task = new Task($db);
	if ($task->fetch($taskId) <= 0) {
		print json_encode(['success' => false, 'error' => $langs->transnoentities('ErrorRecordNotFound')]);
		exit;
	}

	// A closed project takes no more time; the HR project may stay a draft
	$project = new Project($db);
	if ($project->fetch((int) $task->fk_project) <= 0 || (int) $project->status === Project::STATUS_CLOSED || !in_array((int) $project->entity, explode(',', getEntity('project')))) {
		print json_encode(['success' => false, 'error' => $langs->transnoentities('NotEnoughPermissions')]);
		exit;
	}

	// Time on a project says what was done: a note, a photo or a voice note. HR time needs none.
	$isHrTask  = getDolGlobalInt('DOLISIRH_HR_PROJECT') > 0 && (int) $project->id === getDolGlobalInt('DOLISIRH_HR_PROJECT');
	$hasMedias = $isProjectMode && dol_is_dir($tempDir) && count(dol_dir_list($tempDir, 'files', 0, '', '(\.meta|_preview.*\.png)$')) > 0;
	if ($isProjectMode && !$isHrTask && $note === '' && !$hasMedias) {
		print json_encode(['success' => false, 'error' => $langs->transnoentities('ErrorPwaNoteOrMediaRequired')]);
		exit;
	}

	dolisirh_pwa_ensure_contributor($db, $user, (int) $task->fk_project, (int) $task->id);

	$dateParts = explode('-', $dateStr);
	$dateTs    = dol_mktime(12, 0, 0, (int) $dateParts[1], (int) $dateParts[2], (int) $dateParts[0], 'tzserver');

	$task->timespent_date     = $dateTs;
	$task->timespent_datehour = $withHour ? dol_mktime(intdiv($startMin, 60), $startMin % 60, 0, (int) $dateParts[1], (int) $dateParts[2], (int) $dateParts[0], 'tzuserrel') : $dateTs;
	$task->timespent_withhour = $withHour ? 1 : 0;
	$task->timespent_duration = $durationSec;
	$task->timespent_note     = $note;
	$task->timespent_fk_user  = $user->id;

	$resAdd = $task->addTimeSpent($user);
	if ($resAdd <= 0) {
		print json_encode(['success' => false, 'error' => $task->error ?: $langs->transnoentities('Error')]);
		exit;
	}

	$savedMedias    = [];
	$mediaBlockHtml = '';
	if ($isProjectMode) {
		$savedMedias = dolisirh_pwa_attach_medias($conf, $subDir, $project, $task, (int) $resAdd);
		saturne_invalidate_upload_token($uploadTokenContext, 'dolisirh', 'pwa');
		$mediaBlockHtml = dolisirh_pwa_media_block(dolisirh_pwa_temp_sub_dir(saturne_get_upload_token($uploadTokenContext)));
	}

	$state        = dolisirh_pwa_get_state($db, $langs, $user, $dateStr, $calMonth);
	$projectTasks = $isProjectMode ? dolisirh_pwa_get_project_tasks($db, (int) $project->id) : [];

	print json_encode([
		'success'          => true,
		'timespent_id'     => (int) $resAdd,
		'duration_hm'      => dolisirh_pwa_format_hm($durationSec),
		'saved_medias'     => $savedMedias,
		'media_block_html' => $mediaBlockHtml,
		'state'            => $state,
		'project_tasks'    => $projectTasks,
	]);
	exit;
}

if ($action == 'pwa_delete_timespent') {
	top_httphead('application/json');
	$payload      = json_decode(file_get_contents('php://input'), true);
	$timeSpentId  = (int) ($payload['timespent_id'] ?? GETPOSTINT('timespent_id'));
	$selectedDate = (string) ($payload['selected_date'] ?? GETPOST('selected_date', 'alphanohtml'));
	$calMonth     = (string) ($payload['cal_month'] ?? GETPOST('cal_month', 'alphanohtml'));
	$projectId    = (int) ($payload['project_id'] ?? GETPOSTINT('project_id'));

	if ($timeSpentId <= 0) {
		print json_encode(['success' => false, 'error' => $langs->transnoentities('ErrorRecordNotFound')]);
		exit;
	}

	// Verify ownership or admin right
	$sqlCheck  = 'SELECT rowid, fk_element, fk_user FROM ' . MAIN_DB_PREFIX . 'element_time';
	$sqlCheck .= ' WHERE rowid = ' . (int) $timeSpentId . " AND elementtype = 'task'";
	$resCheck  = $db->query($sqlCheck);
	$objCheck  = $resCheck ? $db->fetch_object($resCheck) : null;

	if (!$objCheck || ((int) $objCheck->fk_user !== (int) $user->id && empty($user->admin))) {
		print json_encode(['success' => false, 'error' => $langs->transnoentities('NotEnoughPermissions')]);
		exit;
	}

	$task = new Task($db);
	$task->fetch((int) $objCheck->fk_element);
	if ($task->fetchTimeSpent($timeSpentId) <= 0 || $task->delTimeSpent($user) <= 0) {
		print json_encode(['success' => false, 'error' => $task->error ?: $langs->transnoentities('Error')]);
		exit;
	}

	// The photos and voice notes of the entry go with it
	$project = new Project($db);
	if ($project->fetch((int) $task->fk_project) > 0) {
		dolisirh_pwa_delete_entry_medias($conf, (string) $project->ref, (string) $task->ref, $timeSpentId);
	}

	$state        = dolisirh_pwa_get_state($db, $langs, $user, $selectedDate, $calMonth);
	$projectTasks = $projectId > 0 ? dolisirh_pwa_get_project_tasks($db, $projectId) : [];

	print json_encode([
		'success'       => true,
		'state'         => $state,
		'project_tasks' => $projectTasks,
	]);
	exit;
}
