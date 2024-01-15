/* Copyright (C) 2021-2023 EVARISK <technique@evarisk.com>
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
 *
 * Library javascript to enable Browser notifications
 */

/**
 * \file    js/task.js
 * \ingroup dolisirh
 * \brief   JavaScript task file for module DoliSIRH.
 */

/**
 * Init task JS.
 *
 * @memberof DoliSIRH_Task
 *
 * @since   1.1.0
 * @version 1.4.0
 *
 * @type {Object}
 */
window.dolisirh.task = {};

/**
 * Task init.
 *
 * @memberof DoliSIRH_Task
 *
 * @since   1.1.0
 * @version 1.4.0
 *
 * @returns {void}
 */
window.dolisirh.task.init = function() {
    window.dolisirh.task.event();
};

/**
 * Task event.
 *
 * @memberof DoliSIRH_Task
 *
 * @since   1.1.0
 * @version 1.4.0
 *
 * @returns {void}
 */
window.dolisirh.task.event = function() {
    $(document).on('click', '.auto-fill-timespent', window.dolisirh.task.addTimeSpent);
    $(document).on('click', '.auto-fill-timespent-project', window.dolisirh.task.divideTimeSpent);
    $(document).on('click', '.show-only-favorite-tasks', window.dolisirh.task.showOnlyFavoriteTasks);
    $(document).on('click', '.show-only-tasks-with-timespent', window.dolisirh.task.showOnlyTasksWithTimeSpent);
    $(document).on('click', '.select-logic-operators-mode', window.dolisirh.task.selectLogicOperatorsMode);
    $(document).on('click', '.show-closed-projects', window.dolisirh.task.showClosedProjects);
    $(document).on('click', '.show-sticky-total-timespent-info', window.dolisirh.task.showStickyTotalTimeSpentInfo);
    $(document).on('click', '.timespent-create', window.dolisirh.task.createTimeSpent);
    $(document).on('click', '.toggleTaskFavorite', window.dolisirh.task.toggleTaskFavorite);
    $(document).on('submit', '#addtimeform', window.dolisirh.task.searchForm );
};

/**
 * Automatically fills in the time available on a task.
 *
 * @memberof DoliSIRH_Task
 *
 * @since   1.1.0
 * @version 1.4.0
 *
 * @return {void}
 */
window.dolisirh.task.addTimeSpent = function() {
    let nonConsumedMinutes = $('.non-consumed-time-minute').val();
    let nonConsumedHours = $('.non-consumed-time-hour').val();
    $('.inputhour').val('');
    $('.inputminute').val('');
    $(this).closest('.duration').find('.inputhour').val(nonConsumedHours);
    $(this).closest('.duration').find('.inputminute').val(nonConsumedMinutes);
};

/**
 * Automatically allocates available clocking time to project tasks
 *
 * @memberof DoliSIRH_Task
 *
 * @since   1.1.0
 * @version 1.4.0
 *
 * @return {void}
 */
window.dolisirh.task.divideTimeSpent = function() {
    let projectId = $(this).closest('.project-line').attr('id');

    let taskMinute = 0;
    let taskHour = 0;

    let nonConsumedMinutes = $('.non-consumed-time-minute').val();
    let nonConsumedHours = $('.non-consumed-time-hour').val();
    let totalTimeInMinutes = +nonConsumedMinutes + +nonConsumedHours*60;

    let taskLinkedCounter = $('.'+projectId).length;
    let minutesToSpend = parseInt(totalTimeInMinutes/taskLinkedCounter);

    $('.inputhour').val('');
    $('.inputminute').val('');

    $('.'+projectId).each(function() {
        taskHour = parseInt(minutesToSpend/60);
        taskMinute = minutesToSpend%60;

        $(this).find('.inputhour').val(taskHour);
        $(this).find('.inputminute').val(taskMinute);
    })
};

/**
 * Enables/disables the configuration to display only favorite tasks.
 *
 * @memberof DoliSIRH_Task
 *
 * @since   1.1.0
 * @version 1.4.0
 *
 * @return {void}
 */
