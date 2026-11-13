/* Copyright (C) 2026 EVARISK <technique@evarisk.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    js/modules/pwa.js
 * \ingroup dolisirh
 * \brief   JavaScript controller for the DoliSIRH mobile Time Tracking PWA
 */

window.dolisirh = window.dolisirh || {};
window.dolisirh.pwa = {
	state: null,
	labels: {},
	mode: 'project',
	selectedProject: null,
	selectedTask: null,
	projectTasks: [],
	msgTimer: null,
	// A start or end time typed or set by the user is kept, otherwise it follows the clock
	startTouched: false,
	endTouched: false,
	restoringDraft: false,
	saving: false,
	clockDate: '',
	// Running entry whose stop panel is open, null while the start panel shows
	stopping: null,
	tickTimer: null,
	voice: null,
	draftKey: 'dolisirh_pwa_draft',
	// Answer of the confirmation dialog while it is open
	confirmResolve: null,
};

/**
 * Initialize PWA module when on the PWA page.
 *
 * @returns {void}
 */
window.dolisirh.pwa.init = function() {
	var appEl = document.getElementById('dolisirh-pwa-app');
	if (!appEl) {
		return;
	}

	window.dolisirh.pwa.state = window.dolisirh.pwa.readJsonScript('dolisirh-pwa-initial-state', null);
	window.dolisirh.pwa.labels = window.dolisirh.pwa.readJsonScript('dolisirh-pwa-labels', {});

	var savedMode = window.dolisirh.pwa.storageGet('dolisirh_pwa_mode');
	if (savedMode === 'project' || savedMode === 'week' || savedMode === 'monthly') {
		window.dolisirh.pwa.mode = savedMode;
	}

	// The first render puts the default clock times: the kept draft must not be overwritten by them
	window.dolisirh.pwa.restoringDraft = true;
	window.dolisirh.pwa.bindEvents();
	window.dolisirh.pwa.renderAll();
	window.dolisirh.pwa.restoreDraft();

	// The running entries count their time, and untouched start and end times follow the clock
	window.dolisirh.pwa.tickTimer = setInterval(window.dolisirh.pwa.tickClock, 20000);
	document.addEventListener('visibilitychange', function() {
		if (!document.hidden) {
			window.dolisirh.pwa.tickClock();
		}
	});
};

/**
 * Data written by the page in a JSON script element.
 *
 * @param {string} id Element id
 * @param {*} fallback Value when the element is missing or unreadable
 * @returns {*}
 */
window.dolisirh.pwa.readJsonScript = function(id, fallback) {
	var el = document.getElementById(id);
	if (!el || !el.textContent) {
		return fallback;
	}
	try {
		return JSON.parse(el.textContent);
	} catch (e) {
		return fallback;
	}
};

/**
 * Read a value kept in the browser, which may refuse storage (private browsing).
 *
 * @param {string} key Storage key
 * @returns {string|null}
 */
window.dolisirh.pwa.storageGet = function(key) {
	try {
		return localStorage.getItem(key);
	} catch (e) {
		return null;
	}
};

/**
 * Keep a value in the browser, or remove it when the value is null.
 *
 * @param {string} key Storage key
 * @param {string|null} value Value to keep
 * @returns {void}
 */
window.dolisirh.pwa.storageSet = function(key, value) {
	try {
		if (value === null) {
			localStorage.removeItem(key);
		} else {
			localStorage.setItem(key, value);
		}
	} catch (e) {
		// Nothing kept: the page works the same, only the draft is lost on reload
	}
};

/**
 * Translated label, with its %s replaced by the argument.
 *
 * @param {string} key Label key
 * @param {string} [arg] Value replacing %s
 * @returns {string}
 */
window.dolisirh.pwa.label = function(key, arg) {
	var text = window.dolisirh.pwa.labels[key] || key;
	return arg === undefined ? text : text.replace('%s', arg);
};

/**
 * Parse a HH:MM clock time into minutes since midnight.
 *
 * @param {string} hm HH:MM string
 * @returns {number} Minutes, -1 when the value is not a clock time
 */
window.dolisirh.pwa.hmToMin = function(hm) {
	var match = /^(\d{1,2}):(\d{2})/.exec(String(hm || ''));
	if (!match) {
		return -1;
	}
	var h = parseInt(match[1], 10);
	var m = parseInt(match[2], 10);
	return (h > 23 || m > 59) ? -1 : (h * 60) + m;
};

/**
 * Format minutes since midnight as a HH:MM clock time, wrapping around midnight.
 *
 * @param {number} min Minutes
 * @returns {string}
 */
window.dolisirh.pwa.minToHM = function(min) {
	var m = ((Math.round(min) % 1440) + 1440) % 1440;
	return String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');
};

/**
 * Human readable duration: 45 min, 2 h, 1 h 30.
 *
 * @param {number} min Duration in minutes
 * @returns {string}
 */
window.dolisirh.pwa.durationLabel = function(min) {
	var m = Math.max(0, Math.round(min));
	if (m < 60) {
		return m + ' min';
	}
	return Math.floor(m / 60) + ' h' + (m % 60 ? ' ' + String(m % 60).padStart(2, '0') : '');
};

/**
 * Current clock time of the device, as HH:MM.
 *
 * @returns {string}
 */
window.dolisirh.pwa.nowHM = function() {
	var d = new Date();
	return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
};

/**
 * Today on the device, as YYYY-MM-DD.
 *
 * @returns {string}
 */
window.dolisirh.pwa.localToday = function() {
	var d = new Date();
	return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
};

/**
 * Format seconds into HH:MM.
 *
 * @param {number} sec Seconds
 * @returns {string}
 */
window.dolisirh.pwa.secToHM = function(sec) {
	var s = Math.max(0, Math.round(Number(sec) || 0));
	var mins = Math.round(s / 60);
	var h = Math.floor(mins / 60);
	var m = mins % 60;
	return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
};

/**
 * Parse HH:MM into seconds.
 *
 * @param {string} hm HH:MM string
 * @returns {number}
 */
window.dolisirh.pwa.hmToSec = function(hm) {
	if (!hm) {
		return 0;
	}
	var parts = String(hm).split(':');
	var h = parseInt(parts[0] || '0', 10);
	var m = parseInt(parts[1] || '0', 10);
	if (isNaN(h) || isNaN(m)) {
		return 0;
	}
	return Math.max(0, (h * 3600) + (m * 60));
};

/**
 * Escape HTML special characters.
 *
 * @param {string} str Input string
 * @returns {string}
 */
window.dolisirh.pwa.esc = function(str) {
	return String(str || '')
		.replace(/&/g, '&amp;')
		.replace(/</g, '&lt;')
		.replace(/>/g, '&gt;')
		.replace(/"/g, '&quot;');
};

/**
 * Add days to a YYYY-MM-DD date string.
 *
 * @param {string} dateStr YYYY-MM-DD
 * @param {number} deltaDays Days to add
 * @returns {string}
 */
window.dolisirh.pwa.addDays = function(dateStr, deltaDays) {
	var parts = String(dateStr).split('-');
	var d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10), 12, 0, 0);
	d.setDate(d.getDate() + deltaDays);
	var y = d.getFullYear();
	var m = String(d.getMonth() + 1).padStart(2, '0');
	var day = String(d.getDate()).padStart(2, '0');
	return y + '-' + m + '-' + day;
};

/**
 * Shift a YYYY-MM month string by deltaMonths.
 *
 * @param {string} ymStr YYYY-MM
 * @param {number} deltaMonths Months to add
 * @returns {string}
 */
window.dolisirh.pwa.addMonths = function(ymStr, deltaMonths) {
	var parts = String(ymStr).split('-');
	var d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1 + deltaMonths, 1, 12, 0, 0);
	return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
};

/**
 * Show a temporary status message banner.
 *
 * @param {string} text Message text
 * @param {string} type 'ok' | 'err' | 'info'
 * @returns {void}
 */
window.dolisirh.pwa.showMsg = function(text, type) {
	var msgEl = document.getElementById('pwa-status-msg');
	if (!msgEl) {
		return;
	}
	if (window.dolisirh.pwa.msgTimer) {
		clearTimeout(window.dolisirh.pwa.msgTimer);
	}
	if (!text) {
		msgEl.hidden = true;
		return;
	}
	msgEl.textContent = text;
	msgEl.className = 'ts-msg is-' + (type || 'info');
	msgEl.hidden = false;
	if (type === 'ok') {
		window.dolisirh.pwa.msgTimer = setTimeout(function() {
			msgEl.hidden = true;
		}, 3500);
	}
};

/**
 * Get the current CSRF token on page.
 *
 * @returns {string}
 */
window.dolisirh.pwa.getToken = function() {
	if (window.saturne && window.saturne.toolbox && typeof window.saturne.toolbox.getToken === 'function') {
		var t = window.saturne.toolbox.getToken();
		if (t) {
			return t;
		}
	}
	var el = document.getElementById('dolisirh-pwa-token');
	return el ? el.value : '';
};

/**
 * Fetch fresh state from backend for a given date/month.
 *
 * @param {string} selectedDate YYYY-MM-DD
 * @param {string} calMonth YYYY-MM
 * @returns {Promise<void>}
 */
window.dolisirh.pwa.fetchState = function(selectedDate, calMonth) {
	var appEl = document.getElementById('dolisirh-pwa-app');
	var baseUrl = appEl ? appEl.getAttribute('data-ajax-url') : window.location.pathname;
	var url = baseUrl + '?action=pwa_get_state&selected_date=' + encodeURIComponent(selectedDate || '') + '&cal_month=' + encodeURIComponent(calMonth || '') + '&token=' + encodeURIComponent(window.dolisirh.pwa.getToken());

	return fetch(url, { credentials: 'same-origin' })
		.then(function(r) { return r.json(); })
		.then(function(res) {
			if (res && res.success && res.state) {
				window.dolisirh.pwa.state = res.state;
				window.dolisirh.pwa.renderAll();
			}
		})
		.catch(function(err) {
			window.dolisirh.pwa.showMsg(err.message || 'Erreur réseau', 'err');
		});
};

/**
 * Bind UI events.
 *
 * @returns {void}
 */
