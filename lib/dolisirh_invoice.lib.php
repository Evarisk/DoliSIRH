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
 * \file    lib/dolisirh_invoice.lib.php
 * \ingroup dolisirh
 * \brief   Library files with common functions to assign time spent on invoice service lines.
 */

// Load Dolibarr libraries.
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';

/**
 * Get a set of time spent lines with the data needed to assign them to an invoice : their duration, the invoice
 * line they already consume, and whether their project bills its time.
 *
 * @param  DoliDB $db           Database handler.
 * @param  array  $timeSpentIDs Time spent line IDs (llx_element_time rowid).
 * @return array                Time spent lines data, indexed by time spent line ID.
 */
function dolisirh_get_timespent_lines(DoliDB $db, array $timeSpentIDs): array
{
    $timeSpentLines = [];

    if (empty($timeSpentIDs)) {
        return $timeSpentLines;
    }

    $sql  = 'SELECT t.rowid, t.element_duration, t.invoice_id, t.invoice_line_id,';
    $sql .= ' pt.rowid as task_id, pt.billable,';
    $sql .= ' p.rowid as project_id, p.usage_bill_time';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'element_time as t';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'projet_task as pt ON pt.rowid = t.fk_element';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'projet as p ON p.rowid = pt.fk_projet';
    $sql .= " WHERE t.elementtype = 'task'";
    $sql .= ' AND t.rowid IN (' . implode(',', array_map('intval', $timeSpentIDs)) . ')';

    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog('dolisirh_get_timespent_lines ' . $db->lasterror(), LOG_ERR);
        return $timeSpentLines;
    }

    while ($obj = $db->fetch_object($resql)) {
        $timeSpentLines[(int) $obj->rowid] = [
            'rowid'            => (int) $obj->rowid,
            'element_duration' => (int) $obj->element_duration,
            'invoice_id'       => (int) $obj->invoice_id,
            'invoice_line_id'  => (int) $obj->invoice_line_id,
            'task_id'          => (int) $obj->task_id,
            'billable'         => (int) $obj->billable,
            'project_id'       => (int) $obj->project_id,
            'usage_bill_time'  => (int) $obj->usage_bill_time
        ];
    }
    $db->free($resql);

    return $timeSpentLines;
}

/**
 * Tell if every given time spent line belongs to a project billing its time. The invoice assignment is only
 * offered there, since it takes place in the native "Billed" column of the time spent list.
 *
 * @param  array $timeSpentLines Time spent lines returned by dolisirh_get_timespent_lines().
 * @return bool                  True if all the lines belong to a project billing its time.
 */
function dolisirh_timespent_lines_bill_time(array $timeSpentLines): bool
{
    if (empty($timeSpentLines)) {
        return false;
    }

    foreach ($timeSpentLines as $timeSpentLine) {
        if (empty($timeSpentLine['usage_bill_time'])) {
            return false;
        }
    }

    return true;
}

/**
 * Get the IDs of the time spent lines that can be billed, so assigned to an invoice service line. A time spent
 * line recorded on a task flagged as not billable is left aside.
 *
 * @param  array $timeSpentLines Time spent lines returned by dolisirh_get_timespent_lines().
 * @return array                 IDs of the billable time spent lines.
 */
function dolisirh_get_billable_timespent_ids(array $timeSpentLines): array
{
    $billableTimeSpentIDs = [];

    foreach ($timeSpentLines as $timeSpentID => $timeSpentLine) {
        if ($timeSpentLine['billable'] == 1) {
            $billableTimeSpentIDs[] = $timeSpentID;
        }
    }

    return $billableTimeSpentIDs;
}

/**
 * Get the total duration, in seconds, of a set of time spent lines.
 *
 * @param  array $timeSpentLines Time spent lines returned by dolisirh_get_timespent_lines().
 * @return int                   Total duration in seconds.
 */
function dolisirh_get_timespent_duration(array $timeSpentLines): int
{
    $totalDuration = 0;
    foreach ($timeSpentLines as $timeSpentLine) {
        $totalDuration += $timeSpentLine['element_duration'];
    }

    return $totalDuration;
}

/**
 * Convert the duration set on a product (ex : 40h) into a number of hours.
 *
 * @param  string $duration Raw duration stored on the product (llx_product.duration).
 * @return float            Number of hours, 0 if the duration cannot be computed.
 */
function dolisirh_get_product_duration_hours(string $duration): float
{
    global $db;

    static $productDurationCache = [];

    if (dol_strlen($duration) < 2) {
        return 0;
    }

    if (!isset($productDurationCache[$duration])) {
        $product                 = new Product($db);
        $product->duration_value = (float) substr($duration, 0, -1);
        $product->duration_unit  = substr($duration, -1);

        $durationHours = $product->getProductDurationHours();

        $productDurationCache[$duration] = $durationHours > 0 ? (float) $durationHours : 0;
    }

    return $productDurationCache[$duration];
}