window.dolisirh.task.showOnlyFavoriteTasks = function() {
    let token = $('.id-container').find('input[name="token"]').val();
    let querySeparator = '?';

    document.URL.match(/\?/) ? querySeparator = '&' : 1;

    let showOnlyFavoriteTasks;
    if ($(this).is(':checked')) {
        showOnlyFavoriteTasks = 1;
    } else {
        showOnlyFavoriteTasks = 0;
    }

    $.ajax({
        url: document.URL + querySeparator + "action=show_only_favorite_tasks&token=" + token,
        type: "POST",
        processData: false,
        data: JSON.stringify({
            showOnlyFavoriteTasks: showOnlyFavoriteTasks
        }),
        contentType: false,
        success: function() {
            window.location.reload();
        },
        error: function() {}
    });
};

/**
 * Enables/disables the configuration to display only tasks with time stamp.
 *
 * @memberof DoliSIRH_Task
 *
 * @since   1.1.0
 * @version 1.4.0
 *
 * @return {void}
 */
window.dolisirh.task.showOnlyTasksWithTimeSpent = function() {
    let token = $('.id-container').find('input[name="token"]').val();
    let querySeparator = '?';

    document.URL.match(/\?/) ? querySeparator = '&' : 1

    let showOnlyTasksWithTimeSpent;
    if ($(this).is(':checked')) {
        showOnlyTasksWithTimeSpent = 1;
    } else {
        showOnlyTasksWithTimeSpent = 0;
    }

    $.ajax({
        url: document.URL + querySeparator + "action=show_only_tasks_with_timespent&token=" + token,
        type: "POST",
        processData: false,
        data: JSON.stringify({
            showOnlyTasksWithTimeSpent: showOnlyTasksWithTimeSpent
        }),
        contentType: false,
        success: function() {
            window.location.reload();
        },
        error: function() {}
    });
};

/**
 * Select Logic operators mode beetween AND/OR
 *
 * @memberof DoliSIRH_Task
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @return {void}
 */
window.dolisirh.task.selectLogicOperatorsMode = function() {
  let token          = window.saturne.toolbox.getToken();
  let querySeparator = window.saturne.toolbox.getQuerySeparator(document.URL);

  let selectLogicOperatorsMode;
  if ($(this).is(':checked')) {
    selectLogicOperatorsMode = 1;
  } else {
    selectLogicOperatorsMode = 0;
  }

  $.ajax({
    url: document.URL + querySeparator + "action=select_logic_operators_mode&token=" + token,
    type: "POST",
    processData: false,
    data: JSON.stringify({
      selectLogicOperatorsMode: selectLogicOperatorsMode
    }),
    contentType: false,
    success: function() {
      window.location.reload();
    },
    error: function() {}
  });
};

/**
 * Enables/disables the configuration to display closed projects
 *
 * @memberof DoliSIRH_Task
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @return {void}
 */
window.dolisirh.task.showClosedProjects = function() {
  let token          = window.saturne.toolbox.getToken();
  let querySeparator = window.saturne.toolbox.getQuerySeparator(document.URL);

  let showClosedProjects;
  if ($(this).is(':checked')) {
    showClosedProjects = 1;
  } else {
    showClosedProjects = 0;
  }

  $.ajax({
    url: document.URL + querySeparator + "action=show_closed_projects&token=" + token,
    type: "POST",
    processData: false,
    data: JSON.stringify({
      showClosedProjects: showClosedProjects
    }),
    contentType: false,
    success: function() {
      window.location.reload();
    },
    error: function() {}
  });
};

/**
 * Enables/disables the configuration to display sticky total time spent info in top or bottom
 *
 * @memberof DoliSIRH_Task
 *
 * @since   1.5.0
 * @version 1.5.0
 *
 * @return {void}
 */
window.dolisirh.task.showStickyTotalTimeSpentInfo = function() {
  let token          = window.saturne.toolbox.getToken();
  let querySeparator = window.saturne.toolbox.getQuerySeparator(document.URL);

  let showStickyTotalTimeSpentInfo;
  if ($(this).is(':checked')) {
    showStickyTotalTimeSpentInfo = 1;
  } else {
    showStickyTotalTimeSpentInfo = 0;
  }

  $.ajax({
    url: document.URL + querySeparator + "action=show_sticky_total_timespent_info&token=" + token,
    type: "POST",
    processData: false,
    data: JSON.stringify({
      showStickyTotalTimeSpentInfo: showStickyTotalTimeSpentInfo
    }),
    contentType: false,
    success: function() {
      window.location.reload();
    },
    error: function() {}
  });
};