window.dolisirh.pwa.bindEvents = function() {
	var pwa = window.dolisirh.pwa;

	// Mode tabs
	['project', 'week', 'monthly'].forEach(function(modeKey) {
		var btn = document.getElementById('pwa-mode-' + modeKey);
		if (btn) {
			btn.addEventListener('click', function() {
				pwa.setMode(modeKey);
			});
		}
	});

	// Week prev / next
	var weekPrev = document.getElementById('pwa-week-prev');
	var weekNext = document.getElementById('pwa-week-next');
	if (weekPrev) {
		weekPrev.addEventListener('click', function() {
			if (!pwa.state) return;
			var nextDate = pwa.addDays(pwa.state.selected_date, -7);
			pwa.fetchState(nextDate, nextDate.slice(0, 7));
		});
	}
	if (weekNext) {
		weekNext.addEventListener('click', function() {
			if (!pwa.state) return;
			var nextDate = pwa.addDays(pwa.state.selected_date, 7);
			pwa.fetchState(nextDate, nextDate.slice(0, 7));
		});
	}

	// Monthly calendar prev / next
	var calPrev = document.getElementById('pwa-cal-prev');
	var calNext = document.getElementById('pwa-cal-next');
	if (calPrev) {
		calPrev.addEventListener('click', function() {
			if (!pwa.state || !pwa.state.cal_month) return;
			var nextYm = pwa.addMonths(pwa.state.cal_month.ym, -1);
			pwa.fetchState(pwa.state.selected_date, nextYm);
		});
	}
	if (calNext) {
		calNext.addEventListener('click', function() {
			if (!pwa.state || !pwa.state.cal_month) return;
			var nextYm = pwa.addMonths(pwa.state.cal_month.ym, 1);
			pwa.fetchState(pwa.state.selected_date, nextYm);
		});
	}

	// Project combobox
	var projSearch = document.getElementById('pwa-proj-search');
	var projToggle = document.getElementById('pwa-proj-toggle');
	var projList = document.getElementById('pwa-proj-list');
	if (projSearch) {
		projSearch.addEventListener('focus', function() {
			projSearch.select();
			pwa.renderProjectDropdown(projSearch.value);
			if (projList) projList.hidden = false;
		});
		projSearch.addEventListener('input', function() {
			pwa.selectedProject = null;
			pwa.selectedTask = null;
			pwa.projectTasks = [];
			pwa.renderProjectStats();
			pwa.renderTaskFieldState();
			pwa.updateClockButtons();
			pwa.renderProjectDropdown(projSearch.value);
			if (projList) projList.hidden = false;
		});
	}
	if (projToggle) {
		projToggle.addEventListener('click', function(e) {
			e.stopPropagation();
			if (!projList) return;
			if (projList.hidden) {
				pwa.renderProjectDropdown('');
				projList.hidden = false;
				if (projSearch) projSearch.focus();
			} else {
				projList.hidden = true;
			}
		});
	}

	// Task combobox
	var taskSearch = document.getElementById('pwa-task-search');
	var taskToggle = document.getElementById('pwa-task-toggle');
	var taskList = document.getElementById('pwa-task-list');
	if (taskSearch) {
		taskSearch.addEventListener('focus', function() {
			taskSearch.select();
			pwa.renderTaskDropdown(taskSearch.value);
			if (taskList) taskList.hidden = false;
		});
		taskSearch.addEventListener('input', function() {
			pwa.selectedTask = null;
			pwa.renderTaskStats();
			pwa.updateClockButtons();
			pwa.renderTaskDropdown(taskSearch.value);
			if (taskList) taskList.hidden = false;
		});
	}
	if (taskToggle) {
		taskToggle.addEventListener('click', function(e) {
			e.stopPropagation();
			if (!taskList) return;
			if (taskList.hidden) {
				pwa.renderTaskDropdown('');
				taskList.hidden = false;
				if (taskSearch) taskSearch.focus();
			} else {
				taskList.hidden = true;
			}
		});
	}

	// Close combobox lists on outside click
	document.addEventListener('click', function(e) {
		var projBox = document.getElementById('pwa-proj-combobox');
		var taskBox = document.getElementById('pwa-task-combobox');
		if (projList && projBox && !projBox.contains(e.target)) {
			projList.hidden = true;
		}
		if (taskList && taskBox && !taskBox.contains(e.target)) {
			taskList.hidden = true;
		}
	});

	// Create default SPJ_<ProjectTitle> task button
	var createTaskBtn = document.getElementById('pwa-btn-create-default-task');
	if (createTaskBtn) {
		createTaskBtn.addEventListener('click', function() {
			pwa.createDefaultProjectTask();
		});
	}

	pwa.bindClockEvents();
	pwa.bindConfirmEvents();
};

/**
 * Bind the Pointer tab: task and start time to start, end time, durations, comment and medias to stop.
 *
 * @returns {void}
 */
window.dolisirh.pwa.bindClockEvents = function() {
	var pwa = window.dolisirh.pwa;
	var projDate = document.getElementById('pwa-proj-date');
	var projStart = document.getElementById('pwa-proj-start');
	var projEnd = document.getElementById('pwa-proj-end');
	var projNote = document.getElementById('pwa-proj-note');
	var startBtn = document.getElementById('pwa-btn-start');
	var saveBtn = document.getElementById('pwa-btn-save-proj');
	var backBtn = document.getElementById('pwa-btn-stop-back');
	var runningList = document.getElementById('pwa-running-list');

	if (projDate) {
		projDate.addEventListener('change', function() {
			if (projDate.value && pwa.state && projDate.value !== pwa.state.selected_date) {
				pwa.fetchState(projDate.value, projDate.value.slice(0, 7));
			}
			pwa.updateClockButtons();
		});
	}
	if (projStart) {
		projStart.addEventListener('input', function() {
			pwa.startTouched = true;
			pwa.updateClockButtons();
		});
	}
	if (projEnd) {
		projEnd.addEventListener('input', function() {
			pwa.endTouched = true;
			pwa.syncStop();
		});
	}
	if (projNote) {
		projNote.addEventListener('input', function() {
			pwa.saveDraft();
		});
	}
	if (startBtn) {
		startBtn.addEventListener('click', function() {
			pwa.startClock();
		});
	}
	if (saveBtn) {
		saveBtn.addEventListener('click', function() {
			pwa.stopClock();
		});
	}
	if (backBtn) {
		backBtn.addEventListener('click', function() {
			pwa.closeStop();
		});
	}

	// The running cards are drawn again on every tick: their buttons are listened to from the list
	if (runningList) {
		runningList.addEventListener('click', function(e) {
			var stopBtn = e.target.closest('.ts-running__stop');
			var cancelBtn = e.target.closest('.ts-running__cancel');
			if (stopBtn) {
				var entry = pwa.findRunning(parseInt(stopBtn.getAttribute('data-task-id'), 10));
				if (entry) {
					pwa.openStop(entry);
				}
			} else if (cancelBtn) {
				pwa.cancelRunning(parseInt(cancelBtn.getAttribute('data-task-id'), 10));
			}
		});
	}

	document.querySelectorAll('.ts-clock__chips .ts-chip').forEach(function(chip) {
		chip.addEventListener('click', function() {
			pwa.applyDuration(parseInt(chip.getAttribute('data-duration'), 10));
		});
	});
};

/**
 * Put the default start time: now on today, otherwise where the last entry of the same kind ended or
 * in the morning.
 *
 * @returns {void}
 */
window.dolisirh.pwa.resetStart = function() {
	var pwa = window.dolisirh.pwa;
	var startEl = document.getElementById('pwa-proj-start');
	if (!startEl || !pwa.state) {
		return;
	}

	pwa.startTouched = false;
	pwa.clockDate = pwa.state.selected_date;

	if (pwa.isTodaySelected()) {
		startEl.value = pwa.nowHM();
	} else {
		var lastEnd = pwa.lastEndOfDay(pwa.isHrSelected());
		startEl.value = pwa.minToHM(lastEnd >= 0 ? lastEnd : 8 * 60);
	}

	pwa.updateClockButtons();
};

/**
 * Whether the selected day is today on the device.
 *
 * @returns {boolean}
 */
window.dolisirh.pwa.isTodaySelected = function() {
	var pwa = window.dolisirh.pwa;
	return Boolean(pwa.state && pwa.state.selected_date === pwa.localToday());
};

/**
 * Entries of the selected day of one kind, with clock times.
 * HR presence spans the day while project tasks happen within it: each kind follows only its own.
 *
 * @param {boolean} isHr Whether the HR presence entries are wanted
 * @returns {Array}
 */
window.dolisirh.pwa.sameKindEntries = function(isHr) {
	var pwa = window.dolisirh.pwa;
	var day = pwa.state ? pwa.state.selected_day : null;
	return (day && Array.isArray(day.entries) ? day.entries : []).filter(function(entry) {
		return Boolean(entry.is_hr) === isHr && pwa.hmToMin(entry.start_hm) >= 0;
	});
};

/**
 * End of the latest entry of one kind on the selected day.
 *
 * @param {boolean} isHr Whether the HR presence entries are read
 * @returns {number} Minutes since midnight, -1 when no entry has clock times
 */
window.dolisirh.pwa.lastEndOfDay = function(isHr) {
	var pwa = window.dolisirh.pwa;
	var lastEnd = -1;
	pwa.sameKindEntries(isHr).forEach(function(entry) {
		var s = pwa.hmToMin(entry.start_hm);
		var e = pwa.hmToMin(entry.end_hm);
		if (e > s && e > lastEnd) {
			lastEnd = e;
		}
	});
	return lastEnd;
};

/**
 * Running time entry of a task.
 *
 * @param {number} taskId Task ID
 * @returns {Object|null}
 */
window.dolisirh.pwa.findRunning = function(taskId) {
	var pwa = window.dolisirh.pwa;
	var running = (pwa.state && Array.isArray(pwa.state.running)) ? pwa.state.running : [];
	return running.find(function(entry) { return entry.task_id === taskId; }) || null;
};

/**
 * Minutes elapsed since a running entry started, on the clock of the device.
 *
 * @param {Object} entry Running entry (date, start_hm)
 * @returns {number}
 */
window.dolisirh.pwa.elapsedMinutes = function(entry) {
	var day = String(entry.date).split('-');
	var time = String(entry.start_hm).split(':');
	var start = new Date(parseInt(day[0], 10), parseInt(day[1], 10) - 1, parseInt(day[2], 10), parseInt(time[0], 10), parseInt(time[1], 10), 0);
	return Math.floor((Date.now() - start.getTime()) / 60000);
};

/**
 * Whether the end of a running entry follows the clock: it started less than a day ago. An older one was
 * forgotten, its end is typed.
 *
 * @param {Object} entry Running entry
 * @returns {boolean}
 */
window.dolisirh.pwa.followsClock = function(entry) {
	var elapsed = window.dolisirh.pwa.elapsedMinutes(entry);
	return elapsed >= 0 && elapsed < 1440;
};

