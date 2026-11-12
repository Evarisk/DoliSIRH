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
 * \file    lib/dolisirh_pwa.lib.php
 * \ingroup dolisirh
 * \brief   Library of helper functions for the DoliSIRH Time Tracking PWA
 */

require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/task.class.php';

dol_include_once('/saturne/lib/medias.lib.php');

require_once __DIR__ . '/../class/workinghours.class.php';
require_once __DIR__ . '/dolisirh_function.lib.php';
require_once __DIR__ . '/dolisirh_timespent.lib.php';
require_once __DIR__ . '/dolisirh_timesheet.lib.php';

/**
 * Prefix given to a media stored in the task folder, so the time entry it was taken for is found again.
 */
const DOLISIRH_PWA_MEDIA_PREFIX = 'ts';

/**
 * Sub-directory of the DoliSIRH documents holding the medias picked before the time entry exists.
 *
 * @param  string $token Saturne upload token of the user session
 * @return string        Sub-directory, relative to the DoliSIRH documents
 */
function dolisirh_pwa_temp_sub_dir(string $token): string
{
	return 'pwa/' . $token;
}

/**
 * Remove the temporary media folders left behind by sessions that never saved their time entry.
 *
 * @param  Conf   $conf      Dolibarr configuration
 * @param  string $keepToken Upload token of the current session, whose folder is kept
 * @return void
 */
function dolisirh_pwa_purge_temp_dirs(Conf $conf, string $keepToken): void
{
	if (empty($conf->dolisirh->dir_output)) {
		return;
	}

	$tempRoot = $conf->dolisirh->dir_output . '/pwa';
	if (!dol_is_dir($tempRoot)) {
		return;
	}

	$limit = dol_now() - 2 * 86400;
	// dol_dir_list() only reads the dates when asked to sort on them: without it every folder looks old
	foreach (dol_dir_list($tempRoot, 'directories', 0, '', null, 'date') as $dir) {
		if ((int) $dir['date'] < $limit && $dir['name'] !== $keepToken && preg_match('/^[a-f0-9]{64}$/', $dir['name'])) {
			dol_delete_dir_recursive($dir['fullname']);
		}
	}
}

/**
 * Render the Saturne media upload block for the Pointer tab of the DoliSIRH PWA.
 * The picking button opens the DoliSIRH media library, the camera button takes a new photo.
 *
 * @param  string $subDir Upload subfolder under the DoliSIRH documents
 * @return string         HTML of the media upload block
 */
function dolisirh_pwa_media_block(string $subDir): string
{
	return saturne_render_media_block('dolisirh', $subDir, 'pwa-timespent', '', ['show_photo' => true, 'show_audio' => true, 'show_library' => true, 'show_ai' => false]);
}

/**
 * Folder of the DoliSIRH media library, browsed by the Saturne gallery.
 *
 * @param  Conf   $conf Dolibarr configuration
 * @return string       Full path of the folder
 */
function dolisirh_pwa_library_dir(Conf $conf): string
{
	// Same folder as saturne_add_media_to_library(), the ECM module being off or not
	$ecmDir = $conf->ecm->multidir_output[$conf->entity] ?? saturne_default_documents_dir('ecm', (int) $conf->entity);

	return $ecmDir . '/dolisirh/medias';
}

/**
 * Whether a photo picked in the PWA comes from the media library: same name and same weight.
 *
 * @param  Conf   $conf     Dolibarr configuration
 * @param  string $filePath Full path of the photo
 * @return bool
 */
function dolisirh_pwa_is_library_media(Conf $conf, string $filePath): bool
{
	$libraryFile = dolisirh_pwa_library_dir($conf) . '/' . basename($filePath);

	return dol_is_file($libraryFile) && dol_filesize($libraryFile) == dol_filesize($filePath);
}

/**
 * Parse a HH:MM clock time into minutes since midnight.
 *
 * @param  string $hm Clock time in HH:MM format
 * @return int        Minutes since midnight, -1 when the value is not a clock time
 */
function dolisirh_pwa_parse_clock_time(string $hm): int
{
	if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', trim($hm), $matches)) {
		return -1;
	}

	return ((int) $matches[1]) * 60 + (int) $matches[2];
}

/**
 * Extension matching the real content of a recorded voice note.
 * The Saturne recorder names every recording .wav whereas browsers record WebM or MP4.
 *
 * @param  string $filePath Full path of the recording
 * @return string           Extension without the dot
 */
function dolisirh_pwa_audio_extension(string $filePath): string
{
	$mime = '';
	if (function_exists('finfo_open')) {
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		if ($finfo) {
			$mime = (string) finfo_file($finfo, $filePath);
			finfo_close($finfo);
		}
	}

	$extensions = [
		'video/webm' => 'webm',
		'audio/webm' => 'webm',
		'audio/ogg'  => 'ogg',
		'video/ogg'  => 'ogg',
		'video/mp4'  => 'm4a',
		'audio/mp4'  => 'm4a',
		'audio/x-m4a' => 'm4a',
		'audio/mpeg' => 'mp3',
	];

	return $extensions[$mime] ?? (string) pathinfo($filePath, PATHINFO_EXTENSION);
}

