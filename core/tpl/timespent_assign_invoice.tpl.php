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
 * \file    core/tpl/timespent_assign_invoice.tpl.php
 * \ingroup dolisirh
 * \brief   Form used to assign time spent lines to an invoice service line still holding time.
 */

/**
 * @var Form      $form
 * @var Translate $langs
 * @var array     $assignableInvoiceLines Invoice service lines holding enough available time.
 * @var array     $billableTimeSpentIDs   Time spent line IDs that can be assigned.
 * @var int       $nbTimeSpentNotBillable Number of selected lines left aside because they are not billable.
 * @var int       $totalDurationToAssign  Total duration to assign, in seconds.
 */

$invoiceLineOptions = [];
foreach ($assignableInvoiceLines as $invoiceLineID => $assignableInvoiceLine) {
    $invoiceLineOptions[$invoiceLineID] = dolisirh_get_invoice_line_label($assignableInvoiceLine);
}

$canAssign = !empty($billableTimeSpentIDs) && !empty($invoiceLineOptions);

?>
<div class="dolisirh-assign-timespent-invoice">
    <table class="noborder centpercent">
        <?php if ($nbTimeSpentNotBillable > 0) { ?>
            <tr>
                <td class="titlefield"><?php echo $langs->trans('TimeSpentNotBillable'); ?></td>
                <td>
                    <span class="warning">
                        <?php echo img_picto('', 'warning', 'class="pictofixedwidth"') . $langs->trans('NbTimeSpentNotBillableIgnored', $nbTimeSpentNotBillable); ?>
                    </span>
                </td>
            </tr>
        <?php } ?>
        <tr>
            <td class="titlefield"><?php echo $langs->trans('TimeSpentToAssign'); ?></td>
            <td>
                <?php echo convertSecondToTime($totalDurationToAssign, 'allhourmin'); ?>
                <span class="opacitymedium">(<?php echo $langs->trans('NbOfLines') . ' : ' . count($billableTimeSpentIDs); ?>)</span>
            </td>
        </tr>
        <tr>
            <td class="titlefield fieldrequired"><?php echo $langs->trans('InvoiceLineToConsume'); ?></td>
            <td>
                <?php if (empty($billableTimeSpentIDs)) { ?>
                    <span class="warning"><?php echo $langs->trans('NoBillableTimeSpentSelected'); ?></span>
                <?php } elseif (empty($invoiceLineOptions)) { ?>
                    <span class="warning"><?php echo $langs->trans('NoInvoiceLineWithEnoughAvailableTime'); ?></span>
                <?php } else {
                    echo img_picto('', 'bill', 'class="pictofixedwidth"');
                    echo $form->selectarray('dolisirh_invoice_line_id', $invoiceLineOptions, GETPOSTINT('dolisirh_invoice_line_id'), 1, 0, 0, '', 0, 0, 0, '', 'minwidth300 maxwidth500');
                } ?>
            </td>
        </tr>
    </table>
    <div class="center">
        <?php if ($canAssign) { ?>
            <input type="submit" class="button" name="assign_timespent_invoice" value="<?php echo dol_escape_htmltag($langs->trans('AssignTimeSpentToInvoice')); ?>">
        <?php } ?>
        <input type="submit" class="button button-cancel" name="cancel" value="<?php echo dol_escape_htmltag($langs->trans('Cancel')); ?>">
    </div>
    <br>
</div>
