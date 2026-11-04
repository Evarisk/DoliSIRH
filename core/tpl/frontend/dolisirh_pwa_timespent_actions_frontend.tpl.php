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
if ($action == 'uploadPhoto' && !empty($conf->global->MAIN_UPLOAD_DOC)) {
	$subDirParam = GETPOST('sub_dir', 'alpha');
	$uploadDir   = $conf->project->multidir_output[$conf->entity ?? 1];
	if (!empty($subDirParam)) {
		$uploadDir .= '/' . $subDirParam;
	}

	if (!dol_is_dir($uploadDir)) {
		dol_mkdir($uploadDir);
	}

	if (!empty($_FILES['userfile']['tmp_name'])) {
		$tmpNames = is_array($_FILES['userfile']['tmp_name']) ? $_FILES['userfile']['tmp_name'] : [$_FILES['userfile']['tmp_name']];
		$names    = is_array($_FILES['userfile']['name']) ? $_FILES['userfile']['name'] : [$_FILES['userfile']['name']];

		// An edited photo comes back under its original name to replace the unedited file
		$allowOverwrite = GETPOSTINT('overwrite') ? 1 : 0;
		$res            = dol_add_file_process($uploadDir, $allowOverwrite, 1, 'userfile', '', null, '', 0);
		if ($res > 0) {
			$mediaSizes = ['mini', 'small', 'medium', 'large'];
			foreach ($names as $idx => $name) {
				if (empty($tmpNames[$idx])) {
					continue;
				}
				$destFull = $uploadDir . '/' . dol_sanitizeFileName($name);
				if (dol_is_file($destFull) && image_format_supported($destFull) >= 0) {
					vignette($destFull, 160, 120, '_mini');
					vignette($destFull, 480, 270, '_small');
					foreach ($mediaSizes as $size) {
						$confW = getDolGlobalInt('SATURNE_MEDIA_MAX_WIDTH_' . dol_strtoupper($size));
						$confH = getDolGlobalInt('SATURNE_MEDIA_MAX_HEIGHT_' . dol_strtoupper($size));
						if ($confW > 0 && $confH > 0 && function_exists('saturne_vignette')) {
							saturne_vignette($destFull, $confW, $confH, '_' . $size);
						}
					}
				}
			}
		}
	}
}

if ($action == 'unlinkFile') {
	$data     = json_decode(file_get_contents('php://input'), true);
	$filePath = !empty($data['filepath']) ? $data['filepath'] : GETPOST('filepath', 'alpha');
	$fileName = !empty($data['filename']) ? $data['filename'] : GETPOST('filename', 'alpha');
	$fullPath = $filePath . '/' . $fileName;

	if (!empty($fileName) && dol_is_file($fullPath)) {
		dol_delete_file($fullPath);
		$thumbsDir = $filePath . '/thumbs';
		if (dol_is_dir($thumbsDir)) {
			$pathInfo = pathinfo($fileName);
			$base     = $pathInfo['filename'];
			$ext      = isset($pathInfo['extension']) ? '.' . $pathInfo['extension'] : '';
			foreach (['_mini', '_small', '_medium', '_large'] as $suffix) {
				$thumbFile = $thumbsDir . '/' . $base . $suffix . $ext;
				if (dol_is_file($thumbFile)) {
					dol_delete_file($thumbFile);
				}
			}
		}
	}
}

if ($action == 'add_audio' && !empty($_FILES['audio']['tmp_name'])) {
	$audioModule    = GETPOST('module_name', 'alpha');
	$audioSubDir    = GETPOST('sub_dir', 'alpha');
	$audioModLower  = !empty($audioModule) ? dol_strtolower($audioModule) : 'project';
	$audioSubDirRes = !empty($audioSubDir) ? $audioSubDir : $subDir;

	$uploadDir  = !empty($conf->$audioModLower->multidir_output[$conf->entity ?? 1])
		? $conf->$audioModLower->multidir_output[$conf->entity ?? 1]
		: $conf->ecm->dir_output . '/' . $audioModLower;
	$uploadDir .= '/' . $audioSubDirRes;

	if (!dol_is_dir($uploadDir)) {
		dol_mkdir($uploadDir);
	}

	$fileName = dol_print_date(dol_now(), 'dayhourlog') . '_audio.wav';
	$destFile = $uploadDir . '/' . $fileName;

	move_uploaded_file($_FILES['audio']['tmp_name'], $destFile);
}