/**
 * Get the time already consumed, in seconds, on each invoice line by time spent lines.
 *
 * @param  DoliDB $db                  Database handler.
 * @param  array  $excludeTimeSpentIDs Time spent line IDs to ignore (the ones currently being re-assigned).
 * @return array                       Consumed time in seconds, indexed by invoice line ID.
 */
function dolisirh_get_consumed_time_by_invoice_line(DoliDB $db, array $excludeTimeSpentIDs = []): array
{
    $consumedTime = [];

    $sql  = 'SELECT t.invoice_line_id, SUM(t.element_duration) as consumed_duration';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'element_time as t';
    $sql .= ' WHERE t.invoice_line_id > 0';
    if (!empty($excludeTimeSpentIDs)) {
        $sql .= ' AND t.rowid NOT IN (' . implode(',', array_map('intval', $excludeTimeSpentIDs)) . ')';
    }
    $sql .= ' GROUP BY t.invoice_line_id';

    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog('dolisirh_get_consumed_time_by_invoice_line ' . $db->lasterror(), LOG_ERR);
        return $consumedTime;
    }

    while ($obj = $db->fetch_object($resql)) {
        $consumedTime[(int) $obj->invoice_line_id] = (int) $obj->consumed_duration;
    }
    $db->free($resql);

    return $consumedTime;
}

/**
 * Get the invoice service lines holding time that has not been consumed yet by time spent.
 *
 * Only the lines linked to a service with a duration are returned : the time sold by such a line is
 * qty * product duration. A line stays available as long as the time spent already assigned to it is
 * lower than the time it sells.
 *
 * @param  DoliDB $db                   Database handler.
 * @param  int    $minAvailableDuration Minimum available time, in seconds, a line must still hold to be returned. 0 = no filter.
 * @param  array  $excludeTimeSpentIDs  Time spent line IDs to ignore when computing the consumed time.
 * @param  int    $priorityThirdPartyID Third party whose invoices are returned first.
 * @return array                        Invoice lines data, indexed by invoice line ID.
 */
function dolisirh_get_invoice_lines_with_available_time(DoliDB $db, int $minAvailableDuration = 0, array $excludeTimeSpentIDs = [], int $priorityThirdPartyID = 0): array
{
    $invoiceLines = [];

    $consumedTime = dolisirh_get_consumed_time_by_invoice_line($db, $excludeTimeSpentIDs);

    $sql  = 'SELECT fd.rowid as line_id, fd.fk_facture, fd.fk_product, fd.qty, fd.description,';
    $sql .= ' f.ref as invoice_ref, f.fk_statut as invoice_status, f.type as invoice_type, f.fk_soc,';
    $sql .= ' s.nom as thirdparty_name,';
    $sql .= ' p.ref as product_ref, p.label as product_label, p.duration as product_duration';
    $sql .= ' FROM ' . MAIN_DB_PREFIX . 'facturedet as fd';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'facture as f ON f.rowid = fd.fk_facture';
    $sql .= ' INNER JOIN ' . MAIN_DB_PREFIX . 'product as p ON p.rowid = fd.fk_product';
    $sql .= ' LEFT JOIN ' . MAIN_DB_PREFIX . 'societe as s ON s.rowid = f.fk_soc';
    $sql .= ' WHERE f.entity IN (' . getEntity('invoice') . ')';
    $sql .= ' AND fd.product_type = ' . Product::TYPE_SERVICE;
    $sql .= ' AND f.type <> ' . Facture::TYPE_CREDIT_NOTE;
    $sql .= ' AND f.fk_statut IN (' . Facture::STATUS_DRAFT . ', ' . Facture::STATUS_VALIDATED . ', ' . Facture::STATUS_CLOSED . ')';
    $sql .= " AND p.duration IS NOT NULL AND p.duration <> ''";
    $sql .= ' ORDER BY f.ref DESC, fd.rang ASC';

    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog('dolisirh_get_invoice_lines_with_available_time ' . $db->lasterror(), LOG_ERR);
        return $invoiceLines;
    }

    while ($obj = $db->fetch_object($resql)) {
        $durationHours = dolisirh_get_product_duration_hours((string) $obj->product_duration);
        if ($durationHours <= 0) {
            continue;
        }

        $lineID           = (int) $obj->line_id;
        $soldDuration     = (int) round($obj->qty * $durationHours * 3600);
        $consumedDuration = $consumedTime[$lineID] ?? 0;
        $availableTime    = $soldDuration - $consumedDuration;

        if ($availableTime <= 0 || $availableTime < $minAvailableDuration) {
            continue;
        }

        $invoiceLines[$lineID] = [
            'line_id'            => $lineID,
            'invoice_id'         => (int) $obj->fk_facture,
            'invoice_ref'        => $obj->invoice_ref,
            'invoice_status'     => (int) $obj->invoice_status,
            'invoice_type'       => (int) $obj->invoice_type,
            'thirdparty_id'      => (int) $obj->fk_soc,
            'thirdparty_name'    => $obj->thirdparty_name,
            'product_id'         => (int) $obj->fk_product,
            'product_ref'        => $obj->product_ref,
            'product_label'      => $obj->product_label,
            'description'        => $obj->description,
            'qty'                => (float) $obj->qty,
            'sold_duration'      => $soldDuration,
            'consumed_duration'  => $consumedDuration,
            'available_duration' => $availableTime
        ];
    }
    $db->free($resql);

    if ($priorityThirdPartyID > 0) {
        uasort($invoiceLines, static function (array $first, array $second) use ($priorityThirdPartyID): int {
            $firstPriority  = $first['thirdparty_id'] == $priorityThirdPartyID ? 0 : 1;
            $secondPriority = $second['thirdparty_id'] == $priorityThirdPartyID ? 0 : 1;
            if ($firstPriority != $secondPriority) {
                return $firstPriority <=> $secondPriority;
            }
            return strnatcasecmp((string) $second['invoice_ref'], (string) $first['invoice_ref']);
        });
    }

    return $invoiceLines;
}