/**
 * Day of a YYYY-MM-DD date, as DD/MM.
 *
 * @param {string} dateStr YYYY-MM-DD
 * @returns {string}
 */
window.dolisirh.pwa.shortDay = function(dateStr) {
	var parts = String(dateStr).split('-');
	return parts.length === 3 ? parts[2] + '/' + parts[1] : dateStr;
};

/**
 * Apply a duration to the entry being stopped: the start stays, the end moves to start + duration.
 *
 * @param {number} minutes Duration in minutes
 * @returns {void}
 */
window.dolisirh.pwa.applyDuration = function(minutes) {
	var pwa = window.dolisirh.pwa;
	var endEl = document.getElementById('pwa-proj-end');
	var startMin = pwa.stopping ? pwa.hmToMin(pwa.stopping.start_hm) : -1;
	if (!endEl || startMin < 0 || isNaN(minutes) || minutes <= 0) {
		return;
	}

	endEl.value = pwa.minToHM(startMin + minutes);
	pwa.endTouched = true;
	pwa.syncStop();
};

/**
 * Duration of the entry being stopped, an end before the start meaning the next day.
 *
 * @returns {number} Minutes, -1 when the end is missing
 */
window.dolisirh.pwa.clockMinutes = function() {
	var pwa = window.dolisirh.pwa;
	var endEl = document.getElementById('pwa-proj-end');
	var startMin = pwa.stopping ? pwa.hmToMin(pwa.stopping.start_hm) : -1;
	var endMin = endEl ? pwa.hmToMin(endEl.value) : -1;
	if (startMin < 0 || endMin < 0) {
		return -1;
	}
	return (endMin - startMin + 1440) % 1440;
};

/**
 * Follow the clock: the untouched start of a new entry, the untouched end of the entry being stopped,
 * the time elapsed on the running cards.
 *
 * @returns {void}
 */
window.dolisirh.pwa.tickClock = function() {
	var pwa = window.dolisirh.pwa;
	var startEl = document.getElementById('pwa-proj-start');
	var endEl = document.getElementById('pwa-proj-end');
	var nowValue = pwa.nowHM();

	if (startEl && !pwa.startTouched && pwa.isTodaySelected() && startEl.value !== nowValue) {
		startEl.value = nowValue;
	}
	if (endEl && pwa.stopping && !pwa.endTouched && pwa.followsClock(pwa.stopping) && endEl.value !== nowValue) {
		endEl.value = nowValue;
		pwa.syncStop();
	}
	pwa.renderRunning();
};

/**
 * Render the cards of the running time entries and lay the Pointer tab out around them: while an entry
 * runs, its card takes the place of the start panel, and the stop panel replaces it while it is being stopped.
 *
 * @returns {void}
 */
window.dolisirh.pwa.renderRunning = function() {
	var pwa = window.dolisirh.pwa;
	var box = document.getElementById('pwa-running');
	var list = document.getElementById('pwa-running-list');
	if (!box || !list || !pwa.state) {
		return;
	}

	var running = Array.isArray(pwa.state.running) ? pwa.state.running : [];
	var isRunning = running.length > 0;
	// The recap of the day stays under the card and the stop panel
	var panels = {
		'pwa-start-panel': isRunning || pwa.stopping !== null,
		'pwa-stop-panel': pwa.stopping === null,
	};
	Object.keys(panels).forEach(function(id) {
		var el = document.getElementById(id);
		if (el) {
			el.hidden = panels[id];
		}
	});
	// The days only choose where a new entry goes and which entries are listed
	var weekBar = document.getElementById('pwa-week-bar');
	if (weekBar && pwa.mode === 'project') {
		weekBar.hidden = isRunning;
	}

	box.hidden = !isRunning || pwa.stopping !== null;
	list.innerHTML = running.map(function(entry) {
		var since = entry.date === pwa.localToday() ? entry.start_hm : pwa.shortDay(entry.date) + ' ' + entry.start_hm;
		return '<div class="ts-running__item' + (entry.is_hr ? ' is-hr' : '') + '">' +
			'<span class="ts-clock__pulse"></span>' +
			'<div class="ts-running__body">' +
				'<span class="ts-running__task">' + pwa.esc(entry.task_label || entry.task_ref) + '</span>' +
				'<span class="ts-running__meta">' + pwa.esc((entry.is_hr ? pwa.label('presence') : (entry.project_title || entry.project_ref)) + ' · ' + pwa.label('since', since)) + '</span>' +
			'</div>' +
			'<b class="ts-running__elapsed">' + pwa.esc(pwa.durationLabel(Math.max(0, pwa.elapsedMinutes(entry)))) + '</b>' +
			'<div class="ts-running__actions">' +
				'<button type="button" class="ts-running__cancel" data-task-id="' + entry.task_id + '" title="' + pwa.esc(pwa.label('cancel')) + '"><i class="far fa-trash-alt"></i></button>' +
				'<button type="button" class="ts-running__stop" data-task-id="' + entry.task_id + '"><i class="fas fa-stop"></i> ' + pwa.esc(pwa.label('stop')) + '</button>' +
			'</div>' +
		'</div>';
	}).join('');
};

/**
 * Open the stop panel of a running entry: end time, comment and medias.
 *
 * @param {Object} entry Running entry
 * @returns {void}
 */
window.dolisirh.pwa.openStop = function(entry) {
	var pwa = window.dolisirh.pwa;
	var stopPanel = document.getElementById('pwa-stop-panel');
	var taskEl = document.getElementById('pwa-stop-task');
	var startEl = document.getElementById('pwa-stop-start');
	var startDayEl = document.getElementById('pwa-stop-start-day');
	if (!stopPanel) {
		return;
	}

	pwa.stopping = entry;
	if (taskEl) {
		taskEl.textContent = (entry.task_label || entry.task_ref) + ' · ' + (entry.is_hr ? pwa.label('presence') : (entry.project_title || entry.project_ref));
	}
	if (startEl) {
		startEl.textContent = entry.start_hm;
	}
	if (startDayEl) {
		startDayEl.hidden = entry.date === pwa.localToday();
		startDayEl.textContent = pwa.label('startedOn', pwa.shortDay(entry.date));
	}

	pwa.resetEnd();
	pwa.renderRunning();
	pwa.syncStop();
	stopPanel.scrollIntoView({ block: 'start', behavior: 'smooth' });
};

/**
 * Close the stop panel: back to the running card when the entry goes on, to the start panel once stopped.
 *
 * @returns {void}
 */
window.dolisirh.pwa.closeStop = function() {
	var pwa = window.dolisirh.pwa;

	pwa.stopping = null;
	pwa.renderRunning();
	pwa.updateClockButtons();
	pwa.saveDraft();
};

/**
 * Put the default end time: now while the entry started less than a day ago, to be typed otherwise.
 *
 * @returns {void}
 */
window.dolisirh.pwa.resetEnd = function() {
	var pwa = window.dolisirh.pwa;
	var endEl = document.getElementById('pwa-proj-end');
	if (!endEl || !pwa.stopping) {
		return;
	}
	pwa.endTouched = false;
	endEl.value = pwa.followsClock(pwa.stopping) ? pwa.nowHM() : '';
};

/**
 * Refresh the duration of the entry being stopped, its hints and the validate button.
 *
 * @returns {void}
 */
window.dolisirh.pwa.syncStop = function() {
	var pwa = window.dolisirh.pwa;
	var durEl = document.getElementById('pwa-stop-duration');
	var overnightEl = document.getElementById('pwa-proj-overnight');
	var endEl = document.getElementById('pwa-proj-end');
	var minutes = pwa.clockMinutes();
	var startMin = pwa.stopping ? pwa.hmToMin(pwa.stopping.start_hm) : -1;
	var endMin = endEl ? pwa.hmToMin(endEl.value) : -1;

	if (durEl) {
		durEl.textContent = minutes > 0 ? pwa.durationLabel(minutes) : '-';
	}
	if (overnightEl) {
		overnightEl.hidden = !(startMin >= 0 && endMin >= 0 && endMin < startMin);
	}
	document.querySelectorAll('.ts-clock__chips .ts-chip').forEach(function(chip) {
		chip.classList.toggle('is-active', pwa.endTouched && parseInt(chip.getAttribute('data-duration'), 10) === minutes);
	});

	pwa.renderOverlap();
	pwa.updateClockButtons();
	pwa.saveDraft();
};

/**
 * Warn when the entry being stopped overlaps an entry of the same kind already saved on its day.
 *
 * @returns {void}
 */
window.dolisirh.pwa.renderOverlap = function() {
	var pwa = window.dolisirh.pwa;
	var el = document.getElementById('pwa-clock-overlap');
	var endEl = document.getElementById('pwa-proj-end');
	if (!el || !endEl || !pwa.state) {
		return;
	}

	var entry = pwa.stopping;
	// The saved entries known are the ones of the selected day
	var overlapping = (entry && entry.date === pwa.state.selected_date) ? pwa.findOverlap(pwa.hmToMin(entry.start_hm), pwa.hmToMin(endEl.value), Boolean(entry.is_hr)) : null;
	el.hidden = !overlapping;
	if (overlapping) {
		el.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + pwa.esc(pwa.label('overlap', overlapping.start_hm + ' - ' + overlapping.end_hm + ' · ' + overlapping.task_label));
	}
};

/**
 * First entry of one kind on the selected day whose clock times overlap the given range.
 *
 * @param {number} startMin Start in minutes since midnight
 * @param {number} endMin End in minutes since midnight
 * @param {boolean} isHr Whether the HR presence entries are compared
 * @returns {Object|null}
 */
window.dolisirh.pwa.findOverlap = function(startMin, endMin, isHr) {
	var pwa = window.dolisirh.pwa;
	if (startMin < 0 || endMin <= startMin) {
		return null;
	}
	return pwa.sameKindEntries(isHr).find(function(entry) {
		var s = pwa.hmToMin(entry.start_hm);
		var e = pwa.hmToMin(entry.end_hm);
		return e > s && startMin < e && s < endMin;
	}) || null;
};

/**
 * Value of a field, or an empty string when the field is missing or not kept.
 *
 * @param {string} id Field id
 * @param {boolean} [keep] Whether the value is kept
 * @returns {string}
 */
window.dolisirh.pwa.fieldValue = function(id, keep) {
	var el = document.getElementById(id);
	return (el && keep !== false) ? el.value : '';
};

/**
 * Keep the form in the browser, so a closed app finds its task, the entry being stopped and the comment.
 * The running entries themselves are kept on the server.
 *
 * @returns {void}
 */
