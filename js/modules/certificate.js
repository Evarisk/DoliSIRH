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
 * \file    js/certificate.js
 * \ingroup dolisirh
 * \brief   JavaScript certificate file for module DoliSIRH.
 */

/**
 * Init certificate JS.
 *
 * @memberof DoliSIRH_Certificate
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @type {Object}
 */
window.dolisirh.certificate = {};

/**
 * Certificate init.
 *
 * @memberof DoliSIRH_Certificate
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @returns {void}
 */
window.dolisirh.certificate.init = function() {
    window.dolisirh.certificate.event();
};

/**
 * Certificate event.
 *
 * @memberof DoliSIRH_Certificate
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @returns {void}
 */
window.dolisirh.certificate.event = function() {
    $(document).on('change', '#element_type', window.dolisirh.certificate.reloadField);
};

/**
 * Certificate reload field.
 *
 * @memberof DoliSIRH_Certificate
 *
 * @since   1.4.0
 * @version 1.4.0
 *
 * @returns {void}
 */
window.dolisirh.certificate.reloadField = function() {
  let field          = $(this).val();
  let token          = window.saturne.toolbox.getToken();
  let querySeparator = window.saturne.toolbox.getQuerySeparator(document.URL);

  $.ajax({
    url: document.URL + querySeparator + "element_type=" + field + "&token=" + token,
    type: "POST",
    processData: false,
    contentType: false,
    success: function(resp) {
      $('.field_element_type').replaceWith($(resp).find('.field_element_type'));
      $('.field_fk_element').replaceWith($(resp).find('.field_fk_element'));
    },
    error: function() {}
  });
};
