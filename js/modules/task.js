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
 * \file    js/dolisirh.js
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
    $(document).on('click', '.timespent-create', window.dolisirh.task.createTimeSpent);
    $(document).on('click', '.toggleTaskFavorite', window.dolisirh.task.toggleTaskFavorite);
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
        url: document.URL + querySeparator + "action=showOnlyFavoriteTasks&token=" + token,
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
        url: document.URL + querySeparator + "action=showOnlyTasksWithTimeSpent&token=" + token,
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
    let cell    = $('#tablelines3').find('tr[data-taskid=' + taskID + ']').find('td[data-cell=' + element.find('.timespent-cell').val() + ']');

    let timestamp = element.find('.timespent-timestamp').val();
    let datehour  = element.find('.timespent-datehour').val();
    let datemin   = element.find('.timespent-datemin').val();
    let comment   = element.find('.timespent-comment').val();
    let hour      = element.find('.timespent-hour').val();
    let min       = element.find('.timespent-min').val();

    window.dolisirh.loader.display($(this));
    window.dolisirh.loader.display(cell);

    let token = $('.fiche').find('input[name="token"]').val();
    let querySeparator = '?';

    document.URL.match(/\?/) ? querySeparator = '&' : 1;

    $.ajax({
        url: document.URL + querySeparator + 'action=addTimeSpent&token=' + token,
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