window.dolisirh.pwa.saveDraft = function() {
	var pwa = window.dolisirh.pwa;
	if (!pwa.state || pwa.restoringDraft) {
		return;
	}
	var project = pwa.selectedProject;
	var draft = {
		project: project ? { id: project.id, ref: project.ref, title: project.title, url: project.url || '' } : null,
		task_id: pwa.selectedTask ? pwa.selectedTask.id : 0,
		stop_task_id: pwa.stopping ? pwa.stopping.task_id : 0,
		end: pwa.fieldValue('pwa-proj-end', Boolean(pwa.stopping) && pwa.endTouched),
		note: pwa.fieldValue('pwa-proj-note'),
	};
	var isEmpty = !draft.project && !draft.stop_task_id && !draft.note;
	pwa.storageSet(pwa.draftKey, isEmpty ? null : JSON.stringify(draft));
};

/**
 * Form kept in the browser, if any.
 *
 * @returns {Object|null}
 */
window.dolisirh.pwa.readDraft = function() {
	var raw = window.dolisirh.pwa.storageGet(window.dolisirh.pwa.draftKey);
	try {
		return raw ? JSON.parse(raw) : null;
	} catch (e) {
		return null;
	}
};

/**
 * Restore the form kept in the browser: task, comment, and the stop panel of an entry still running.
 *
 * @returns {void}
 */
window.dolisirh.pwa.restoreDraft = function() {
	var pwa = window.dolisirh.pwa;
	var draft = pwa.readDraft();
	if (!draft || !pwa.state) {
		pwa.restoringDraft = false;
		return;
	}

	pwa.restoringDraft = true;
	var noteEl = document.getElementById('pwa-proj-note');
	if (noteEl && draft.note) {
		noteEl.value = draft.note;
	}
	var stopEntry = draft.stop_task_id ? pwa.findRunning(draft.stop_task_id) : null;
	if (stopEntry) {
		pwa.openStop(stopEntry);
		var endEl = document.getElementById('pwa-proj-end');
		if (endEl && draft.end) {
			endEl.value = draft.end;
			pwa.endTouched = true;
		}
	}
	pwa.restoringDraft = false;

	if (draft.project && draft.project.id) {
		var project = (pwa.state.projects || []).find(function(p) { return p.id === draft.project.id; }) || pwa.fallbackProject(draft.project);
		pwa.selectProject(project, draft.task_id);
	}
	if (pwa.stopping) {
		pwa.syncStop();
	}
};

/**
 * Project object for a project that is not in the list of open projects (the HR project).
 *
 * @param {Object} data Project id, ref, title and url
 * @returns {Object}
 */
window.dolisirh.pwa.fallbackProject = function(data) {
	return {
		id: data.id,
		ref: data.ref || '',
		title: data.title || '',
		planned_workload: 0,
		duration_effective: 0,
		planned_hm: '-',
		spent_hm: '-',
		url: data.url || '',
	};
};

/**
 * Render the one-tap task buttons: HR presence and the latest tasks of the user.
 *
 * @returns {void}
 */
window.dolisirh.pwa.renderQuickTasks = function() {
	var pwa = window.dolisirh.pwa;
	var field = document.getElementById('pwa-quick-field');
	var box = document.getElementById('pwa-quick-tasks');
	if (!field || !box || !pwa.state) {
		return;
	}

	var tasks = Array.isArray(pwa.state.quick_tasks) ? pwa.state.quick_tasks : [];
	field.hidden = tasks.length === 0;
	box.innerHTML = tasks.map(function(t) {
		var isActive = pwa.selectedTask && pwa.selectedTask.id === t.id;
		return '<button type="button" class="ts-quick-task' + (isActive ? ' is-active' : '') + (t.is_hr ? ' is-hr' : '') + '" data-task-id="' + t.id + '" title="' + pwa.esc(t.project_ref + ' - ' + t.project_title + ' / ' + t.ref + ' - ' + t.label) + '">' +
			'<span class="ts-quick-task__label">' + pwa.esc(t.label) + '</span>' +
			'<span class="ts-quick-task__project">' + pwa.esc(t.is_hr ? pwa.label('presence') : (t.project_title || t.project_ref)) + '</span>' +
		'</button>';
	}).join('');

	box.querySelectorAll('.ts-quick-task').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var taskId = parseInt(btn.getAttribute('data-task-id'), 10);
			var task = tasks.find(function(t) { return t.id === taskId; });
			if (!task) {
				return;
			}
			var project = (pwa.state.projects || []).find(function(p) { return p.id === task.project_id; }) ||
				pwa.fallbackProject({ id: task.project_id, ref: task.project_ref, title: task.project_title, url: task.project_url });
			pwa.selectProject(project, task.id);
		});
	});
};

/**
 * Whether the selected project is the HR project.
 *
 * @returns {boolean}
 */
window.dolisirh.pwa.isHrSelected = function() {
	var pwa = window.dolisirh.pwa;
	return Boolean(pwa.selectedProject && pwa.state && pwa.state.hr_project && pwa.state.hr_project.id === pwa.selectedProject.id);
};

/**
 * Switch active mode ('project' | 'week' | 'monthly').
 *
 * @param {string} nextMode Mode name
 * @returns {void}
 */
window.dolisirh.pwa.setMode = function(nextMode) {
	var pwa = window.dolisirh.pwa;
	pwa.mode = nextMode;
	pwa.storageSet('dolisirh_pwa_mode', nextMode);

	['project', 'week', 'monthly'].forEach(function(m) {
		var btn = document.getElementById('pwa-mode-' + m);
		var panel = document.getElementById('pwa-view-' + m);
		var isActive = (m === nextMode);
		if (btn) {
			btn.classList.toggle('is-active', isActive);
			btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
		}
		if (panel) {
			panel.hidden = !isActive;
		}
	});

	var hrTopBars = document.getElementById('pwa-hr-top-bars');
	var weekBar = document.getElementById('pwa-week-bar');
	if (hrTopBars) {
		hrTopBars.hidden = (nextMode !== 'week');
	}
	if (weekBar) {
		weekBar.hidden = (nextMode === 'monthly');
	}

	pwa.renderAll();
};

/**
 * Render all sections according to current state and mode.
 *
 * @returns {void}
 */
window.dolisirh.pwa.renderAll = function() {
	var pwa = window.dolisirh.pwa;
	if (!pwa.state) {
		return;
	}

	// Ensure tab visibility matches pwa.mode
	['project', 'week', 'monthly'].forEach(function(m) {
		var btn = document.getElementById('pwa-mode-' + m);
		var panel = document.getElementById('pwa-view-' + m);
		var isActive = (m === pwa.mode);
		if (btn) {
			btn.classList.toggle('is-active', isActive);
			btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
		}
		if (panel) {
			panel.hidden = !isActive;
		}
	});

	var hrTopBars = document.getElementById('pwa-hr-top-bars');
	var weekBar = document.getElementById('pwa-week-bar');
	if (hrTopBars) {
		hrTopBars.hidden = (pwa.mode !== 'week');
	}
	if (weekBar) {
		weekBar.hidden = (pwa.mode === 'monthly');
	}

	var projDateInput = document.getElementById('pwa-proj-date');
	if (projDateInput && pwa.state.selected_date) {
		projDateInput.value = pwa.state.selected_date;
	}

	pwa.renderHrTopBars();
	pwa.renderWeekBar();
	pwa.renderHrTaskCards();
	pwa.renderQuickTasks();
	pwa.renderProjectHistory();
	pwa.renderMonthlyCalendar();

	// Another day: the start time starts again from that day's entries
	if (pwa.clockDate !== pwa.state.selected_date) {
		pwa.resetStart();
	}

	// An entry stopped elsewhere (another device, another tab) closes its stop panel
	if (pwa.stopping) {
		var stillRunning = pwa.findRunning(pwa.stopping.task_id);
		if (stillRunning) {
			pwa.stopping = stillRunning;
		} else {
			pwa.closeStop();
		}
	}
	pwa.renderRunning();
	if (pwa.stopping) {
		pwa.syncStop();
	} else {
		pwa.updateClockButtons();
	}
};

/**
 * Render the HR project link banner and monthly summary counter.
 *
 * @returns {void}
 */
window.dolisirh.pwa.renderHrTopBars = function() {
	var pwa = window.dolisirh.pwa;
	var st = pwa.state;
	if (!st) return;

	var projBar = document.getElementById('pwa-hr-project-bar');
	if (projBar) {
		if (st.hr_project) {
			projBar.innerHTML = '<i class="fas fa-book-open ts-hr-project-icon"></i> ' +
				'<a href="' + pwa.esc(st.hr_project.url) + '" target="_blank" class="ts-hr-project-link">' +
				pwa.esc(st.hr_project.ref + ' - ' + st.hr_project.title) +
				'</a>';
		} else {
			projBar.innerHTML = '<span class="ts-muted">Aucun projet RH configuré</span>';
		}
	}

	var monthLabel = document.getElementById('pwa-hr-month-label');
	var monthValues = document.getElementById('pwa-hr-month-values');
	if (monthLabel && st.sel_month) {
		monthLabel.textContent = 'Prévues ' + st.sel_month.label;
	}
	if (monthValues && st.sel_month) {
		monthValues.textContent = st.sel_month.logged_hr_hm + ' / ' + st.sel_month.expected_hm;
		monthValues.classList.toggle('is-complete', st.sel_month.expected_sec > 0 && st.sel_month.logged_hr_sec >= st.sel_month.expected_sec);
	}
};

/**
 * Render the 5-day week strip (Monday to Friday) for Semaine and Projet modes.
 *
 * @returns {void}
 */