/**
 * Get the time data of a single invoice service line.
 *
 * @param  DoliDB $db                  Database handler.
 * @param  int    $invoiceLineID       Invoice line ID (llx_facturedet rowid).
 * @param  array  $excludeTimeSpentIDs Time spent line IDs to ignore when computing the consumed time.
 * @return array                       Invoice line data, empty if the line holds no available time anymore.
 */
function dolisirh_get_invoice_line_time_data(DoliDB $db, int $invoiceLineID, array $excludeTimeSpentIDs = []): array
{
    $invoiceLines = dolisirh_get_invoice_lines_with_available_time($db, 0, $excludeTimeSpentIDs);

    return $invoiceLines[$invoiceLineID] ?? [];
}

/**
 * Build the label shown for an invoice service line into the assignment selector.
 *
 * @param  array $invoiceLine Invoice line data returned by dolisirh_get_invoice_lines_with_available_time().
 * @return string             Label of the invoice line.
 */
function dolisirh_get_invoice_line_label(array $invoiceLine): string
{
    global $langs;

    $label = $invoiceLine['invoice_ref'];
    if (!empty($invoiceLine['thirdparty_name'])) {
        $label .= ' - ' . $invoiceLine['thirdparty_name'];
    }
    $label .= ' | ' . $invoiceLine['product_ref'];
    if (!empty($invoiceLine['product_label'])) {
        $label .= ' (' . $invoiceLine['product_label'] . ')';
    }
    $label .= ' | ' . $langs->trans('AvailableTimeOnInvoiceLine') . ' : ' . convertSecondToTime($invoiceLine['available_duration'], 'allhourmin');
    $label .= ' / ' . convertSecondToTime($invoiceLine['sold_duration'], 'allhourmin');

    return $label;
}

/**
 * Assign a set of time spent lines to an invoice service line.
 *
 * @param  DoliDB $db            Database handler.
 * @param  array  $timeSpentIDs  Time spent line IDs to assign.
 * @param  int    $invoiceLineID Invoice line the time is assigned to.
 * @param  int    $invoiceID     Invoice the line belongs to.
 * @return int                   Number of assigned time spent lines, < 0 if KO.
 */
function dolisirh_assign_timespent_to_invoice_line(DoliDB $db, array $timeSpentIDs, int $invoiceLineID, int $invoiceID): int
{
    if (empty($timeSpentIDs) || $invoiceLineID <= 0 || $invoiceID <= 0) {
        return -1;
    }

    $sql  = 'UPDATE ' . MAIN_DB_PREFIX . 'element_time';
    $sql .= ' SET invoice_id = ' . $invoiceID . ', invoice_line_id = ' . $invoiceLineID;
    $sql .= " WHERE elementtype = 'task'";
    $sql .= ' AND rowid IN (' . implode(',', array_map('intval', $timeSpentIDs)) . ')';

    $resql = $db->query($sql);
    if (!$resql) {
        dol_syslog('dolisirh_assign_timespent_to_invoice_line ' . $db->lasterror(), LOG_ERR);
        return -1;
    }

    return $db->affected_rows($resql);
}
