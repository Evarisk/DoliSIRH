/* Copyright (C) 2023 EVARISK <technique@evarisk.com>
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
 * \file    js/timespent.js
 * \ingroup dolisirh
 * \brief   JavaScript timespent file for module DoliSIRH
 */

/**
 * Init timespent JS
 *
 * @memberof DoliSIRH_Timespent
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @type {Object}
 */
window.dolisirh.timespent = {};

/**
 * Timespent init
 *
 * @memberof DoliSIRH_Timespent
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @returns {void}
 */
window.dolisirh.timespent.init = function() {
  window.dolisirh.timespent.event();
};

/**
 * Timespent event
 *
 * @memberof DoliSIRH_Timespent
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @returns {void}
 */
window.dolisirh.timespent.event = function() {
  $(document).ready(function() {
    window.dolisirh.timespent.fixHeader()
  });
};

/**
 * Fix table header for show header after scroll event
 *
 * @memberof DoliSIRH_Timespent
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @return {void}
 */
window.dolisirh.timespent.fixHeader = function() {
  $("#tablelines3").floatThead({
    position: "fixed",
  });
};