window.dolisirh.pwa.renderWeekBar = function() {
	var pwa = window.dolisirh.pwa;
	var st = pwa.state;
	var container = document.getElementById('pwa-week-days');
	if (!container || !st || !Array.isArray(st.week_days)) {
		return;
	}

	var isProjectMode = (pwa.mode === 'project');
	var todayStr = st.today;
	var selectedDateStr = st.selected_date;

	container.innerHTML = st.week_days.map(function(day) {
		var expectedSec = Number(day.expected_sec) || 0;
		var presSec = Number(day.pres_sec) || 0;
		var absSec = Number(day.abs_sec) || 0;
		var projSec = Number(day.proj_sec) || 0;
		var totalDaySec = isProjectMode ? projSec : (presSec + absSec);

		var stateClass = 'cal-day-grey';
		if (isProjectMode) {
			if (expectedSec > 0 && projSec >= expectedSec) {
				stateClass = 'cal-day-ok';
			} else if (day.date <= todayStr && expectedSec > 0 && projSec < expectedSec) {
				stateClass = 'cal-day-ko';
			}
		} else {
			if ((expectedSec > 0 && totalDaySec === expectedSec) || (expectedSec === 0 && totalDaySec > 0)) {
				stateClass = 'cal-day-ok';
			} else if (expectedSec > 0 && totalDaySec > expectedSec) {
				stateClass = 'cal-day-over';
			} else if ((day.date <= todayStr && expectedSec > 0 && totalDaySec !== expectedSec) || (day.date > todayStr && totalDaySec > 0 && totalDaySec !== expectedSec)) {
				stateClass = 'cal-day-ko';
			}
		}

		var paText = '-';
		var paClass = 'is-none';
		if (isProjectMode) {
			if (projSec > 0) {
				paText = 'P';
				paClass = 'is-p';
			}
		} else {
			if (presSec > 0 && absSec > 0) {
				paText = 'P/A';
				paClass = 'is-pa';
			} else if (presSec > 0) {
				paText = 'P';
				paClass = 'is-p';
			} else if (absSec > 0) {
				paText = 'A';
				paClass = 'is-a';
			}
		}

		var durStr;
		if (isProjectMode) {
			durStr = projSec > 0 ? pwa.secToHM(projSec) : '00:00';
		} else {
			durStr = (stateClass === 'cal-day-grey')
				? (expectedSec > 0 ? pwa.secToHM(expectedSec) : '00:00')
				: (totalDaySec > 0 ? pwa.secToHM(totalDaySec) : '00:00');
		}

		var isSelectedClass = (day.date === selectedDateStr) ? ' is-today' : '';

		return '<div class="ts-cal-day ts-week-day ' + stateClass + isSelectedClass + '" data-date="' + pwa.esc(day.date) + '">' +
			'<div class="ts-cal-day-top">' +
				'<span class="ts-cal-day-name">' + pwa.esc(day.day_name) + '</span>' +
				'<span class="ts-cal-pa-badge ' + paClass + '">' + pwa.esc(paText) + '</span>' +
			'</div>' +
			'<span class="ts-cal-day-num">' + pwa.esc(day.dd) + '</span>' +
			'<span class="ts-cal-day-month">' + pwa.esc(day.mm_yyyy) + '</span>' +
			'<span class="ts-cal-day-dur">' + pwa.esc(durStr) + '</span>' +
		'</div>';
	}).join('');

	container.querySelectorAll('.ts-week-day').forEach(function(card) {
		card.addEventListener('click', function() {
			var dateStr = card.getAttribute('data-date');
			if (dateStr) {
				pwa.fetchState(dateStr, dateStr.slice(0, 7));
			}
		});
	});
};

/**
 * Render the Présence & Absence HR task sections in the Semaine tab.
 *
 * @returns {void}
 */
window.dolisirh.pwa.renderHrTaskCards = function() {
	var pwa = window.dolisirh.pwa;
	var st = pwa.state;
	var container = document.getElementById('pwa-hr-task-sections');
	if (!container || !st) {
		return;
	}

	var dayData = st.selected_day || {
		expected_sec: 0,
		pres_sec: 0,
		abs_sec: 0,
		hr_entries: {},
	};

	var expectedSec = Number(dayData.expected_sec) || 0;
	var presSec = Number(dayData.pres_sec) || 0;
	var absSec = Number(dayData.abs_sec) || 0;
	var totalLoggedSec = presSec + absSec;
	var remainingDaySec = Math.max(0, expectedSec - totalLoggedSec);

	var expectedPresSec = Math.max(0, expectedSec - absSec);
	var diffPresSec = Math.max(0, expectedPresSec - presSec);
	var isPresOk = (diffPresSec === 0);

	var expectedAbsSec = (presSec === 0 && absSec > 0) ? expectedSec : 0;
	var diffAbsSec = Math.max(0, expectedAbsSec - absSec);
	var isAbsOk = (diffAbsSec === 0);

	var presTasks = (st.hr_tasks && st.hr_tasks.presence) ? st.hr_tasks.presence : [];
	var absTasks = (st.hr_tasks && st.hr_tasks.absence) ? st.hr_tasks.absence : [];

	var renderCard = function(task, idx, isPresenceSection) {
		var entries = (dayData.hr_entries && dayData.hr_entries[task.id]) ? dayData.hr_entries[task.id] : [];
		var loggedTaskSec = entries.reduce(function(sum, e) { return sum + (Number(e.duration) || 0); }, 0);
		var isDone = (loggedTaskSec > 0);

		var defaultHm = '00:00';
		if (isPresenceSection && idx === 0 && !isDone && remainingDaySec > 0) {
			defaultHm = pwa.secToHM(remainingDaySec);
		} else if (isDone) {
			defaultHm = pwa.secToHM(loggedTaskSec);
		}

		var entriesHtml = '';
		if (entries.length > 0) {
			entriesHtml = '<div class="ts-hr-card__entries">' + entries.map(function(entry) {
				return '<div class="ts-hr-entry">' +
					'<span class="ts-hr-entry__note" title="' + pwa.esc(entry.note || 'Pointage') + '">' + pwa.esc(entry.note || 'Pointage') + '</span>' +
					'<span class="ts-hr-entry__dur">' + pwa.esc(entry.duration_hm) + '</span>' +
					'<button type="button" class="ts-hr-entry__del" data-entry-id="' + entry.id + '" title="Supprimer ce pointage">' +
						'<i class="far fa-trash-alt"></i>' +
					'</button>' +
				'</div>';
			}).join('') + '</div>';
		}

		// Sized for a finger: the task by its label, then the note, the time and the save button on one row
		return '<div class="ts-hr-card' + (isDone ? ' is-done' : '') + '" data-task-id="' + task.id + '">' +
			'<div class="ts-hr-card__main">' +
				'<span class="ts-hr-card__label">' + pwa.esc(task.label) + '</span>' +
				'<textarea class="ts-hr-card__note" rows="2" placeholder="Note / Commentaire..."></textarea>' +
				'<input type="time" class="ts-hr-card__time" value="' + pwa.esc(defaultHm) + '" step="60">' +
				'<button type="button" class="ts-hr-card__btn">' +
					'<i class="far fa-save"></i><span>' + pwa.esc(pwa.label('save')) + '</span>' +
				'</button>' +
			'</div>' +
			entriesHtml +
		'</div>';
	};

	var html = '';

	// Présence section
	html += '<div class="ts-hr-section-header">' +
		'<span class="ts-hr-section-title">Présence</span>' +
		'<span class="ts-hr-section-stats">' +
			'attendue : <b>' + pwa.secToHM(expectedPresSec) + '</b> | ' +
			'Pointé : <b>' + pwa.secToHM(presSec) + '</b> | ' +
			(isPresOk ? 'Etat' : 'Manque') + ' : <b>' + pwa.secToHM(diffPresSec) + '</b> - ' +
			'<b class="' + (isPresOk ? 'is-ok' : 'is-ko') + '">' + (isPresOk ? 'OK' : 'KO') + '</b>' +
		'</span>' +
	'</div>';
	html += presTasks.map(function(t, idx) { return renderCard(t, idx, true); }).join('');

	// Absence section
	html += '<div class="ts-hr-section-header">' +
		'<span class="ts-hr-section-title">Absence</span>' +
		'<span class="ts-hr-section-stats">' +
			'attendue : <b>' + pwa.secToHM(expectedAbsSec) + '</b> | ' +
			'Pointé : <b>' + pwa.secToHM(absSec) + '</b> | ' +
			(isAbsOk ? 'Etat' : 'Manque') + ' : <b>' + pwa.secToHM(diffAbsSec) + '</b> - ' +
			'<b class="' + (isAbsOk ? 'is-ok' : 'is-ko') + '">' + (isAbsOk ? 'OK' : 'KO') + '</b>' +
		'</span>' +
	'</div>';
	html += absTasks.map(function(t, idx) { return renderCard(t, idx, false); }).join('');

	container.innerHTML = html;

	// Bind card events
	container.querySelectorAll('.ts-hr-card').forEach(function(cardEl) {
		var taskId = parseInt(cardEl.getAttribute('data-task-id'), 10);
		var timeInput = cardEl.querySelector('.ts-hr-card__time');
		var noteInput = cardEl.querySelector('.ts-hr-card__note');
		var saveBtn = cardEl.querySelector('.ts-hr-card__btn');

		var syncBtnState = function() {
			var sec = pwa.hmToSec(timeInput ? timeInput.value : '00:00');
			if (saveBtn) {
				saveBtn.disabled = (sec <= 0);
			}
		};
		syncBtnState();

		if (timeInput) {
			timeInput.addEventListener('input', syncBtnState);
		}
		if (saveBtn) {
			saveBtn.addEventListener('click', function() {
				var hmVal = timeInput ? timeInput.value : '00:00';
				var noteVal = noteInput ? noteInput.value.trim() : '';
				if (pwa.hmToSec(hmVal) <= 0) {
					return;
				}
				saveBtn.disabled = true;
				pwa.submitTimeSpent({
					task_id: taskId,
					date: st.selected_date,
					duration_hm: hmVal,
					note: noteVal,
					is_project_mode: false,
					cal_month: st.cal_month ? st.cal_month.ym : '',
				});
			});
		}

		cardEl.querySelectorAll('.ts-hr-entry__del').forEach(function(delBtn) {
			delBtn.addEventListener('click', function() {
				var entryId = parseInt(delBtn.getAttribute('data-entry-id'), 10);
				if (entryId > 0) {
					delBtn.disabled = true;
					pwa.deleteTimeSpent(entryId);
				}
			});
		});
	});
};

/**
 * Render the searchable project dropdown list.
 *
 * @param {string} query Search query
 * @returns {void}
 */