if ($action == 'delete_audio') {
	$audioModule    = GETPOST('module_name', 'alpha');
	$audioSubDir    = GETPOST('sub_dir', 'alpha');
	$audioFilename  = GETPOST('filename', 'alpha');
	$audioModLower  = !empty($audioModule) ? dol_strtolower($audioModule) : 'project';
	$audioSubDirRes = !empty($audioSubDir) ? $audioSubDir : $subDir;

	$uploadDir  = !empty($conf->$audioModLower->multidir_output[$conf->entity ?? 1])
		? $conf->$audioModLower->multidir_output[$conf->entity ?? 1]
		: $conf->ecm->dir_output . '/' . $audioModLower;
	$uploadDir .= '/' . $audioSubDirRes;

	$filePath = $uploadDir . '/' . dol_basename($audioFilename);
	if (!empty($audioFilename) && dol_is_file($filePath)) {
		dol_delete_file($filePath);
	}
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
	$note          = trim((string) ($payload['note'] ?? GETPOST('note', 'restricthtml')));
	$isProjectMode = !empty($payload['is_project_mode']);
	$calMonth      = (string) ($payload['cal_month'] ?? GETPOST('cal_month', 'alphanohtml'));

	$durationSec = dolisirh_pwa_parse_hm_to_seconds($durationHm);
	if ($taskId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr) || $durationSec <= 0) {
		print json_encode(['success' => false, 'error' => $langs->transnoentities('ErrorFieldRequired')]);
		exit;
	}

	if ($isProjectMode && $note === '') {
		print json_encode(['success' => false, 'error' => $langs->transnoentities('ErrorFieldRequired', $langs->transnoentities('Note'))]);
		exit;
	}

	$task = new Task($db);
	if ($task->fetch($taskId) <= 0) {
		print json_encode(['success' => false, 'error' => $langs->transnoentities('ErrorRecordNotFound')]);
		exit;
	}

	$project = new Project($db);
	$project->fetch((int) $task->fk_project);

	dolisirh_pwa_ensure_contributor($db, $user, (int) $task->fk_project, (int) $task->id);

	$dateParts = explode('-', $dateStr);
	$dateTs    = dol_mktime(12, 0, 0, (int) $dateParts[1], (int) $dateParts[2], (int) $dateParts[0], 'tzserver');

	$task->timespent_date     = $dateTs;
	$task->timespent_datehour = $dateTs;
	$task->timespent_withhour = 1;
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
	if ($isProjectMode && $project->id > 0) {
		$savedMedias    = dolisirh_pwa_save_uploaded_medias_to_project($conf, $user, $uploadTokenContext, $subDir, $project, $task);
		$newSubDir      = 'tmp/' . saturne_get_upload_token($uploadTokenContext);
		$mediaBlockHtml = dolisirh_pwa_media_block($newSubDir);
	}

	$state        = dolisirh_pwa_get_state($db, $langs, $user, $dateStr, $calMonth);
	$projectTasks = $isProjectMode ? dolisirh_pwa_get_project_tasks($db, (int) $project->id) : [];

	print json_encode([
		'success'          => true,
		'timespent_id'     => (int) $resAdd,
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

	$state        = dolisirh_pwa_get_state($db, $langs, $user, $selectedDate, $calMonth);
	$projectTasks = $projectId > 0 ? dolisirh_pwa_get_project_tasks($db, $projectId) : [];

	print json_encode([
		'success'       => true,
		'state'         => $state,
		'project_tasks' => $projectTasks,
	]);
	exit;
}