/**
 * Documents folder of a task, where the medias of its time entries are stored.
 *
 * @param  Conf   $conf       Dolibarr configuration
 * @param  string $projectRef Project reference
 * @param  string $taskRef    Task reference
 * @return string             Full path of the folder
 */
function dolisirh_pwa_task_dir(Conf $conf, string $projectRef, string $taskRef): string
{
	return $conf->project->multidir_output[$conf->entity] . '/' . dol_sanitizeFileName($projectRef) . '/' . dol_sanitizeFileName($taskRef);
}

/**
 * Move the medias picked in the PWA to the documents of the task, named after the time entry.
 *
 * @param  Conf    $conf        Dolibarr configuration
 * @param  string  $subDir      Temporary upload sub-directory of the DoliSIRH documents
 * @param  Project $project     Project of the task
 * @param  Task    $task        Task the time was spent on
 * @param  int     $timeSpentId Time entry ID
 * @return string[]             Names of the stored files
 */
function dolisirh_pwa_attach_medias(Conf $conf, string $subDir, Project $project, Task $task, int $timeSpentId): array
{
	$tempDir = $conf->dolisirh->dir_output . '/' . $subDir;
	if (!dol_is_dir($tempDir)) {
		return [];
	}

	$taskDir = dolisirh_pwa_task_dir($conf, (string) $project->ref, (string) $task->ref);
	if (!dol_is_dir($taskDir)) {
		dol_mkdir($taskDir);
	}

	$storedFiles = [];
	foreach (dol_dir_list($tempDir, 'files', 0, '', '(\.meta|_preview.*\.png)$', 'date', SORT_ASC) as $file) {
		$isImage = image_format_supported($file['name']) >= 0;
		$isAudio = preg_match('/\.(wav|mp3|ogg|m4a|webm)$/i', $file['name']);
		if (!$isImage && !$isAudio) {
			continue;
		}

		$fileName = dol_sanitizeFileName($file['name']);
		if ($isAudio) {
			$fileName = pathinfo($fileName, PATHINFO_FILENAME) . '.' . dolisirh_pwa_audio_extension($file['fullname']);
		}
		$fileName = DOLISIRH_PWA_MEDIA_PREFIX . $timeSpentId . '_' . $fileName;
		$destFile = $taskDir . '/' . $fileName;

		$fromLibrary = $isImage && dolisirh_pwa_is_library_media($conf, $file['fullname']);

		$moreInfo = ['gen_or_uploaded' => 'uploaded', 'src_object_type' => $task->table_element, 'src_object_id' => $task->id];
		if (!dol_move($file['fullname'], $destFile, '0', 1, 0, 1, $moreInfo)) {
			continue;
		}

		if ($isImage) {
			vignette($destFile, 160, 120, '_mini');
			vignette($destFile, 480, 270, '_small');

			// A photo taken on the spot joins the DoliSIRH media library, as Saturne media blocks do;
			// one picked from the library is already there
			if (!$fromLibrary && function_exists('saturne_add_media_to_library')) {
				saturne_add_media_to_library('dolisirh', $destFile);
			}
		}
		$storedFiles[] = $fileName;
	}

	return $storedFiles;
}

/**
 * Copy medias of the DoliSIRH media library into the temporary folder of the PWA.
 *
 * @param  Conf     $conf      Dolibarr configuration
 * @param  string   $tempDir   Full path of the temporary folder of the session
 * @param  string[] $fileNames Names of the library medias
 * @return int                 Number of medias copied
 */
function dolisirh_pwa_link_library_medias(Conf $conf, string $tempDir, array $fileNames): int
{
	$libraryDir = dolisirh_pwa_library_dir($conf);
	$copied     = 0;
	foreach ($fileNames as $fileName) {
		$fileName = dol_sanitizeFileName(basename((string) $fileName));
		if ($fileName === '' || image_format_supported($fileName) < 0 || !dol_is_file($libraryDir . '/' . $fileName)) {
			continue;
		}

		if (!dol_is_dir($tempDir)) {
			dol_mkdir($tempDir);
		}
		if (dol_copy($libraryDir . '/' . $fileName, $tempDir . '/' . $fileName, '0', 1) > 0) {
			$copied++;
		}
	}

	return $copied;
}

/**
 * Delete the medias of a time entry, with their thumbnails, from the documents of its task.
 *
 * @param  Conf   $conf        Dolibarr configuration
 * @param  string $projectRef  Project reference
 * @param  string $taskRef     Task reference
 * @param  int    $timeSpentId Time entry ID
 * @return void
 */
function dolisirh_pwa_delete_entry_medias(Conf $conf, string $projectRef, string $taskRef, int $timeSpentId): void
{
	$taskDir = dolisirh_pwa_task_dir($conf, $projectRef, $taskRef);
	if ($timeSpentId <= 0 || !dol_is_dir($taskDir)) {
		return;
	}

	foreach (dol_dir_list($taskDir, 'files', 0, '^' . DOLISIRH_PWA_MEDIA_PREFIX . $timeSpentId . '_') as $file) {
		if (image_format_supported($file['name']) >= 0) {
			foreach (['_mini', '_small'] as $size) {
				$thumbFile = $taskDir . '/' . getImageFileNameForSize($file['name'], $size);
				if (dol_is_file($thumbFile)) {
					dol_delete_file($thumbFile, 0, 0, 0, null, false, 0);
				}
			}
		}
		dol_delete_file($file['fullname']);
	}
}