window.dolisirh.pwa.renderProjectDropdown = function(query) {
	var pwa = window.dolisirh.pwa;
	var listEl = document.getElementById('pwa-proj-list');
	if (!listEl || !pwa.state || !Array.isArray(pwa.state.projects)) {
		return;
	}

	var q = String(query || '').trim().toLowerCase();
	var filtered = pwa.state.projects.filter(function(p) {
		if (!q) return true;
		return (p.ref + ' ' + p.title).toLowerCase().indexOf(q) !== -1;
	});

	if (filtered.length === 0) {
		listEl.innerHTML = '<li class="ts-combobox__empty">' + pwa.esc(pwa.label('noProject')) + '</li>';
		return;
	}

	listEl.innerHTML = filtered.slice(0, 60).map(function(p) {
		return '<li class="ts-combobox__item" data-project-id="' + p.id + '">' +
			'<strong>' + pwa.esc(p.ref) + '</strong> - ' + pwa.esc(p.title) +
		'</li>';
	}).join('');

	listEl.querySelectorAll('.ts-combobox__item').forEach(function(li) {
		li.addEventListener('click', function() {
			var pid = parseInt(li.getAttribute('data-project-id'), 10);
			var proj = pwa.state.projects.find(function(p) { return p.id === pid; });
			if (proj) {
				pwa.selectProject(proj);
			}
			listEl.hidden = true;
		});
	});
};

/**
 * Select a project in the Projet tab and load its tasks.
 *
 * @param {Object} project Project object
 * @param {number} [taskIdToSelect] Task selected once the tasks are loaded
 * @returns {void}
 */
window.dolisirh.pwa.selectProject = function(project, taskIdToSelect) {
	var pwa = window.dolisirh.pwa;
	pwa.selectedProject = project;
	pwa.selectedTask = null;
	pwa.projectTasks = [];
	pwa.renderQuickTasks();

	var projSearch = document.getElementById('pwa-proj-search');
	if (projSearch) {
		projSearch.value = project.ref + ' - ' + project.title;
	}

	pwa.renderProjectStats();
	pwa.renderTaskStats();
	pwa.renderTaskFieldState();
	pwa.updateClockButtons();

	var appEl = document.getElementById('dolisirh-pwa-app');
	var baseUrl = appEl ? appEl.getAttribute('data-ajax-url') : window.location.pathname;
	var url = baseUrl + '?action=pwa_get_tasks&project_id=' + encodeURIComponent(project.id) + '&token=' + encodeURIComponent(pwa.getToken());

	fetch(url, { credentials: 'same-origin' })
		.then(function(r) { return r.json(); })
		.then(function(res) {
			if (res && res.success && Array.isArray(res.tasks)) {
				pwa.projectTasks = res.tasks;
				pwa.renderTaskFieldState();
				var wantedTask = taskIdToSelect ? pwa.projectTasks.find(function(t) { return t.id === taskIdToSelect; }) : null;
				if (wantedTask) {
					pwa.selectTask(wantedTask);
				} else if (pwa.projectTasks.length === 1) {
					pwa.selectTask(pwa.projectTasks[0]);
				}
			}
		});
};

/**
 * Render inline project stats (title + planned + spent + link).
 *
 * @returns {void}
 */
window.dolisirh.pwa.renderProjectStats = function() {
	var pwa = window.dolisirh.pwa;
	var el = document.getElementById('pwa-proj-stats');
	if (!el) return;
	var p = pwa.selectedProject;
	if (!p) {
		el.innerHTML = '';
		return;
	}
	var isOver = p.planned_workload > 0 && p.duration_effective > p.planned_workload;
	el.innerHTML = '<span class="ts-inline-stats__title" title="' + pwa.esc(p.title) + '">- ' + pwa.esc(p.title) + '</span> ' +
		'<span class="ts-inline-stats__meta">| &#9203; <b>' + pwa.esc(p.planned_hm) + '</b> | &#9201; <b class="' + (isOver ? 'is-over' : '') + '">' + pwa.esc(p.spent_hm) + '</b> | ' +
		'<a href="' + pwa.esc(p.url) + '" target="_blank" class="ts-ext-link" title="Ouvrir le projet dans Dolibarr">&#8599;</a></span>';
};

/**
 * Update the Task combobox and `+` button state.
 *
 * @returns {void}
 */
window.dolisirh.pwa.renderTaskFieldState = function() {
	var pwa = window.dolisirh.pwa;
	var addBtn = document.getElementById('pwa-btn-create-default-task');
	var taskCombobox = document.getElementById('pwa-task-combobox');
	var taskSearch = document.getElementById('pwa-task-search');

	if (addBtn) {
		addBtn.disabled = !pwa.selectedProject;
	}
	if (taskCombobox) {
		taskCombobox.hidden = (!pwa.selectedProject || pwa.projectTasks.length === 0);
	}
	if (taskSearch && !pwa.selectedTask) {
		taskSearch.value = '';
	}
};

/**
 * Render the searchable task dropdown list.
 *
 * @param {string} query Search query
 * @returns {void}
 */
window.dolisirh.pwa.renderTaskDropdown = function(query) {
	var pwa = window.dolisirh.pwa;
	var listEl = document.getElementById('pwa-task-list');
	if (!listEl) return;

	var q = String(query || '').trim().toLowerCase();
	var filtered = pwa.projectTasks.filter(function(t) {
		if (!q) return true;
		return (t.ref + ' ' + t.label).toLowerCase().indexOf(q) !== -1;
	});

	if (filtered.length === 0) {
		listEl.innerHTML = '<li class="ts-combobox__empty">' + pwa.esc(pwa.label('noTask')) + '</li>';
		return;
	}

	listEl.innerHTML = filtered.map(function(t) {
		return '<li class="ts-combobox__item" data-task-id="' + t.id + '">' +
			'<strong>' + pwa.esc(t.ref) + '</strong> - ' + pwa.esc(t.label) +
		'</li>';
	}).join('');

	listEl.querySelectorAll('.ts-combobox__item').forEach(function(li) {
		li.addEventListener('click', function() {
			var tid = parseInt(li.getAttribute('data-task-id'), 10);
			var task = pwa.projectTasks.find(function(t) { return t.id === tid; });
			if (task) {
				pwa.selectTask(task);
			}
			listEl.hidden = true;
		});
	});
};

/**
 * Select a task in the Projet tab.
 *
 * @param {Object} task Task object
 * @returns {void}
 */
window.dolisirh.pwa.selectTask = function(task) {
	var pwa = window.dolisirh.pwa;
	pwa.selectedTask = task;
	var taskSearch = document.getElementById('pwa-task-search');
	if (taskSearch) {
		taskSearch.value = task.ref + ' - ' + task.label;
	}
	pwa.renderTaskStats();
	pwa.renderQuickTasks();
	// On another day, presence and project tasks start from their own last entry: an untouched start follows the task
	if (!pwa.startTouched && !pwa.restoringDraft) {
		pwa.resetStart();
	}
	pwa.updateClockButtons();
	pwa.saveDraft();
};

/**
 * Render inline task stats (label + planned + spent + link).
 *
 * @returns {void}
 */
window.dolisirh.pwa.renderTaskStats = function() {
	var pwa = window.dolisirh.pwa;
	var el = document.getElementById('pwa-task-stats');
	if (!el) return;
	var t = pwa.selectedTask;
	if (!t) {
		el.innerHTML = '';
		return;
	}
	var isOver = t.planned_workload > 0 && t.duration_effective > t.planned_workload;
	el.innerHTML = '<span class="ts-inline-stats__title" title="' + pwa.esc(t.label) + '">- ' + pwa.esc(t.label) + '</span> ' +
		'<span class="ts-inline-stats__meta">| &#9203; <b>' + pwa.esc(t.planned_hm) + '</b> | &#9201; <b class="' + (isOver ? 'is-over' : '') + '">' + pwa.esc(t.spent_hm) + '</b> | ' +
		'<a href="' + pwa.esc(t.url) + '" target="_blank" class="ts-ext-link" title="Ouvrir la tâche dans Dolibarr">&#8599;</a></span>';
};

/**
 * Create or select the default SPJ_<ProjectTitle> task on the selected project.
 *
 * @returns {void}
 */
window.dolisirh.pwa.createDefaultProjectTask = function() {
	var pwa = window.dolisirh.pwa;
	if (!pwa.selectedProject) return;

	var addBtn = document.getElementById('pwa-btn-create-default-task');
	if (addBtn) addBtn.disabled = true;

	var appEl = document.getElementById('dolisirh-pwa-app');
	var baseUrl = appEl ? appEl.getAttribute('data-ajax-url') : window.location.pathname;
	var url = baseUrl + '?action=pwa_create_default_task&project_id=' + encodeURIComponent(pwa.selectedProject.id) + '&token=' + encodeURIComponent(pwa.getToken());

	fetch(url, { method: 'POST', credentials: 'same-origin' })
		.then(function(r) { return r.json(); })
		.then(function(res) {
			if (res && res.success && res.task) {
				pwa.projectTasks = res.tasks || [res.task];
				pwa.renderTaskFieldState();
				pwa.selectTask(res.task);
				pwa.showMsg(res.created ? ('Tâche "' + res.task.label + '" créée et sélectionnée.') : ('Tâche "' + res.task.label + '" sélectionnée.'), 'ok');
			} else {
				pwa.showMsg((res && res.error) ? res.error : 'Erreur lors de la création de la tâche', 'err');
			}
		})
		.finally(function() {
			if (addBtn) addBtn.disabled = !pwa.selectedProject;
		});
};

/**
 * Enable the Pointer button once a task and a start time are chosen, the Validate button once the entry
 * being stopped has a duration.
 *
 * @returns {void}
 */
window.dolisirh.pwa.updateClockButtons = function() {
	var pwa = window.dolisirh.pwa;
	var startBtn = document.getElementById('pwa-btn-start');
	var saveBtn = document.getElementById('pwa-btn-save-proj');
	var labelEl = document.getElementById('pwa-btn-save-proj-label');

	var hasTask = Boolean(pwa.selectedTask && pwa.selectedTask.id);
	var hasRunning = Boolean(pwa.state && Array.isArray(pwa.state.running) && pwa.state.running.length);
	if (startBtn) {
		startBtn.disabled = pwa.saving || !hasTask || hasRunning || pwa.hmToMin(pwa.fieldValue('pwa-proj-start')) < 0 || pwa.fieldValue('pwa-proj-date') === '';
	}

	var minutes = pwa.clockMinutes();
	if (labelEl) {
		labelEl.textContent = pwa.label('validate') + (minutes > 0 ? ' · ' + pwa.durationLabel(minutes) : '');
	}
	if (saveBtn) {
		saveBtn.disabled = pwa.saving || !pwa.stopping || minutes <= 0;
	}
};

/**
 * Send a JSON request to the page.
 *
 * @param {string} action Action name
 * @param {Object} payload Request payload
 * @returns {Promise<Object>}
 */
