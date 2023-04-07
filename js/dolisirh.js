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
 * \brief   JavaScript file for module DoliSIRH.
 */

'use strict';

if (!window.dolisirh) {
	/**
	 * Init DoliSIRH JS.
	 *
	 * @memberof DoliSIRH_Init
	 *
	 * @since   1.4.0
	 * @version 1.4.0
	 *
	 * @type {Object}
	 */
	window.dolisirh = {};

	/**
	 * Init scriptsLoaded DoliSIRH.
	 *
	 * @memberof DoliSIRH_Init
	 *
	 * @since   1.4.0
	 * @version 1.4.0
	 *
	 * @type {Boolean}
	 */
	window.dolisirh.scriptsLoaded = false;
}

if (!window.dolisirh.scriptsLoaded) {
	/**
	 * DoliSIRH init.
	 *
	 * @memberof DoliSIRH_Init
	 *
	 * @since   1.4.0
	 * @version 1.4.0
	 *
	 * @returns {void}
	 */
	window.dolisirh.init = function() {
		window.dolisirh.load_list_script();
	};

	/**
	 * Load all modules' init.
	 *
	 * @memberof DoliSIRH_Init
	 *
	 * @since   1.4.0
	 * @version 1.4.0
	 *
	 * @returns {void}
	 */
	window.dolisirh.load_list_script = function() {
		if (!window.dolisirh.scriptsLoaded) {
			let key = undefined, slug = undefined;
			for (key in window.dolisirh) {
				if (window.dolisirh[key].init) {
					window.dolisirh[key].init();
				}
				for (slug in window.dolisirh[key]) {
					if (window.dolisirh[key] && window.dolisirh[key][slug] && window.dolisirh[key][slug].init) {
						window.dolisirh[key][slug].init();
					}
				}
			}
			window.dolisirh.scriptsLoaded = true;
		}
	};

	/**
	 * Refresh and reload all modules' init.
	 *
	 * @memberof DoliSIRH_Init
	 *
	 * @since   1.4.0
	 * @version 1.4.0
	 *
	 * @returns {void}
	 */
	window.dolisirh.refresh = function() {
		let key = undefined;
		let slug = undefined;
		for (key in window.dolisirh) {
			if (window.dolisirh[key].refresh) {
				window.dolisirh[key].refresh();
			}
			for (slug in window.dolisirh[key]) {
				if (window.dolisirh[key] && window.dolisirh[key][slug] && window.dolisirh[key][slug].refresh) {
					window.dolisirh[key][slug].refresh();
				}
			}
		}
	};
	$(document).ready(window.dolisirh.init);
}