/**
 * Medias attached to time entries, read from the documents of their tasks.
 *
 * @param  Conf  $conf    Dolibarr configuration
 * @param  array $entries Time entries (id, project_ref, task_ref)
 * @return array          Map of time entry ID => list of medias (type, name, url, thumb)
 */
function dolisirh_pwa_get_entries_medias(Conf $conf, array $entries): array
{
	$entriesByDir = [];
	foreach ($entries as $entry) {
		$key                          = $entry['project_ref'] . '/' . $entry['task_ref'];
		$entriesByDir[$key][$entry['id']] = true;
	}

	$medias = [];
	foreach ($entriesByDir as $relativeDir => $entryIds) {
		[$projectRef, $taskRef] = explode('/', $relativeDir, 2);
		$taskDir = dolisirh_pwa_task_dir($conf, $projectRef, $taskRef);
		if (!dol_is_dir($taskDir)) {
			continue;
		}

		$urlDir = dol_sanitizeFileName($projectRef) . '/' . dol_sanitizeFileName($taskRef);
		$files  = dol_dir_list($taskDir, 'files', 0, '^' . DOLISIRH_PWA_MEDIA_PREFIX . '[0-9]+_', '(\.meta|_preview.*\.png)$', 'name', SORT_ASC);
		foreach ($files as $file) {
			if (!preg_match('/^' . DOLISIRH_PWA_MEDIA_PREFIX . '(\d+)_/', $file['name'], $matches) || empty($entryIds[(int) $matches[1]])) {
				continue;
			}

			$url = DOL_URL_ROOT . '/document.php?modulepart=project_task&entity=' . ((int) $conf->entity) . '&file=' . urlencode($urlDir . '/' . $file['name']);
			if (image_format_supported($file['name']) >= 0) {
				$thumbName = getImageFileNameForSize($file['name'], '_mini');
				$thumbUrl  = dol_is_file($taskDir . '/' . $thumbName) ? DOL_URL_ROOT . '/document.php?modulepart=project_task&entity=' . ((int) $conf->entity) . '&file=' . urlencode($urlDir . '/' . $thumbName) : $url;
				$medias[(int) $matches[1]][] = ['type' => 'image', 'name' => $file['name'], 'url' => $url . '&attachment=0', 'thumb' => $thumbUrl];
			} elseif (preg_match('/\.(wav|mp3|ogg|m4a|webm)$/i', $file['name'])) {
				$medias[(int) $matches[1]][] = ['type' => 'audio', 'name' => $file['name'], 'url' => $url . '&attachment=0', 'thumb' => ''];
			}
		}
	}

	return $medias;
}

/**
 * Tasks the user spent time on lately, for one-tap selection in the Pointer tab.
 *
 * @param  DoliDB $db          Database handler
 * @param  int    $userId      User ID
 * @param  int    $hrProjectId HR project ID
 * @param  int    $limit       Maximum number of tasks
 * @return array               List of tasks with their project
 */
function dolisirh_pwa_get_recent_tasks(DoliDB $db, int $userId, int $hrProjectId, int $limit = 6): array
{
	$sql  = 'SELECT pt.rowid, pt.ref, pt.label, p.rowid as project_id, p.ref as project_ref, p.title as project_title, MAX(et.rowid) as last_id';
	$sql .= ' FROM ' . MAIN_DB_PREFIX . 'element_time as et';
	$sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'projet_task as pt ON pt.rowid = et.fk_element';
	$sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'projet as p ON p.rowid = pt.fk_projet';
	$sql .= " WHERE et.elementtype = 'task'";
	$sql .= ' AND et.fk_user = ' . $userId;
	$sql .= ' AND p.entity IN (' . getEntity('project') . ')';
	$sql .= ' AND p.fk_statut = 1';
	$sql .= ' AND (pt.fk_statut IS NULL OR pt.fk_statut < 3)';
	$sql .= ' GROUP BY pt.rowid, pt.ref, pt.label, p.rowid, p.ref, p.title';
	$sql .= ' ORDER BY last_id DESC';
	$sql .= $db->plimit($limit);

	$tasks = [];
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$tasks[] = [
				'id'            => (int) $obj->rowid,
				'ref'           => (string) $obj->ref,
				'label'         => (string) $obj->label,
				'project_id'    => (int) $obj->project_id,
				'project_ref'   => (string) $obj->project_ref,
				'project_title' => (string) $obj->project_title,
				'project_url'   => DOL_URL_ROOT . '/projet/card.php?id=' . (int) $obj->project_id,
				'is_hr'         => $hrProjectId > 0 && (int) $obj->project_id === $hrProjectId,
			];
		}
		$db->free($resql);
	}

	return $tasks;
}