window.dolisirh.pwa.postJson = function(action, payload) {
	var pwa = window.dolisirh.pwa;
	var appEl = document.getElementById('dolisirh-pwa-app');
	var baseUrl = appEl ? appEl.getAttribute('data-ajax-url') : window.location.pathname;

	return fetch(baseUrl + '?action=' + encodeURIComponent(action) + '&token=' + encodeURIComponent(pwa.getToken()), {
		method: 'POST',
		credentials: 'same-origin',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify(payload),
	})
		.then(function(r) { return r.json(); })
		.catch(function() {
			return { success: false, error: pwa.label('networkError') };
		});
};

/**
 * Start a time entry on the selected task: kept on the server until it is stopped.
 *
 * @returns {void}
 */
window.dolisirh.pwa.startClock = function() {
	var pwa = window.dolisirh.pwa;
	if (!pwa.selectedTask || pwa.saving || !pwa.state) {
		return;
	}

	// An untouched start is the time of the tap: the field may lag a few seconds behind the clock
	var startHm = (!pwa.startTouched && pwa.isTodaySelected()) ? pwa.nowHM() : pwa.fieldValue('pwa-proj-start');
	pwa.saving = true;
	pwa.updateClockButtons();

	pwa.postJson('pwa_start_clock', {
		task_id: pwa.selectedTask.id,
		date: pwa.fieldValue('pwa-proj-date') || pwa.state.selected_date,
		start_hm: startHm,
		cal_month: pwa.state.cal_month ? pwa.state.cal_month.ym : '',
	}).then(function(res) {
		pwa.saving = false;
		if (res && res.success && res.state) {
			pwa.state = res.state;
			pwa.clockDate = '';
			pwa.renderAll();
			pwa.showMsg(pwa.label('started', startHm), 'ok');
		} else {
			pwa.showMsg((res && res.error) ? res.error : pwa.label('error'), 'err');
		}
		pwa.updateClockButtons();
	});
};

/**
 * Ask for a confirmation in the dialog of the page.
 *
 * @param {string} title Question
 * @param {string} text Consequence of the answer
 * @param {string} okLabel Label of the confirming button
 * @returns {Promise<boolean>} Whether the action is confirmed
 */
window.dolisirh.pwa.confirm = function(title, text, okLabel) {
	var pwa = window.dolisirh.pwa;
	var dialog = document.getElementById('pwa-confirm');
	if (!dialog || typeof dialog.showModal !== 'function') {
		return Promise.resolve(window.confirm(title + '\n' + text));
	}

	// A question left open is answered no before the next one is asked
	pwa.closeConfirm(false);
	document.getElementById('pwa-confirm-title').textContent = title;
	document.getElementById('pwa-confirm-text').textContent = text;
	document.getElementById('pwa-confirm-ok').textContent = okLabel;

	return new Promise(function(resolve) {
		pwa.confirmResolve = resolve;
		dialog.showModal();
		document.getElementById('pwa-confirm-cancel').focus();
	});
};

/**
 * Close the confirmation dialog with its answer.
 *
 * @param {boolean} confirmed Whether the action is confirmed
 * @returns {void}
 */
window.dolisirh.pwa.closeConfirm = function(confirmed) {
	var pwa = window.dolisirh.pwa;
	var dialog = document.getElementById('pwa-confirm');
	var resolve = pwa.confirmResolve;
	pwa.confirmResolve = null;
	if (dialog && dialog.open) {
		dialog.close();
	}
	if (resolve) {
		resolve(confirmed);
	}
};

/**
 * Bind the confirmation dialog: its buttons, Escape, and a tap outside it.
 *
 * @returns {void}
 */
window.dolisirh.pwa.bindConfirmEvents = function() {
	var pwa = window.dolisirh.pwa;
	var dialog = document.getElementById('pwa-confirm');
	if (!dialog) {
		return;
	}

	document.getElementById('pwa-confirm-cancel').addEventListener('click', function() {
		pwa.closeConfirm(false);
	});
	document.getElementById('pwa-confirm-ok').addEventListener('click', function() {
		pwa.closeConfirm(true);
	});
	dialog.addEventListener('cancel', function(e) {
		e.preventDefault();
		pwa.closeConfirm(false);
	});
	// Only the backdrop is the dialog itself; a press started inside that ends outside does not close it
	var pressedOutside = false;
	dialog.addEventListener('pointerdown', function(e) {
		pressedOutside = e.target === dialog;
	});
	dialog.addEventListener('click', function(e) {
		if (e.target === dialog && pressedOutside) {
			pwa.closeConfirm(false);
		}
	});
};

/**
 * Give up a running time entry: nothing is saved.
 *
 * @param {number} taskId Task of the running entry
 * @returns {void}
 */
window.dolisirh.pwa.cancelRunning = function(taskId) {
	var pwa = window.dolisirh.pwa;
	if (!taskId || !pwa.state) {
		return;
	}

	pwa.confirm(pwa.label('cancelTitle'), pwa.label('cancelText'), pwa.label('cancelOk')).then(function(confirmed) {
		if (!confirmed) {
			return null;
		}
		return pwa.postJson('pwa_cancel_clock', {
			task_id: taskId,
			selected_date: pwa.state.selected_date,
			cal_month: pwa.state.cal_month ? pwa.state.cal_month.ym : '',
		});
	}).then(function(res) {
		if (res === null) {
			return;
		}
		if (res && res.success && res.state) {
			pwa.state = res.state;
			pwa.renderAll();
			pwa.showMsg(pwa.label('cancelled'), 'ok');
		} else {
			pwa.showMsg((res && res.error) ? res.error : pwa.label('error'), 'err');
		}
	});
};

/**
 * Stop the running entry of the stop panel: the time entry is saved with its comment and medias.
 *
 * @returns {void}
 */
window.dolisirh.pwa.stopClock = function() {
	var pwa = window.dolisirh.pwa;
	var entry = pwa.stopping;
	if (!entry || pwa.saving) {
		return;
	}

	var noteEl = document.getElementById('pwa-proj-note');
	// An end following the clock is the time of the tap
	var endHm = (!pwa.endTouched && pwa.followsClock(entry)) ? pwa.nowHM() : pwa.fieldValue('pwa-proj-end');
	pwa.saving = true;
	pwa.updateClockButtons();

	pwa.submitTimeSpent({
		task_id: entry.task_id,
		date: entry.date,
		start_hm: entry.start_hm,
		end_hm: endHm,
		note: noteEl ? noteEl.value.trim() : '',
		is_project_mode: true,
		stop_running: true,
		cal_month: (pwa.state && pwa.state.cal_month) ? pwa.state.cal_month.ym : '',
	}).then(function(res) {
		pwa.saving = false;
		if (res && res.success) {
			if (noteEl) {
				noteEl.value = '';
			}
			pwa.closeStop();
			if (res.media_block_html) {
				var wrapper = document.getElementById('pwa-proj-media-wrapper');
				if (wrapper) {
					wrapper.innerHTML = res.media_block_html;
				}
			}
			if (Array.isArray(res.project_tasks)) {
				pwa.projectTasks = res.project_tasks;
				var updatedTask = pwa.projectTasks.find(function(t) { return pwa.selectedTask && t.id === pwa.selectedTask.id; });
				if (updatedTask) {
					pwa.selectedTask = updatedTask;
					pwa.renderTaskStats();
				}
			}
			if (pwa.selectedProject && pwa.state && Array.isArray(pwa.state.projects)) {
				var updatedProj = pwa.state.projects.find(function(p) { return p.id === pwa.selectedProject.id; });
				if (updatedProj) {
					pwa.selectedProject = updatedProj;
					pwa.renderProjectStats();
				}
			}
		} else if (res && res.state) {
			// The entry was stopped or given up elsewhere: the panel closes on the fresh state
			pwa.state = res.state;
			pwa.renderAll();
		}
		pwa.updateClockButtons();
	});
};

/**
 * Send time spent creation request to backend.
 *
 * @param {Object} payload Request payload
 * @returns {Promise<Object>}
 */
window.dolisirh.pwa.submitTimeSpent = function(payload) {
	var pwa = window.dolisirh.pwa;
	var appEl = document.getElementById('dolisirh-pwa-app');
	var baseUrl = appEl ? appEl.getAttribute('data-ajax-url') : window.location.pathname;
	var url = baseUrl + '?action=pwa_add_timespent&token=' + encodeURIComponent(pwa.getToken());

	return fetch(url, {
		method: 'POST',
		credentials: 'same-origin',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify(payload),
	})
		.then(function(r) { return r.json(); })
		.then(function(res) {
			if (res && res.success && res.state) {
				pwa.state = res.state;
				pwa.renderAll();
				var range = (payload.start_hm && payload.end_hm) ? (payload.start_hm + ' - ' + payload.end_hm + ', ') : '';
				var mediaCount = Array.isArray(res.saved_medias) ? res.saved_medias.length : 0;
				var mediaMsg = mediaCount > 0 ? ' · ' + pwa.label('mediasSaved', String(mediaCount)) : '';
				pwa.showMsg(pwa.label('saved') + ' (' + range + (res.duration_hm || payload.duration_hm) + ')' + mediaMsg, 'ok');
			} else {
				pwa.showMsg((res && res.error) ? res.error : pwa.label('error'), 'err');
			}
			return res;
		})
		.catch(function(err) {
			pwa.showMsg(err.message || pwa.label('networkError'), 'err');
			return { success: false };
		});
};

/**
 * Delete a time spent entry by ID.
 *
 * @param {number} timeSpentId Entry rowid in llx_element_time
 * @returns {Promise<void>}
 */
window.dolisirh.pwa.deleteTimeSpent = function(timeSpentId) {
	var pwa = window.dolisirh.pwa;
	var appEl = document.getElementById('dolisirh-pwa-app');
	var baseUrl = appEl ? appEl.getAttribute('data-ajax-url') : window.location.pathname;
	var url = baseUrl + '?action=pwa_delete_timespent&token=' + encodeURIComponent(pwa.getToken());

	return fetch(url, {
		method: 'POST',
		credentials: 'same-origin',
		headers: { 'Content-Type': 'application/json' },
		body: JSON.stringify({
			timespent_id: timeSpentId,
			selected_date: pwa.state ? pwa.state.selected_date : '',
			cal_month: (pwa.state && pwa.state.cal_month) ? pwa.state.cal_month.ym : '',
			project_id: pwa.selectedProject ? pwa.selectedProject.id : 0,
		}),
	})
		.then(function(r) { return r.json(); })
		.then(function(res) {
			if (res && res.success && res.state) {
				pwa.state = res.state;
				if (Array.isArray(res.project_tasks) && res.project_tasks.length > 0) {
					pwa.projectTasks = res.project_tasks;
				}
				pwa.renderAll();
				pwa.showMsg(pwa.label('deleted'), 'ok');
			} else {
				pwa.showMsg((res && res.error) ? res.error : pwa.label('error'), 'err');
			}
		});
};

