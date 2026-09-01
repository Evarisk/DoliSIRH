<?php
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
 * \file    core/tpl/timespent_assign_invoice_cell.tpl.php
 * \ingroup dolisirh
 * \brief   Content put by the module into the native "Billed" cells of the time spent list.
 *
 * Dolibarr offers no hook inside those cells, so the content is printed hidden inside the form of the list, then
 * put in place by js/timespent-invoice.js : the warning replaces the plain "Disabled" text of every line that is
 * not billable, and the selector takes place on the line being edited.
 */

/**
 * @var Form      $form
 * @var Translate $langs
 * @var array     $assignableInvoiceLines Invoice service lines holding enough available time.
 * @var bool      $isTimeSpentBillable    True when the line being edited can be assigned to an invoice.
 */

$invoiceLineOptions = [];
foreach ($assignableInvoiceLines as $invoiceLineID => $assignableInvoiceLine) {
    $invoiceLineOptions[$invoiceLineID] = dolisirh_get_invoice_line_label($assignableInvoiceLine);
}

?>
<div id="dolisirh-timespent-invoice-assign" class="hideobject" data-billed-column-label="<?php echo dol_escape_htmltag($langs->trans('Billed')); ?>" data-not-billable-source-label="<?php echo dol_escape_htmltag($langs->trans('Disabled')); ?>">
    <span class="dolisirh-timespent-not-billable warning classfortooltip" title="<?php echo dol_escape_htmltag($langs->trans('TimeSpentNotBillableHelp')); ?>">
        <?php echo img_picto('', 'warning', 'class="pictofixedwidth"') . $langs->trans('TimeSpentNotBillable'); ?>
    </span>
    <?php if ($isTimeSpentBillable) { ?>
        <div class="dolisirh-timespent-invoice-selector">
            <?php if (empty($invoiceLineOptions)) { ?>
                <span class="opacitymedium classfortooltip" title="<?php echo dol_escape_htmltag($langs->trans('NoInvoiceLineWithEnoughAvailableTime')); ?>">
                    <?php echo img_picto('', 'warning', 'class="pictofixedwidth"') . $langs->trans('NoAvailableInvoiceLine'); ?>
                </span>
            <?php } else {
                echo $form->selectarray('dolisirh_invoice_line_id', $invoiceLineOptions, GETPOSTINT('dolisirh_invoice_line_id'), 1, 0, 0, '', 0, 0, 0, '', 'minwidth200 maxwidth300', 0);
            } ?>
        </div>
    <?php } ?>
</div>