/**
 * Format seconds as HH:MM (e.g. 25200 -> 07:00).
 *
 * @param  int    $seconds Duration in seconds
 * @return string          Formatted HH:MM string
 */
function dolisirh_pwa_format_hm(int $seconds): string
{
	$sec  = max(0, $seconds);
	$mins = (int) round($sec / 60);
	$h    = (int) floor($mins / 60);
	$m    = $mins % 60;

	return sprintf('%02d:%02d', $h, $m);
}

/**
 * Parse a HH:MM string into seconds.
 *
 * @param  string $hm Duration string in HH:MM format
 * @return int        Duration in seconds
 */
function dolisirh_pwa_parse_hm_to_seconds(string $hm): int
{
	$parts = explode(':', trim($hm));
	if (count($parts) < 2) {
		return 0;
	}
	$h = max(0, (int) $parts[0]);
	$m = max(0, (int) $parts[1]);

	return ($h * 3600) + ($m * 60);
}

/**
 * Get the user's daily working schedule in seconds per ISO weekday (1 = Monday .. 7 = Sunday).
 *
 * @param  DoliDB $db     Database handler
 * @param  int    $userId User ID
 * @return array<int,int> Map of ISO weekday (1..7) => expected working seconds
 */
function dolisirh_pwa_get_user_schedule(DoliDB $db, int $userId): array
{
	$workingHoursObj = new Workinghours($db);
	$workingHours    = $workingHoursObj->fetchCurrentWorkingHours($userId, 'user');

	$dayKeys = [
		1 => 'workinghours_monday',
		2 => 'workinghours_tuesday',
		3 => 'workinghours_wednesday',
		4 => 'workinghours_thursday',
		5 => 'workinghours_friday',
		6 => 'workinghours_saturday',
		7 => 'workinghours_sunday',
	];

	$schedule = [];
	$totalSec = 0;
	foreach ($dayKeys as $isoDay => $key) {
		$mins               = dolisirh_working_hours_of_day($workingHours, $key);
		$schedule[$isoDay]  = max(0, $mins * 60);
		$totalSec          += $schedule[$isoDay];
	}

	if ($totalSec === 0) {
		// Default fallback: 7h/day Monday to Friday
		$schedule = [
			1 => 7 * 3600,
			2 => 7 * 3600,
			3 => 7 * 3600,
			4 => 7 * 3600,
			5 => 7 * 3600,
			6 => 0,
			7 => 0,
		];
	}

	return $schedule;
}

/**
 * Load daily time entries and summaries for a date range [startDate .. endDate].
 *
 * @param  DoliDB        $db              Database handler
 * @param  Translate     $langs           Translation object
 * @param  int           $userId          User ID
 * @param  string        $startDate       Start date (YYYY-MM-DD)
 * @param  string        $endDate         End date (YYYY-MM-DD)
 * @param  array<int,int> $schedule       User schedule per ISO weekday (seconds)
 * @param  int[]         $presenceTaskIds HR presence task IDs
 * @param  int[]         $absenceTaskIds  HR absence task IDs
 * @param  int           $hrProjectId     HR project ID
 * @return array                          Map of YYYY-MM-DD => day data
 */