/**
 * Render the entries of the selected day under the Pointer form, in clock order, with their medias.
 *
 * @returns {void}
 */
window.dolisirh.pwa.renderProjectHistory = function() {
	var pwa = window.dolisirh.pwa;
	var container = document.getElementById('pwa-proj-history');
	if (!container || !pwa.state) return;

	var dayData = pwa.state.selected_day;
	var entries = (dayData && Array.isArray(dayData.entries)) ? dayData.entries : [];
	if (!dayData) {
		container.innerHTML = '';
		return;
	}

	var totalSec = entries.reduce(function(sum, entry) { return sum + (Number(entry.duration) || 0); }, 0);
	var html = '<div class="ts-proj-history__title">' +
		'<span>' + pwa.esc(pwa.label('dayEntries', dayData.dmy)) + '</span>' +
		(entries.length ? '<span class="ts-proj-history__total">' + pwa.esc(pwa.label('total')) + ' <b>' + pwa.esc(pwa.durationLabel(totalSec / 60)) + '</b></span>' : '') +
	'</div>';

	if (entries.length === 0) {
		container.innerHTML = html + '<div class="ts-timeline__empty">' + pwa.esc(pwa.label('noEntry')) + '</div>';
		return;
	}

	html += '<ol class="ts-timeline">' + entries.map(function(entry) {
		var medias = Array.isArray(entry.medias) ? entry.medias : [];
		var time = '<span class="ts-timeline__range is-duration-only">&mdash;</span>';
		if (entry.start_hm) {
			time = '<span class="ts-timeline__range">' + pwa.esc(entry.start_hm) + '<small>' + pwa.esc(entry.end_hm) + '</small></span>';
		}
		var mediasHtml = medias.map(function(media) {
			if (media.type === 'image') {
				return '<a class="ts-timeline__photo" href="' + pwa.esc(media.url) + '" target="_blank" rel="noopener"><img src="' + pwa.esc(media.thumb) + '" alt="" loading="lazy"></a>';
			}
			if (media.type === 'file') {
				return '<a class="ts-timeline__file" href="' + pwa.esc(media.url) + '" target="_blank" rel="noopener" title="' + pwa.esc(media.name) + '"><i class="fas fa-paperclip"></i><span>' + pwa.esc(media.name) + '</span></a>';
			}
			return '<button type="button" class="ts-timeline__voice" data-src="' + pwa.esc(media.url) + '" title="' + pwa.esc(pwa.label('playVoice')) + '"><i class="fas fa-play"></i></button>';
		}).join('');

		return '<li class="ts-timeline__item' + (entry.is_hr ? ' is-hr' : '') + '">' +
			time +
			'<div class="ts-timeline__body">' +
				'<div class="ts-timeline__head">' +
					'<a href="' + pwa.esc(entry.task_url) + '" target="_blank" class="ts-timeline__task" title="' + pwa.esc(entry.task_ref + ' - ' + entry.task_label) + '">' + pwa.esc(entry.task_label || entry.task_ref) + '</a>' +
					'<span class="ts-timeline__dur">' + pwa.esc(pwa.durationLabel((Number(entry.duration) || 0) / 60)) + '</span>' +
				'</div>' +
				'<div class="ts-timeline__project">' + pwa.esc(entry.project_ref + ' - ' + entry.project_title) + '</div>' +
				(entry.note ? '<div class="ts-timeline__note">' + pwa.esc(entry.note) + '</div>' : '') +
				(mediasHtml ? '<div class="ts-timeline__medias">' + mediasHtml + '</div>' : '') +
			'</div>' +
			'<button type="button" class="ts-timeline__del" data-entry-id="' + entry.id + '" data-has-medias="' + (medias.length ? '1' : '0') + '"><i class="far fa-trash-alt"></i></button>' +
		'</li>';
	}).join('') + '</ol>';

	container.innerHTML = html;

	container.querySelectorAll('.ts-timeline__del').forEach(function(delBtn) {
		delBtn.addEventListener('click', function() {
			var entryId = parseInt(delBtn.getAttribute('data-entry-id'), 10);
			var remove = function() {
				delBtn.disabled = true;
				pwa.deleteTimeSpent(entryId);
			};
			if (entryId <= 0) {
				return;
			}
			if (delBtn.getAttribute('data-has-medias') !== '1') {
				remove();
				return;
			}
			// Photos, files and voice notes go with the entry and cannot be brought back
			pwa.confirm(pwa.label('deleteTitle'), pwa.label('deleteText'), pwa.label('deleteOk')).then(function(confirmed) {
				if (confirmed) {
					remove();
				}
			});
		});
	});

	container.querySelectorAll('.ts-timeline__voice').forEach(function(voiceBtn) {
		voiceBtn.addEventListener('click', function() {
			pwa.toggleVoice(voiceBtn);
		});
	});
};

/**
 * Play or stop the voice note of an entry, one at a time.
 *
 * @param {HTMLElement} btn Button carrying the recording URL
 * @returns {void}
 */
window.dolisirh.pwa.toggleVoice = function(btn) {
	var pwa = window.dolisirh.pwa;
	var isPlaying = btn.classList.contains('is-playing');

	if (pwa.voice) {
		pwa.voice.pause();
		pwa.voice = null;
	}
	document.querySelectorAll('.ts-timeline__voice.is-playing').forEach(function(other) {
		other.classList.remove('is-playing');
		other.innerHTML = '<i class="fas fa-play"></i>';
	});
	if (isPlaying) {
		return;
	}

	pwa.voice = new Audio(btn.getAttribute('data-src'));
	btn.classList.add('is-playing');
	btn.innerHTML = '<i class="fas fa-stop"></i>';
	pwa.voice.addEventListener('ended', function() {
		btn.classList.remove('is-playing');
		btn.innerHTML = '<i class="fas fa-play"></i>';
	});
	pwa.voice.play().catch(function() {
		btn.classList.remove('is-playing');
		btn.innerHTML = '<i class="fas fa-play"></i>';
		pwa.showMsg(pwa.label('error'), 'err');
	});
};

/**
 * Render the Mensuel calendar grid (5-day week rows).
 *
 * @returns {void}
 */
window.dolisirh.pwa.renderMonthlyCalendar = function() {
	var pwa = window.dolisirh.pwa;
	var st = pwa.state;
	if (!st || !st.cal_month) return;

	var cal = st.cal_month;
	var labelEl = document.getElementById('pwa-cal-month-label');
	var summaryEl = document.getElementById('pwa-cal-month-summary');
	var gridEl = document.getElementById('pwa-cal-grid');

	if (labelEl) {
		labelEl.textContent = cal.label;
	}
	if (summaryEl) {
		summaryEl.textContent = 'Pointé : ' + cal.logged_hr_hm + ' / Attendu : ' + cal.expected_hm;
	}
	if (!gridEl) return;

	var daysMap = cal.days || {};
	var dateKeys = Object.keys(daysMap).sort();
	var weeks = [];
	var currentWeek = [null, null, null, null, null];

	dateKeys.forEach(function(dateKey) {
		var d = daysMap[dateKey];
		var iso = Number(d.iso_day) || 1;
		if (iso >= 6) {
			return;
		}
		var colIdx = iso - 1;
		if (colIdx === 0 && currentWeek.some(function(x) { return x !== null; })) {
			weeks.push(currentWeek);
			currentWeek = [null, null, null, null, null];
		}
		currentWeek[colIdx] = d;
	});

	if (currentWeek.some(function(x) { return x !== null; })) {
		weeks.push(currentWeek);
	}

	var todayStr = st.today;
	var selectedDateStr = st.selected_date;

	gridEl.innerHTML = weeks.map(function(week) {
		var cellsHtml = week.map(function(day) {
			if (!day) {
				return '<div class="ts-cal-day is-empty"></div>';
			}
			var expectedSec = Number(day.expected_sec) || 0;
			var presSec = Number(day.pres_sec) || 0;
			var absSec = Number(day.abs_sec) || 0;
			var totalDaySec = presSec + absSec;

			var stateClass = 'cal-day-grey';
			if ((expectedSec > 0 && totalDaySec === expectedSec) || (expectedSec === 0 && totalDaySec > 0)) {
				stateClass = 'cal-day-ok';
			} else if (expectedSec > 0 && totalDaySec > expectedSec) {
				stateClass = 'cal-day-over';
			} else if ((day.date <= todayStr && expectedSec > 0 && totalDaySec !== expectedSec) || (day.date > todayStr && totalDaySec > 0 && totalDaySec !== expectedSec)) {
				stateClass = 'cal-day-ko';
			}

			var paText = '-';
			var paClass = 'is-none';
			if (presSec > 0 && absSec > 0) {
				paText = 'P/A';
				paClass = 'is-pa';
			} else if (presSec > 0) {
				paText = 'P';
				paClass = 'is-p';
			} else if (absSec > 0) {
				paText = 'A';
				paClass = 'is-a';
			}

			var durStr = (stateClass === 'cal-day-grey')
				? (expectedSec > 0 ? pwa.secToHM(expectedSec) : '00:00')
				: (totalDaySec > 0 ? pwa.secToHM(totalDaySec) : '00:00');

			var isSelectedClass = (day.date === selectedDateStr) ? ' is-today' : '';

			return '<div class="ts-cal-day ' + stateClass + isSelectedClass + '" data-date="' + pwa.esc(day.date) + '">' +
				'<div class="ts-cal-day-top">' +
					'<span class="ts-cal-day-name">' + pwa.esc(day.day_name) + '</span>' +
					'<span class="ts-cal-pa-badge ' + paClass + '">' + pwa.esc(paText) + '</span>' +
				'</div>' +
				'<span class="ts-cal-day-num">' + pwa.esc(day.dd) + '</span>' +
				'<span class="ts-cal-day-month">' + pwa.esc(day.mm_yyyy) + '</span>' +
				'<span class="ts-cal-day-dur">' + pwa.esc(durStr) + '</span>' +
			'</div>';
		}).join('');

		return '<div class="ts-cal-week">' + cellsHtml + '</div>';
	}).join('');

	gridEl.querySelectorAll('.ts-cal-day[data-date]').forEach(function(cell) {
		cell.addEventListener('click', function() {
			var dateStr = cell.getAttribute('data-date');
			if (dateStr) {
				pwa.setMode('week');
				pwa.fetchState(dateStr, dateStr.slice(0, 7));
			}
		});
	});
};