/**
 * Action create timespent.
 *
 * @memberof DoliSIRH_Task
 *
 * @since   1.1.0
 * @version 1.4.0
 *
 * @return {void}
 */
window.dolisirh.task.createTimeSpent = function() {
    let taskID  = $(this).attr('value');
    let element = $(this).closest('.timespent-add-modal').find('.timespent-container');

    let timestamp = element.find('.timespent-timestamp').val();
    let datehour  = element.find('.timespent-datehour').val();
    let datemin   = element.find('.timespent-datemin').val();
    let comment   = element.find('.timespent-comment').val();
    let hour      = element.find('.timespent-hour').val();
    let min       = element.find('.timespent-min').val();

    window.saturne.loader.display($(this));

    let token = $('.fiche').find('input[name="token"]').val();
    let querySeparator = '?';

    document.URL.match(/\?/) ? querySeparator = '&' : 1;

    $.ajax({
        url: document.URL + querySeparator + 'action=add_timespent&token=' + token,
        data: JSON.stringify({
            taskID: taskID,
            timestamp: timestamp,
            datehour: datehour,
            datemin: datemin,
            comment: comment,
            hour: hour,
            min: min
        }),
        type: "POST",
        processData: false,
        contentType: false,
        success: function(resp) {
            $('.loader-spin').remove();
            $('.wpeo-loader').removeClass('wpeo-loader');
            $('#timespent').removeClass('modal-active');
            $('#tablelines3').html($(resp).find('#tablelines3'));
        },
        error: function(resp) {}
    });
};

/**
 * Toggle favorite task.
 *
 * @memberof DoliSIRH_Task
 *
 * @since   1.1.0
 * @version 1.4.0
 *
 * @return {void}
 */
window.dolisirh.task.toggleTaskFavorite = function() {
    let taskID = $(this).attr('value');
    let token = $('form[name="addtime"]').find('input[name="token"]').val();
    let querySeparator = '?';

    document.URL.match(/\?/) ? querySeparator = '&' : 1;

    $.ajax({
        url: document.URL + querySeparator + 'action=toggleTaskFavorite&taskId=' + taskID + '&token=' + token,
        type: "POST",
        processData: false,
        contentType: false,
        success: function() {
            let taskContainer = $('#' + taskID);

            if (taskContainer.hasClass('fas')) {
                taskContainer.removeClass('fas');
                taskContainer.addClass('far');
            } else if (taskContainer.hasClass('far')) {
                taskContainer.removeClass('far');
                taskContainer.addClass('fas');
            }
        },
        error: function(resp) {}
    });
};

/**
 * Add more open modal data.
 *
 * @memberof DoliSIRH_Task
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @return {void}
 */
window.saturne.modal.addMoreOpenModalData = function(modalToOpen, elementFrom) {
    let cell = elementFrom.find('.timespent');

    let taskID    = cell.attr('data-task-id');
    let timestamp = cell.attr('data-timestamp');
    let dataCell  = cell.attr('data-cell');
    let date      = cell.attr('data-date');
    $('.timespent-taskid').val(taskID);
    $('.timespent-timestamp').val(timestamp);
    $('.timespent-cell').val(dataCell);
    $('.timespent-create').attr('value', taskID);
    $('.timespent-date').html(date);
};

/**
 * Submit form dynamically to avoid 406 errors.
 *
 * @memberof DoliSIRH_Task
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @return {void}
 */
window.dolisirh.task.searchForm = function(event) {
  event.preventDefault()

  var addTimeForm = document.getElementById('addtimeform');
  var formData    = new FormData(addTimeForm);
  let newFormData = new FormData();

  for (const pair of formData.entries()) {
    if (pair[1] != '') {
      newFormData.append(pair[0], pair[1])
    }
  }
  window.saturne.loader.display($('#addtimeform'));

  $.ajax({
    url: document.URL,
    type: "POST",
    data: newFormData,
    processData: false,
    contentType: false,
    success: function (resp) {
      $('.wpeo-loader').removeClass('wpeo-loader');
      $('#addtimeform').html($(resp).find('#addtimeform').children())
    },
  });
}