function dolisirh_pwa_load_days_range(DoliDB $db, Translate $langs, int $userId, string $startDate, string $endDate, array $schedule, array $presenceTaskIds, array $absenceTaskIds, int $hrProjectId): array
{
	$startParts = explode('-', $startDate);
	$endParts   = explode('-', $endDate);
	$startTs    = dol_mktime(0, 0, 0, (int) $startParts[1], (int) $startParts[2], (int) $startParts[0], 'tzserver');
	$endTs      = dol_mktime(0, 0, 0, (int) $endParts[1], (int) $endParts[2], (int) $endParts[0], 'tzserver');

	$days   = [];
	$cursor = $startTs;
	for ($i = 0; $cursor <= $endTs && $i < 95; $i++) {
		$dayKey          = date('Y-m-d', $cursor);
		$isoDay          = (int) date('N', $cursor);
		$isWeekEnd       = $isoDay >= 6;
		$gmtDay          = dol_mktime(0, 0, 0, (int) date('m', $cursor), (int) date('d', $cursor), (int) date('Y', $cursor), 'gmt');
		$isPublicHoliday = !$isWeekEnd && num_public_holiday($gmtDay, $gmtDay + 86400) > 0;
		$expectedSec     = ($isWeekEnd || $isPublicHoliday) ? 0 : ($schedule[$isoDay] ?? 0);

		$dayName = dol_strtoupper(dol_substr(dol_print_date($cursor, '%a', 'tzserver', $langs), 0, 3));

		$days[$dayKey] = [
			'date'         => $dayKey,
			'iso_day'      => $isoDay,
			'day_name'     => $dayName,
			'dd'           => date('d', $cursor),
			'mm_yyyy'      => date('m/Y', $cursor),
			'dmy'          => date('d/m/Y', $cursor),
			'expected_sec' => $expectedSec,
			'pres_sec'     => 0,
			'abs_sec'      => 0,
			'proj_sec'     => 0,
			'hr_entries'   => [],
			'proj_entries' => [],
		];

		$cursor = dolisirh_add_days($cursor, 1);
	}

	$sql  = 'SELECT et.rowid, et.fk_element, et.element_date, et.element_datehour, et.element_date_withhour, et.element_duration, et.note,';
	$sql .= ' pt.ref as task_ref, pt.label as task_label, pt.fk_projet as project_id,';
	$sql .= ' p.ref as project_ref, p.title as project_title';
	$sql .= ' FROM ' . MAIN_DB_PREFIX . 'element_time as et';
	$sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'projet_task as pt ON pt.rowid = et.fk_element';
	$sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'projet as p ON p.rowid = pt.fk_projet';
	$sql .= " WHERE et.elementtype = 'task'";
	$sql .= ' AND et.fk_user = ' . (int) $userId;
	$sql .= " AND et.element_date >= '" . $db->escape($startDate) . "'";
	$sql .= " AND et.element_date <= '" . $db->escape($endDate) . " 23:59:59'";
	$sql .= ' ORDER BY et.element_date DESC, et.rowid DESC';

	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$dayKey    = dol_substr((string) $obj->element_date, 0, 10);
			$taskId    = (int) $obj->fk_element;
			$projectId = (int) $obj->project_id;
			$duration  = (int) $obj->element_duration;

			if ($duration <= 0 || !isset($days[$dayKey])) {
				continue;
			}

			// Entries saved with a start hour are shown as a time range, the others by their duration only
			$startTs = !empty($obj->element_date_withhour) ? $db->jdate($obj->element_datehour) : 0;

			$entry = [
				'id'            => (int) $obj->rowid,
				'task_id'       => $taskId,
				'task_ref'      => (string) $obj->task_ref,
				'task_label'    => (string) $obj->task_label,
				'project_id'    => $projectId,
				'project_ref'   => (string) $obj->project_ref,
				'project_title' => (string) $obj->project_title,
				'duration'      => $duration,
				'duration_hm'   => dolisirh_pwa_format_hm($duration),
				'start_hm'      => $startTs > 0 ? dol_print_date($startTs, '%H:%M', 'tzuserrel') : '',
				'end_hm'        => $startTs > 0 ? dol_print_date($startTs + $duration, '%H:%M', 'tzuserrel') : '',
				'is_hr'         => $hrProjectId > 0 && $projectId === $hrProjectId,
				'note'          => trim((string) $obj->note),
				'date'          => $dayKey,
				'dmy'           => $days[$dayKey]['dmy'],
				'task_url'      => DOL_URL_ROOT . '/projet/tasks/time.php?id=' . $taskId,
				'project_url'   => DOL_URL_ROOT . '/projet/card.php?id=' . $projectId,
			];

			if (in_array($taskId, $absenceTaskIds, true)) {
				$days[$dayKey]['abs_sec']               += $duration;
				$days[$dayKey]['hr_entries'][$taskId][]  = $entry;
			} elseif (in_array($taskId, $presenceTaskIds, true) || ($hrProjectId > 0 && $projectId === $hrProjectId)) {
				$days[$dayKey]['pres_sec']              += $duration;
				$days[$dayKey]['hr_entries'][$taskId][]  = $entry;
			} else {
				$days[$dayKey]['proj_sec']     += $duration;
				$days[$dayKey]['proj_entries'][] = $entry;
			}
		}
		$db->free($resql);
	}

	return $days;
}

/**
 * Compute the Monday..Friday week dates containing $dateStr.
 *
 * @param  string   $dateStr Date in YYYY-MM-DD format
 * @return string[]          Array of 5 YYYY-MM-DD strings (Monday to Friday)
 */
function dolisirh_pwa_get_week_dates(string $dateStr): array
{
	$parts  = explode('-', $dateStr);
	$ts     = dol_mktime(0, 0, 0, (int) $parts[1], (int) $parts[2], (int) $parts[0], 'tzserver');
	$isoDay = (int) date('N', $ts);

	// If Saturday (6) or Sunday (7), show the Monday..Friday of that same ISO week
	$mondayTs = dolisirh_add_days($ts, 1 - $isoDay);

	$weekDates = [];
	for ($i = 0; $i < 5; $i++) {
		$dayTs       = dolisirh_add_days($mondayTs, $i);
		$weekDates[] = date('Y-m-d', $dayTs);
	}

	return $weekDates;
}

/**
 * Build the complete state payload for the DoliSIRH PWA UI.
 *
 * @param  DoliDB    $db              Database handler
 * @param  Translate $langs           Translation object
 * @param  User      $user            Current user
 * @param  string    $selectedDateStr Selected date (YYYY-MM-DD)
 * @param  string    $calMonthStr     Selected calendar month (YYYY-MM)
 * @return array                      Full state array for JSON / initial render
 */
