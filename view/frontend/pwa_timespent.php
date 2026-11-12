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
 * \file    view/frontend/pwa_timespent.php
 * \ingroup dolisirh
 * \brief   Mobile PWA page for DoliSIRH time tracking (Semaine RH, Projet avec médias, Mensuel)
 */

// Load DoliSIRH environment
if (file_exists('../dolisirh.main.inc.php')) {
	require_once __DIR__ . '/../dolisirh.main.inc.php';
} elseif (file_exists('../../dolisirh.main.inc.php')) {
	require_once __DIR__ . '/../../dolisirh.main.inc.php';
} else {
	die('Include of dolisirh main fails');
}

require_once DOL_DOCUMENT_ROOT . '/core/class/html.form.class.php';
require_once __DIR__ . '/../../lib/dolisirh_pwa.lib.php';

/** @var Conf $conf */
/** @var DoliDB $db */
/** @var HookManager $hookmanager */
/** @var Translate $langs */
/** @var User $user */

saturne_load_langs(['projects', 'users', 'dolisirh@dolisirh', 'saturne@saturne', 'medias@saturne']);

$action        = GETPOST('action', 'aZ09');
$subaction     = GETPOST('subaction', 'aZ09');
$selectedDate  = GETPOST('date', 'alphanohtml');
$calMonth      = GETPOST('cal_month', 'alphanohtml');

$hookmanager->initHooks(['dolisirhpwatimespent']);

// Security check
$permissionToRead = $user->hasRight('dolisirh', 'lire') || $user->hasRight('projet', 'lire');
saturne_check_access($permissionToRead);

// Isolate the medias picked before the time entry is saved, per user session
$uploadTokenContext = 'dolisirh_pwa_timespent_' . (int) $user->id;
$uploadToken        = saturne_get_upload_token($uploadTokenContext);
$subDir             = dolisirh_pwa_temp_sub_dir($uploadToken);

/*
 * Actions
 */

$parameters = [];
$reshook    = $hookmanager->executeHooks('doActions', $parameters, $user, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	require_once __DIR__ . '/../../core/tpl/frontend/dolisirh_pwa_timespent_actions_frontend.tpl.php';
}

/*
 * View
 */

$title       = $langs->trans('TimeSpending');
$manifestUrl = dol_buildpath('/dolisirh/manifest.json.php', 1);
$head        = '<link rel="manifest" href="' . dol_escape_htmltag($manifestUrl) . '">';

$conf->dol_hide_topmenu  = 1;
$conf->dol_hide_leftmenu = 1;

saturne_header(1, $head, $title, '', '', 0, 0, [], [], '', 'template-pwa dolisirh-pwa-body');

dolisirh_pwa_purge_temp_dirs($conf, $uploadToken);

$initialState = dolisirh_pwa_get_state($db, $langs, $user, $selectedDate, $calMonth);

// One-tap durations of the Pointer tab, in minutes
$durationChips = [];
foreach ([15, 30, 60, 120, 210, 420] as $minutes) {
	$durationChips[$minutes] = $minutes < 60 ? $minutes . ' min' : intdiv($minutes, 60) . ' h' . ($minutes % 60 ? ' ' . sprintf('%02d', $minutes % 60) : '');
}

$labels = [
	'save'           => $langs->transnoentities('PwaClockSave'),
	'saved'          => $langs->transnoentities('PwaClockSaved'),
	'mediasSaved'    => $langs->transnoentities('PwaClockMediasSaved', '%s'),
	'deleted'        => $langs->transnoentities('PwaClockDeleted'),
	'deleteConfirm'  => $langs->transnoentities('PwaClockDeleteConfirm'),
	'running'        => $langs->transnoentities('PwaClockRunning', '%s'),
	'dayEntries'     => $langs->transnoentities('PwaClockDayEntries', '%s'),
	'noEntry'        => $langs->transnoentities('PwaClockNoEntry'),
	'total'          => $langs->transnoentities('Total'),
	'noProject'      => $langs->transnoentities('PwaNoProjectFound'),
	'noTask'         => $langs->transnoentities('PwaNoTaskFound'),
	'playVoice'      => $langs->transnoentities('PwaClockPlayVoice'),
	'networkError'   => $langs->transnoentities('PwaNetworkError'),
	'error'          => $langs->transnoentities('Error'),
	'presence'       => $langs->transnoentities('PwaClockPresence'),
	'overlap'        => $langs->transnoentities('PwaClockOverlap', '%s'),
];

