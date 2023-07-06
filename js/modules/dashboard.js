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
 * \brief   JavaScript dashboard file for module DoliSIRH.
 */

/**
 * Init dashboard JS.
 *
 * @memberof DoliSIRH_Dashboard
 *
 * @since   1.1.0
 * @version 1.4.0
 *
 * @type {Object}
 */
window.dolisirh.dashboard = {};

/**
 * Dashboard init.
 *
 * @memberof DoliSIRH_Dashboard
 *
 * @since   1.1.0
 * @version 1.4.0
 *
 * @returns {void}
 */
window.dolisirh.dashboard.init = function() {
    window.dolisirh.dashboard.event();
};

/**
 * Dashboard event.
 *
 * @memberof DoliSIRH_Dashboard
 *
 * @since   1.1.0
 * @version 1.4.0
 *
 * @returns {void}
 */
window.dolisirh.dashboard.event = function() {
    $(document).on('change', '.add-dashboard-widget', window.dolisirh.dashboard.addDashBoardInfo);
    $(document).on('click', '.close-dashboard-widget', window.dolisirh.dashboard.closeDashBoardInfo);
    $(document).on('click', '.select-dataset-dashboard-info', window.dolisirh.dashboard.selectDatasetDashboardInfo);
};

/**
 * Add widget dashboard info.
 *
 * @memberof DoliSIRH_Dashboard
 *
 * @since   1.1.0
 * @version 1.4.0
 *
 * @returns {void}
 */
window.dolisirh.dashboard.addDashBoardInfo = function() {
    const dashboardWidgetForm = document.getElementById('dashBoardForm');
    const formData = new FormData(dashboardWidgetForm);
    let dashboardWidgetName = formData.get('boxcombo');
    let querySeparator = '?';
    let token = $('.dashboard').find('input[name="token"]').val();
    document.URL.match(/\?/) ? querySeparator = '&' : 1;

    $.ajax({
        url: document.URL + querySeparator + 'action=adddashboardinfo&token=' + token,
        type: "POST",
        processData: false,
        data: JSON.stringify({
            dashboardWidgetName: dashboardWidgetName
        }),
        contentType: false,
        success: function() {
            window.location.reload();
        },
        error: function() {}
    });
};

/**
 * Close widget dashboard info.
 *
 * @memberof DoliSIRH_Dashboard
 *
 * @since   1.1.0
 * @version 1.4.0
 *
 * @returns {void}
 */
window.dolisirh.dashboard.closeDashBoardInfo = function() {
    let box = $(this);
    let dashboardWidgetName = box.attr('data-widgetname');
    let querySeparator = '?';
    let token = $('.dashboard').find('input[name="token"]').val();
    document.URL.match(/\?/) ? querySeparator = '&' : 1;

    $.ajax({
        url: document.URL + querySeparator + 'action=closedashboardinfo&token=' + token,
        type: "POST",
        processData: false,
        data: JSON.stringify({
            dashboardWidgetName: dashboardWidgetName
        }),
        contentType: false,
        success: function(resp) {
            box.closest('.box-flex-item').fadeOut(400);
            $('.add-widget-box').attr('style', '');
            $('.add-widget-box').html($(resp).find('.add-widget-box').children())
        },
        error: function() {}
    });
};

/**
 * Select dataset dashboard info.
 *
 * @memberof DoliSIRH_Dashboard
 *
 * @since   1.2.1
 * @version 1.4.0
 *
 * @returns {void}
 */
window.dolisirh.dashboard.selectDatasetDashboardInfo = function() {
    let userID = $('#search_userid').val();
    let year   = $('#search_year').val();
    let month  = $('#search_month').val();

    let querySeparator = '?';
    let token = $('.dashboard').find('input[name="token"]').val();
    document.URL.match(/\?/) ? querySeparator = '&' : 1;

    window.dolisirh.loader.display($('.fichecenter'));

    $.ajax({
        url: document.URL + querySeparator + 'token=' + token + '&search_userid=' + userID + '&search_year=' + year + '&search_month=' + month,
        type: "POST",
        processData: false,
        contentType: false,
        success: function(resp) {
            $('.fichecenter').replaceWith($(resp).find('.fichecenter'));
        },
        error: function() {}
    });
};