function dolisirh_pwa_get_state(DoliDB $db, Translate $langs, User $user, string $selectedDateStr = '', string $calMonthStr = ''): array
{
	global $conf;

	$todayTs  =dol_mktime(0, 0, 0, (int) date('m'), (int) date('d'), (int) date('Y'), 'tzserver');
	$todayStr = date('Y-m-d', $todayTs);

	if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDateStr)) {
		$selectedDateStr = $todayStr;
	}

	// A weekend day stays selected: time worked on a Saturday must not be saved on the Friday
	$selParts = explode('-', $selectedDateStr);

	if (!preg_match('/^\d{4}-\d{2}$/', $calMonthStr)) {
		$calMonthStr = $selParts[0] . '-' . $selParts[1];
	}

	$hrProjectId   = getDolGlobalInt('DOLISIRH_HR_PROJECT');
	$hrProjectData = null;
	if ($hrProjectId > 0) {
		$hrProject = new Project($db);
		if ($hrProject->fetch($hrProjectId) > 0) {
			$hrProjectData = [
				'id'    => (int) $hrProject->id,
				'ref'   => (string) $hrProject->ref,
				'title' => (string) $hrProject->title,
				'url'   => DOL_URL_ROOT . '/projet/tasks.php?id=' . (int) $hrProject->id,
			];
		}
	}

	$hrTasksByType   = timesheet_get_hr_project_tasks_by_type($hrProjectId);
	$presenceTaskIds = [];
	$absenceTaskIds  = [];
	$presenceTasks   = [];
	$absenceTasks    = [];

	foreach ($hrTasksByType['work_time'] as $t) {
		$presenceTaskIds[] = (int) $t->id;
		$presenceTasks[]   = [
			'id'    => (int) $t->id,
			'ref'   => (string) $t->ref,
			'label' => (string) $t->label,
			'url'   => DOL_URL_ROOT . '/projet/tasks/time.php?id=' . (int) $t->id,
		];
	}
	foreach ($hrTasksByType['no_work_time'] as $t) {
		$absenceTaskIds[] = (int) $t->id;
		$absenceTasks[]   = [
			'id'    => (int) $t->id,
			'ref'   => (string) $t->ref,
			'label' => (string) $t->label,
			'url'   => DOL_URL_ROOT . '/projet/tasks/time.php?id=' . (int) $t->id,
		];
	}

	$schedule  = dolisirh_pwa_get_user_schedule($db, (int) $user->id);
	$weekDates = dolisirh_pwa_get_week_dates($selectedDateStr);

	// Selected date's month bounds
	$selMonthFirstTs = dol_mktime(0, 0, 0, (int) $selParts[1], 1, (int) $selParts[0], 'tzserver');
	$selDaysInMonth  = (int) date('t', $selMonthFirstTs);
	$selMonthStart   = sprintf('%04d-%02d-01', (int) $selParts[0], (int) $selParts[1]);
	$selMonthEnd     = sprintf('%04d-%02d-%02d', (int) $selParts[0], (int) $selParts[1], $selDaysInMonth);

	// Calendar month bounds
	$calParts        = explode('-', $calMonthStr);
	$calMonthFirstTs = dol_mktime(0, 0, 0, (int) $calParts[1], 1, (int) $calParts[0], 'tzserver');
	$calDaysInMonth  = (int) date('t', $calMonthFirstTs);
	$calMonthStart   = sprintf('%04d-%02d-01', (int) $calParts[0], (int) $calParts[1]);
	$calMonthEnd     = sprintf('%04d-%02d-%02d', (int) $calParts[0], (int) $calParts[1], $calDaysInMonth);

	// Load union of week range + selected month range
	$rangeStart = min($weekDates[0], $selMonthStart, $calMonthStart);
	$rangeEnd   = max($weekDates[4], $selMonthEnd, $calMonthEnd);

	$allDays = dolisirh_pwa_load_days_range($db, $langs, (int) $user->id, $rangeStart, $rangeEnd, $schedule, $presenceTaskIds, $absenceTaskIds, $hrProjectId);

	// Compute selected month summary
	$selMonthExpectedSec = 0;
	$selMonthLoggedHrSec = 0;
	foreach ($allDays as $dKey => $dVal) {
		if ($dKey >= $selMonthStart && $dKey <= $selMonthEnd) {
			$selMonthExpectedSec += $dVal['expected_sec'];
			$selMonthLoggedHrSec += ($dVal['pres_sec'] + $dVal['abs_sec']);
		}
	}

	// Compute calendar month summary + days list
	$calMonthExpectedSec = 0;
	$calMonthLoggedHrSec = 0;
	$calMonthDays        = [];
	foreach ($allDays as $dKey => $dVal) {
		if ($dKey >= $calMonthStart && $dKey <= $calMonthEnd) {
			$calMonthExpectedSec  += $dVal['expected_sec'];
			$calMonthLoggedHrSec  += ($dVal['pres_sec'] + $dVal['abs_sec']);
			$calMonthDays[$dKey]   = $dVal;
		}
	}

	$weekDays = [];
	foreach ($weekDates as $wDate) {
		if (isset($allDays[$wDate])) {
			$weekDays[] = $allDays[$wDate];
		}
	}

	// Every entry of the selected day, HR and projects together, in clock order for the Pointer tab
	$selectedDay = $allDays[$selectedDateStr] ?? null;
	if ($selectedDay !== null) {
		$dayEntries = $selectedDay['proj_entries'];
		foreach ($selectedDay['hr_entries'] as $taskEntries) {
			$dayEntries = array_merge($dayEntries, $taskEntries);
		}

		$entriesMedias = dolisirh_pwa_get_entries_medias($conf, $dayEntries);
		foreach ($dayEntries as $index => $dayEntry) {
			$dayEntries[$index]['medias'] = $entriesMedias[$dayEntry['id']] ?? [];
		}

		usort($dayEntries, function (array $a, array $b): int {
			$aStart = $a['start_hm'] !== '' ? $a['start_hm'] : '99:99';
			$bStart = $b['start_hm'] !== '' ? $b['start_hm'] : '99:99';
			return [$aStart, $a['id']] <=> [$bStart, $b['id']];
		});
		$selectedDay['entries'] = $dayEntries;
	}

	// One-tap tasks: HR presence first, then the tasks the user spent time on lately
	$quickTasks = [];
	if (!empty($presenceTasks) && $hrProjectData !== null) {
		$quickTasks[] = [
			'id'            => $presenceTasks[0]['id'],
			'ref'           => $presenceTasks[0]['ref'],
			'label'         => $presenceTasks[0]['label'],
			'project_id'    => $hrProjectData['id'],
			'project_ref'   => $hrProjectData['ref'],
			'project_title' => $hrProjectData['title'],
			'project_url'   => $hrProjectData['url'],
			'is_hr'         => true,
		];
	}
	foreach (dolisirh_pwa_get_recent_tasks($db, (int) $user->id, $hrProjectId) as $recentTask) {
		if (empty($quickTasks) || $recentTask['id'] !== $quickTasks[0]['id']) {
			$quickTasks[] = $recentTask;
		}
	}

	// Load open projects (excluding HR project) with planned & effective workload
	$projects = [];
	$sqlProj  = 'SELECT p.rowid, p.ref, p.title,';
	$sqlProj .= ' COALESCE(SUM(pt.planned_workload), 0) as planned_workload,';
	$sqlProj .= ' COALESCE(SUM(pt.duration_effective), 0) as duration_effective';
	$sqlProj .= ' FROM ' . MAIN_DB_PREFIX . 'projet as p';
	$sqlProj .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'projet_task as pt ON pt.fk_projet = p.rowid';
	$sqlProj .= ' WHERE p.entity IN (' . getEntity('project') . ')';
	$sqlProj .= ' AND p.fk_statut = 1';
	if ($hrProjectId > 0) {
		$sqlProj .= ' AND p.rowid <> ' . (int) $hrProjectId;
	}
	$sqlProj .= ' GROUP BY p.rowid, p.ref, p.title';
	$sqlProj .= ' ORDER BY p.ref DESC';

	$resProj = $db->query($sqlProj);
	if ($resProj) {
		while ($objP = $db->fetch_object($resProj)) {
			$pid        = (int) $objP->rowid;
			$plannedSec = (int) $objP->planned_workload;
			$spentSec   = (int) $objP->duration_effective;
			$projects[] = [
				'id'                 => $pid,
				'ref'                => (string) $objP->ref,
				'title'              => (string) $objP->title,
				'planned_workload'   => $plannedSec,
				'duration_effective' => $spentSec,
				'planned_hm'         => $plannedSec > 0 ? dolisirh_pwa_format_hm($plannedSec) : '-',
				'spent_hm'           => dolisirh_pwa_format_hm($spentSec),
				'url'                => DOL_URL_ROOT . '/projet/card.php?id=' . $pid,
			];
		}
		$db->free($resProj);
	}

	return [
		'today'         => $todayStr,
		'selected_date' => $selectedDateStr,
		'selected_day'  => $selectedDay,
		'quick_tasks'   => $quickTasks,
		'week_days'     => $weekDays,
		'hr_project'    => $hrProjectData,
		'hr_tasks'      => [
			'presence' => $presenceTasks,
			'absence'  => $absenceTasks,
		],
		'sel_month'     => [
			'ym'              => $selParts[0] . '-' . $selParts[1],
			'label'           => dol_ucfirst(dol_print_date($selMonthFirstTs, '%B %Y', 'tzserver', $langs)),
			'expected_sec'    => $selMonthExpectedSec,
			'logged_hr_sec'   => $selMonthLoggedHrSec,
			'expected_hm'     => dolisirh_pwa_format_hm($selMonthExpectedSec),
			'logged_hr_hm'    => dolisirh_pwa_format_hm($selMonthLoggedHrSec),
		],
		'cal_month'     => [
			'ym'              => $calMonthStr,
			'year'            => (int) $calParts[0],
			'month'           => (int) $calParts[1],
			'label'           => dol_ucfirst(dol_print_date($calMonthFirstTs, '%B %Y', 'tzserver', $langs)),
			'expected_sec'    => $calMonthExpectedSec,
			'logged_hr_sec'   => $calMonthLoggedHrSec,
			'expected_hm'     => dolisirh_pwa_format_hm($calMonthExpectedSec),
			'logged_hr_hm'    => dolisirh_pwa_format_hm($calMonthLoggedHrSec),
			'days'            => $calMonthDays,
		],
		'projects'      => $projects,
	];
}