require_once __DIR__ . '/../../core/tpl/frontend/dolisirh_pwa_header.tpl.php';
?>

<main class="dolisirh-pwa-main ts-card" id="dolisirh-pwa-app" data-ajax-url="<?php echo dol_escape_htmltag($_SERVER['PHP_SELF']); ?>">
	<input type="hidden" name="token" id="dolisirh-pwa-token" value="<?php echo newToken(); ?>">

	<!-- 3-Tab Mode Switcher: Pointer / Semaine / Mensuel -->
	<div class="ts-mode-switch" role="tablist">
		<button type="button" id="pwa-mode-project" class="ts-mode-btn" data-mode="project" role="tab" aria-selected="false">
			<i class="fas fa-stopwatch"></i>
			<span><?php echo $langs->trans('PwaClockTab'); ?></span>
		</button>
		<button type="button" id="pwa-mode-week" class="ts-mode-btn is-active" data-mode="week" role="tab" aria-selected="true">
			<i class="far fa-clock"></i>
			<span><?php echo $langs->trans('Week'); ?></span>
		</button>
		<button type="button" id="pwa-mode-monthly" class="ts-mode-btn" data-mode="monthly" role="tab" aria-selected="false">
			<i class="far fa-calendar-alt"></i>
			<span><?php echo $langs->trans('Monthly'); ?></span>
		</button>
	</div>

	<!-- Status Toast Message -->
	<div id="pwa-status-msg" class="ts-msg" hidden></div>

	<!-- HR Project Info & Monthly Summary (Semaine mode) -->
	<div id="pwa-hr-top-bars">
		<div id="pwa-hr-project-bar" class="ts-hr-project-bar"></div>
		<div id="pwa-hr-month-bar" class="ts-hr-summary-bar">
			<span id="pwa-hr-month-label" class="ts-hr-summary-bar__label"></span>
			<strong id="pwa-hr-month-values" class="ts-hr-summary-bar__values"></strong>
		</div>
	</div>

	<!-- Interactive 5-Day Week Selector (Semaine & Projet modes) -->
	<div id="pwa-week-bar" class="ts-week-bar">
		<button type="button" id="pwa-week-prev" class="ts-week-nav" title="<?php echo dol_escape_htmltag($langs->trans('Previous')); ?>">&lsaquo;</button>
		<div id="pwa-week-days" class="ts-week-days"></div>
		<button type="button" id="pwa-week-next" class="ts-week-nav" title="<?php echo dol_escape_htmltag($langs->trans('Next')); ?>">&rsaquo;</button>
	</div>

	<!-- TAB 1: SEMAINE (HR Presence & Absence) -->
	<section id="pwa-view-week" class="ts-tab-panel">
		<div id="pwa-hr-task-sections" class="ts-hr-task-sections"></div>
	</section>

	<!-- TAB 2: POINTER (start and end time, task, note, photos and voice notes) -->
	<section id="pwa-view-project" class="ts-tab-panel" hidden>
		<!-- One-tap tasks: HR presence and the latest tasks of the user -->
		<div class="ts-field" id="pwa-quick-field" hidden>
			<label><?php echo $langs->trans('PwaQuickTasks'); ?></label>
			<div id="pwa-quick-tasks" class="ts-quick-tasks"></div>
		</div>

		<!-- Project Selector -->
		<div class="ts-field">
			<div class="ts-field-header">
				<label for="pwa-proj-search"><?php echo $langs->trans('Project'); ?></label>
				<span id="pwa-proj-stats" class="ts-inline-stats"></span>
			</div>
			<div class="ts-combobox" id="pwa-proj-combobox">
				<input type="text" id="pwa-proj-search" class="ts-combobox__input" placeholder="<?php echo dol_escape_htmltag($langs->trans('SelectProject')); ?>" autocomplete="off">
				<button type="button" id="pwa-proj-toggle" class="ts-combobox__toggle" tabindex="-1"><i class="fas fa-chevron-down"></i></button>
				<ul id="pwa-proj-list" class="ts-combobox__list" hidden></ul>
			</div>
		</div>

		<!-- Task Selector -->
		<div class="ts-field" id="pwa-task-field">
			<div class="ts-field-header">
				<label for="pwa-task-search"><?php echo $langs->trans('Task'); ?></label>
				<span id="pwa-task-stats" class="ts-inline-stats"></span>
				<button type="button" id="pwa-btn-create-default-task" class="ts-btn-icon ts-btn-add-task" title="Créer / sélectionner la tâche par défaut SPJ_<Titre du projet>" disabled>
					<i class="fas fa-plus"></i>
				</button>
			</div>
			<div class="ts-combobox" id="pwa-task-combobox" hidden>
				<input type="text" id="pwa-task-search" class="ts-combobox__input" placeholder="<?php echo dol_escape_htmltag($langs->trans('SelectTask')); ?>" autocomplete="off">
				<button type="button" id="pwa-task-toggle" class="ts-combobox__toggle" tabindex="-1"><i class="fas fa-chevron-down"></i></button>
				<ul id="pwa-task-list" class="ts-combobox__list" hidden></ul>
			</div>
		</div>

		<!-- Start and end time: the duration follows, or a duration moves the end -->
		<div class="ts-clock" id="pwa-clock">
			<div id="pwa-clock-running" class="ts-clock__running" hidden></div>
			<div class="ts-clock__range">
				<div class="ts-clock__field">
					<label for="pwa-proj-start"><?php echo $langs->trans('PwaClockStart'); ?></label>
					<input type="time" id="pwa-proj-start" step="60" required>
					<button type="button" class="ts-clock__now" data-clock-now="start"><i class="far fa-clock"></i> <?php echo $langs->trans('PwaClockNow'); ?></button>
				</div>
				<span class="ts-clock__arrow" aria-hidden="true"><i class="fas fa-long-arrow-alt-right"></i></span>
				<div class="ts-clock__field">
					<label for="pwa-proj-end"><?php echo $langs->trans('PwaClockEnd'); ?></label>
					<input type="time" id="pwa-proj-end" step="60" required>
					<button type="button" class="ts-clock__now" data-clock-now="end"><i class="far fa-clock"></i> <?php echo $langs->trans('PwaClockNow'); ?></button>
				</div>
			</div>
			<div class="ts-clock__chips" role="group" aria-label="<?php echo dol_escape_htmltag($langs->trans('Duration')); ?>">
				<?php foreach ($durationChips as $minutes => $chipLabel) : ?>
					<button type="button" class="ts-chip" data-duration="<?php echo (int) $minutes; ?>"><?php echo dol_escape_htmltag($chipLabel); ?></button>
				<?php endforeach; ?>
			</div>
			<div class="ts-row-2col">
				<div class="ts-field">
					<label for="pwa-proj-duration"><?php echo $langs->trans('Duration'); ?> <span id="pwa-proj-overnight" class="ts-clock__hint" hidden><?php echo $langs->trans('PwaClockOvernight'); ?></span></label>
					<input type="time" id="pwa-proj-duration" value="01:00" step="60">
				</div>
				<div class="ts-field">
					<label for="pwa-proj-date"><?php echo $langs->trans('Date'); ?></label>
					<input type="date" id="pwa-proj-date">
				</div>
			</div>
			<div id="pwa-clock-overlap" class="ts-clock__overlap" hidden></div>
		</div>

		<!-- Note / Comment -->
		<div class="ts-field">
			<label for="pwa-proj-note"><?php echo $langs->trans('Note'); ?> / <?php echo $langs->trans('Comment'); ?> <span id="pwa-proj-note-req" class="ts-req" title="<?php echo dol_escape_htmltag($langs->trans('PwaNoteOrMediaHelp')); ?>">*</span></label>
			<textarea id="pwa-proj-note" rows="2" placeholder="<?php echo dol_escape_htmltag($langs->trans('PwaNotePlaceholder')); ?>"></textarea>
		</div>

		<!-- Saturne media block: photos and voice notes, attached to the time entry once saved -->
		<div class="ts-field ts-media-field">
			<div class="ts-field-header">
				<label><i class="fas fa-camera"></i> <?php echo $langs->trans('Medias'); ?></label>
			</div>
			<div id="pwa-proj-media-wrapper" class="dolisirh-pwa-media-block">
				<?php print dolisirh_pwa_media_block($subDir); ?>
			</div>
		</div>

		<button type="button" id="pwa-btn-save-proj" class="ts-btn-clock" disabled>
			<i class="fas fa-check"></i>
			<span id="pwa-btn-save-proj-label"><?php echo $langs->trans('PwaClockSave'); ?></span>
		</button>

		<!-- Entries of the selected day, in clock order -->
		<div id="pwa-proj-history" class="ts-proj-history"></div>
	</section>

	<!-- TAB 3: MENSUEL (Monthly Calendar) -->
	<section id="pwa-view-monthly" class="ts-tab-panel" hidden>
		<div class="ts-cal-header">
			<button type="button" id="pwa-cal-prev" class="ts-btn-icon" title="<?php echo dol_escape_htmltag($langs->trans('Previous')); ?>">&lsaquo;</button>
			<div class="ts-cal-title-wrap">
				<span id="pwa-cal-month-label" class="ts-cal-month-label"></span>
				<span id="pwa-cal-month-summary" class="ts-cal-month-summary"></span>
			</div>
			<button type="button" id="pwa-cal-next" class="ts-btn-icon" title="<?php echo dol_escape_htmltag($langs->trans('Next')); ?>">&rsaquo;</button>
		</div>
		<div class="ts-cal-legend">
			<span class="ts-cal-legend-item"><i class="ts-dot is-ok"></i> OK</span>
			<span class="ts-cal-legend-item"><i class="ts-dot is-over"></i> +</span>
			<span class="ts-cal-legend-item"><i class="ts-dot is-ko"></i> Manque</span>
			<span class="ts-cal-legend-item"><i class="ts-dot is-grey"></i> À venir / 0h</span>
			<span class="ts-cal-legend-item"><b class="ts-pa-pill is-p">P</b> Présent</span>
			<span class="ts-cal-legend-item"><b class="ts-pa-pill is-a">A</b> Absent</span>
		</div>
		<div id="pwa-cal-grid" class="ts-cal-grid"></div>
	</section>
</main>

<?php // A note holding "</script>" must not close the data element: "<\/" reads the same in JSON ?>
<script id="dolisirh-pwa-initial-state" type="application/json"><?php echo str_replace('</', '<\/', (string) json_encode($initialState)); ?></script>
<script id="dolisirh-pwa-labels" type="application/json"><?php echo str_replace('</', '<\/', (string) json_encode($labels)); ?></script>

<?php
$mediaEditorModalPath = dol_buildpath('/saturne/core/tpl/medias/media_editor_modal.tpl.php', 0);
if (is_readable($mediaEditorModalPath)) {
	include $mediaEditorModalPath;
}
$photoEditorModalPath = dol_buildpath('/saturne/core/tpl/medias/photo_editor_modal.tpl.php', 0);
if (is_readable($photoEditorModalPath)) {
	include $photoEditorModalPath;
}

llxFooter();
$db->close();
