/* Copyright (C) 2025 EVARISK <technique@evarisk.com>
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
 * \file    js/timespent-invoice.js
 * \ingroup dolisirh
 * \brief   Fills the native "Billed" cells of the time spent list with the content built by the module.
 *
 * Standalone script : the time spent list (projet/tasks/time.php) is a native Dolibarr page, the module bundle
 * is not loaded there. Dolibarr offers no hook inside its "Billed" cell, so the content is printed hidden by the
 * doPreMassActions hook, then put in place here : the warning on every line that is not billable, the selector
 * of the invoice service line on the line being edited.
 */

'use strict';

(function() {
	/**
	 * Index of the native "Billed" column, read from the header of the list.
	 *
	 * @param  {Object} $list       List of the time spent lines.
	 * @param  {String} columnLabel Label of the native "Billed" column.
	 * @return {Number}             Index of the column, -1 if it is not displayed.
	 */
	function getBilledColumnIndex($list, columnLabel) {
		var columnIndex = -1;

		$list.find('tr.liste_titre').first().children('th').each(function(index) {
			// The column holding the field selector lists every label, it must not be matched.
			if (columnIndex !== -1 || jQuery(this).find('select').length) {
				return;
			}
			if (jQuery(this).text().replace(/\s+/g, ' ').trim() === columnLabel) {
				columnIndex = index;
			}
		});

		return columnIndex;
	}

	/**
	 * Replace the plain "Disabled" text of Dolibarr by the warning telling the time cannot be billed.
	 *
	 * @param  {Object} $list       List of the time spent lines.
	 * @param  {Number} columnIndex Index of the native "Billed" column.
	 * @param  {Object} $content    Hidden container holding the content built by the module.
	 * @return {void}
	 */
	function flagNotBillableLines($list, columnIndex, $content) {
		var $warning         = $content.find('.dolisirh-timespent-not-billable');
		var notBillableLabel = $content.attr('data-not-billable-source-label');
		if (!$warning.length || !notBillableLabel) {
			return;
		}

		$list.find('tr').not('.liste_titre').not('.liste_titre_filter').each(function() {
			var $cell = jQuery(this).children('td').eq(columnIndex);
			if ($cell.length && $cell.text().replace(/\s+/g, ' ').trim() === notBillableLabel) {
				$cell.empty().append($warning.clone());
			}
		});
	}

	/**
	 * Move the selector of the invoice service line into the "Billed" cell of the line being edited.
	 *
	 * @param  {Number} columnIndex Index of the native "Billed" column.
	 * @param  {Object} $content    Hidden container holding the content built by the module.
	 * @return {void}
	 */
	function moveSelectorIntoEditedLine(columnIndex, $content) {
		var $selector = $content.find('.dolisirh-timespent-invoice-selector');
		if (!$selector.length) {
			return;
		}

		var $editedRow  = jQuery('input[name="new_durationhour"]').closest('tr');
		var $billedCell = $editedRow.children('td').eq(columnIndex);
		if (!$billedCell.length) {
			return;
		}

		$billedCell.empty().append($selector);

		var $select = $selector.find('select');
		if ($select.length && jQuery.fn.select2) {
			$select.select2({dir: 'ltr', width: 'resolve'});
		}
	}

	/**
	 * Fill the native "Billed" cells with the content built by the module.
	 *
	 * @return {void}
	 */
	function fillBilledCells() {
		var $content = jQuery('#dolisirh-timespent-invoice-assign');
		var $list    = jQuery('table.liste').first();
		if (!$content.length || !$list.length) {
			return;
		}

		var columnIndex = getBilledColumnIndex($list, $content.attr('data-billed-column-label'));
		if (columnIndex === -1) {
			return;
		}

		flagNotBillableLines($list, columnIndex, $content);
		moveSelectorIntoEditedLine(columnIndex, $content);
		$content.remove();
	}

	jQuery(document).ready(fillBilledCells);
})();