/**
 * Fetch tasks for a given project ID.
 *
 * @param  DoliDB $db        Database handler
 * @param  int    $projectId Project ID
 * @return array             List of tasks with stats
 */
function dolisirh_pwa_get_project_tasks(DoliDB $db, int $projectId): array
{
	if ($projectId <= 0) {
		return [];
	}

	$tasks = [];
	$sql   = 'SELECT pt.rowid, pt.ref, pt.label, pt.planned_workload, pt.duration_effective, pt.fk_statut';
	$sql  .= ' FROM ' . MAIN_DB_PREFIX . 'projet_task as pt';
	$sql  .= ' WHERE pt.fk_projet = ' . (int) $projectId;
	$sql  .= ' AND (pt.fk_statut IS NULL OR pt.fk_statut < 3)';
	$sql  .= ' ORDER BY pt.rang ASC, pt.ref ASC';

	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$tid        = (int) $obj->rowid;
			$plannedSec = (int) $obj->planned_workload;
			$spentSec   = (int) $obj->duration_effective;
			$tasks[]    = [
				'id'                 => $tid,
				'ref'                => (string) $obj->ref,
				'label'              => (string) $obj->label,
				'planned_workload'   => $plannedSec,
				'duration_effective' => $spentSec,
				'planned_hm'         => $plannedSec > 0 ? dolisirh_pwa_format_hm($plannedSec) : '-',
				'spent_hm'           => dolisirh_pwa_format_hm($spentSec),
				'url'                => DOL_URL_ROOT . '/projet/tasks/time.php?id=' . $tid,
			];
		}
		$db->free($resql);
	}

	return $tasks;
}

/**
 * Ensure the user is assigned as contributor on the project and task, as the browser extension does.
 *
 * @param  DoliDB $db        Database handler
 * @param  User   $user      Current user
 * @param  int    $projectId Project ID
 * @param  int    $taskId    Task ID
 * @return void
 */
function dolisirh_pwa_ensure_contributor(DoliDB $db, User $user, int $projectId, int $taskId): void
{
	if ($projectId > 0) {
		$project = new Project($db);
		if ($project->fetch($projectId) > 0) {
			$project->add_contact($user->id, 'PROJECTCONTRIBUTOR', 'internal');
		}
	}
	if ($taskId > 0) {
		$task = new Task($db);
		if ($task->fetch($taskId) > 0) {
			$task->add_contact($user->id, 'TASKCONTRIBUTOR', 'internal');
		}
	}
}

/**
 * Create or return the default task SPJ_<ProjectTitle> for a project.
 *
 * @param  DoliDB $db        Database handler
 * @param  User   $user      Current user
 * @param  int    $projectId Project ID
 * @return array             Created or existing task data, or empty array on failure
 */
function dolisirh_pwa_create_default_task(DoliDB $db, User $user, int $projectId): array
{
	if ($projectId <= 0) {
		return [];
	}

	$project = new Project($db);
	if ($project->fetch($projectId) <= 0) {
		return [];
	}

	$expectedLabel = 'SPJ_' . trim((string) $project->title);
	$existingTasks = dolisirh_pwa_get_project_tasks($db, $projectId);
	foreach ($existingTasks as $t) {
		if (dol_strtolower(trim($t['label'])) === dol_strtolower($expectedLabel)) {
			dolisirh_pwa_ensure_contributor($db, $user, $projectId, (int) $t['id']);
			return [
				'created' => false,
				'task'    => $t,
				'tasks'   => $existingTasks,
			];
		}
	}

	$taskRefClass = getDolGlobalString('PROJECT_TASK_ADDON', 'mod_task_simple');
	$defaultRef   = 'TK' . date('ym') . '-0001';
	if (!empty($taskRefClass) && is_readable(DOL_DOCUMENT_ROOT . '/core/modules/project/task/' . $taskRefClass . '.php')) {
		require_once DOL_DOCUMENT_ROOT . '/core/modules/project/task/' . $taskRefClass . '.php';
		$modTask    = new $taskRefClass();
		$defaultRef = $modTask->getNextValue(null, null);
	}

	$task             = new Task($db);
	$task->fk_project = $projectId;
	$task->ref        = $defaultRef;
	$task->label      = $expectedLabel;
	$task->date_c     = dol_now();
	$task->date_start = dol_now();
	$task->status     = 1;

	$newTaskId = $task->create($user);
	if ($newTaskId <= 0) {
		return [];
	}

	dolisirh_pwa_ensure_contributor($db, $user, $projectId, (int) $newTaskId);
	$updatedTasks = dolisirh_pwa_get_project_tasks($db, $projectId);
	$createdTask  = null;
	foreach ($updatedTasks as $t) {
		if ((int) $t['id'] === (int) $newTaskId) {
			$createdTask = $t;
			break;
		}
	}

	return [
		'created' => true,
		'task'    => $createdTask,
		'tasks'   => $updatedTasks,
	];
}
