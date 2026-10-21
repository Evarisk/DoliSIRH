<?php
/* Copyright (C) 2021-2026 EVARISK <technique@evarisk.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 * or see https://www.gnu.org/
 */

/**
 * \file    core/modules/dolisirh/dolisirhdocuments/timesheetdocument/pdf_timesheetdocument.modules.php
 * \ingroup dolisirh
 * \brief   File of class to build the timesheet document as a native PDF.
 *          Only the tasks of the HR project are shown, split in two blocks like the DoliSIRH browser
 *          extension: Presence (work time tasks) and Absence (no work time tasks).
 */

// Load Dolibarr libraries.
require_once DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/pdf.lib.php';
require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT . '/projet/class/project.class.php';

// Load Saturne libraries.
dol_include_once('/saturne/class/saturnesignature.class.php');
dol_include_once('/saturne/core/modules/saturne/modules_saturne.php');

// Load DoliSIRH libraries.
require_once __DIR__ . '/../../../../../class/timesheet.class.php';
require_once __DIR__ . '/../../../../../class/workinghours.class.php';
require_once __DIR__ . '/../../../../../lib/dolisirh_function.lib.php';
require_once __DIR__ . '/../../../../../lib/dolisirh_timespent.lib.php';
require_once __DIR__ . '/mod_timesheetdocument_standard.php';

/**
 * Class to build the timesheet document as a native PDF.
 */
class pdf_timesheetdocument extends SaturneDocumentModel
{
    /**
     * @var string Module.
     */
    public string $module = 'dolisirh';

    /**
     * @var string Document type.
     */
    public string $document_type = 'timesheetdocument';

    /**
     * @var string Page orientation: one column per day of the month needs the landscape width.
     */
    protected string $orientation = 'L';

    /**
     * Bottom band kept for the footer, in mm: the content stops above it so they never overlap.
     */
    const FOOTER_BAND = 14;

    /**
     * @var array Colors of the document, same palette as the DoliSIRH browser extension.
     */
    protected array $colors = [
        'text'          => [30, 41, 59],
        'muted'         => [100, 116, 139],
        'border'        => [226, 232, 240],
        'head'          => [241, 245, 249],
        'light'         => [248, 250, 252],
        'offDay'        => [236, 240, 245],
        'presence'      => [16, 185, 129],
        'presenceTint'  => [236, 253, 245],
        'absence'       => [243, 156, 18],
        'absenceTint'   => [254, 245, 231],
        'summary'       => [71, 85, 105],
        'summaryTint'   => [241, 245, 249],
        'expected'      => [59, 130, 246],
        'ko'            => [239, 68, 68],
        'ok'            => [16, 185, 129],
    ];

    /**
     * @var string Left text of the footer.
     */
    protected string $footerText = '';

    /**
     * Constructor.
     *
     * @param DoliDB $db Database handler.
     */
    public function __construct(DoliDB $db)
    {
        global $langs;

        parent::__construct($db, $this->module, $this->document_type);

        $this->name        = $langs->transnoentities('TimeSheetDocumentPDF');
        $this->description = $langs->transnoentities('TimeSheetDocumentPDFDescription');
        $this->type        = 'pdf';
        $this->version     = '1.0.0';
    }

    /**
     * Return description of a module.
     *
     * @param  Translate $langs Lang object to use for output.
     * @return string           Description.
     */
    public function info(Translate $langs): string
    {
        return $this->description;
    }

    /**
     * Format a duration in hours and minutes, like 7:30 or 154:00.
     *
     * @param  int    $seconds Duration in seconds.
     * @param  bool   $signed  Prefix a positive duration with +.
     * @return string          Formatted duration.
     */
    protected function formatDuration(int $seconds, bool $signed = false): string
    {
        $sign    = $seconds < 0 ? '-' : (($signed && $seconds > 0) ? '+' : '');
        $seconds = abs($seconds);

        return $sign . floor($seconds / 3600) . ':' . sprintf('%02d', floor(($seconds % 3600) / 60));
    }

    /**
     * Usable width of the page.
     *
     * @param  TCPDF $pdf PDF handler.
     * @return float      Width between the margins.
     */
    protected function contentWidth($pdf): float
    {
        return $pdf->getPageWidth() - $this->marge_gauche - $this->marge_droite;
    }

    /**
     * Add a page when the block about to be written would not fit on the current one.
     *
     * @param  TCPDF $pdf          PDF handler.
     * @param  float $neededHeight Height the next block needs.
     * @return void
     */
    protected function checkPageBreak($pdf, float $neededHeight)
    {
        // Already at the top of a page: a new one would only leave a blank page behind.
        if ($pdf->GetY() <= $this->marge_haute + 1) {
            return;
        }

        if ($pdf->GetY() + $neededHeight + $pdf->getBreakMargin() > $pdf->getPageHeight()) {
            $pdf->AddPage($this->orientation);
            $pdf->SetY($this->marge_haute);
        }
    }

    /**
     * Load the HR project tasks configured in the module, grouped by block.
     *
     * @param  Translate $outputLangs Lang object to use for output.
     * @return array                  Tasks of the presence and absence blocks.
     */
    protected function loadHrTasks(Translate $outputLangs): array
    {
        $blocks = ['work_time' => [], 'no_work_time' => []];
        foreach (get_hr_project_tasks() as $hrProjectTask) {
            $taskID = getDolGlobalInt($hrProjectTask['code']);
            if ($taskID > 0 && isset($blocks[$hrProjectTask['type'] ?? ''])) {
                $blocks[$hrProjectTask['type']][$taskID] = $outputLangs->transnoentities($hrProjectTask['name']);
            }
        }

        $taskIDs = array_merge(array_keys($blocks['work_time']), array_keys($blocks['no_work_time']));
        if (empty($taskIDs)) {
            return $blocks;
        }

        // The label of the task wins over the default name: it may have been renamed in the project.
        $sql   = 'SELECT rowid, label FROM ' . MAIN_DB_PREFIX . 'projet_task WHERE rowid IN (' . $this->db->sanitize(implode(',', $taskIDs)) . ')';
        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                foreach ($blocks as $type => $tasks) {
                    if (isset($tasks[$obj->rowid]) && dol_strlen($obj->label) > 0) {
                        $blocks[$type][$obj->rowid] = $obj->label;
                    }
                }
            }
            $this->db->free($resql);
        }

        return $blocks;
    }

    /**
     * Load the time spent by the user on the given tasks within the period, by task then by day.
     *
     * Every line is kept, whatever the day: an absence is precisely logged on a day off.
     *
     * @param  array  $taskIDs  Tasks ID.
     * @param  int    $userID   User ID.
     * @param  string $dayStart First day, Y-m-d.
     * @param  string $dayEnd   Last day, Y-m-d.
     * @return array|int        Durations in seconds indexed by task then day, -1 on error.
     */
    protected function loadTimeSpent(array $taskIDs, int $userID, string $dayStart, string $dayEnd)
    {
        if (empty($taskIDs) || $userID <= 0) {
            return [];
        }

        $sql  = 'SELECT et.fk_element, et.element_date, SUM(et.element_duration) as duration';
        $sql .= ' FROM ' . MAIN_DB_PREFIX . 'element_time as et';
        $sql .= " WHERE et.elementtype = 'task'";
        $sql .= ' AND et.fk_element IN (' . $this->db->sanitize(implode(',', $taskIDs)) . ')';
        $sql .= ' AND et.fk_user = ' . $userID;
        $sql .= " AND et.element_date >= '" . $this->db->escape($dayStart) . "'";
        $sql .= " AND et.element_date <= '" . $this->db->escape($dayEnd) . " 23:59:59'";
        $sql .= ' GROUP BY et.fk_element, et.element_date';

        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->error = $this->db->lasterror();
            return -1;
        }

        $timeSpent = [];
        while ($obj = $this->db->fetch_object($resql)) {
            $day = substr($obj->element_date, 0, 10);
            $timeSpent[$obj->fk_element][$day] = ($timeSpent[$obj->fk_element][$day] ?? 0) + (int) $obj->duration;
        }
        $this->db->free($resql);

        return $timeSpent;
    }

    /**
     * Build the days of the period with their expected working time.
     *
     * The expected time follows the working hours of the weekday, as in the browser extension:
     * a day off is justified by an absence task, it is not removed from the expected time.
     *
     * @param  int                $dateStart    First day of the period.
     * @param  int                $dateEnd      Last day of the period.
     * @param  Workinghours|array $workingHours Working hours of the user.
     * @param  Translate          $outputLangs  Lang object to use for output.
     * @return array                            Days indexed by Y-m-d.
     */
    protected function buildDays(int $dateStart, int $dateEnd, $workingHours, Translate $outputLangs): array
    {
        $days   = [];
        $cursor = dol_mktime(0, 0, 0, (int) date('m', $dateStart), (int) date('d', $dateStart), (int) date('Y', $dateStart), 'tzserver');
        $end    = dol_mktime(0, 0, 0, (int) date('m', $dateEnd), (int) date('d', $dateEnd), (int) date('Y', $dateEnd), 'tzserver');

        // A month has 31 days at most: the bound keeps a wrong period from building a huge grid.
        for ($i = 0; $cursor <= $end && $i < 62; $i++) {
            // num_public_holiday() reads its dates in GMT: a local midnight would be checked as the day before.
            $gmtDay          = dol_mktime(0, 0, 0, (int) date('m', $cursor), (int) date('d', $cursor), (int) date('Y', $cursor), 'gmt');
            $isWeekEnd       = date('N', $cursor) >= 6;
            $isPublicHoliday = !$isWeekEnd && num_public_holiday($gmtDay, $gmtDay + 86400) > 0;

            $days[date('Y-m-d', $cursor)] = [
                'mday'     => (int) date('j', $cursor),
                'weekday'  => dol_ucfirst(dol_substr(dol_print_date($cursor, '%a', 'tzserver', $outputLangs), 0, 2)),
                'off'      => $isWeekEnd || $isPublicHoliday,
                'expected' => dolisirh_working_hours_of_day($workingHours, 'workinghours_' . strtolower(date('l', $cursor))) * 60,
            ];

            $cursor = dol_time_plus_duree($cursor, 1, 'd');
        }

        return $days;
    }

    /**
     * Header of the document: title, period, HR project, employee and manager.
     *
     * @param  TCPDF     $pdf         PDF handler.
     * @param  TimeSheet $object      Timesheet.
     * @param  array     $infos       Labels of the employee, manager, period and HR project.
     * @param  Translate $outputLangs Lang object to use for output.
     * @param  float     $size        Font size.
     * @return void
     */
    protected function drawHeader($pdf, $object, array $infos, Translate $outputLangs, float $size)
    {
        global $mysoc;

        $width = $this->contentWidth($pdf);
        $posX  = $this->marge_gauche;
        $posY  = $this->marge_haute;

        if (!getDolGlobalString('PDF_DISABLE_MYCOMPANY_LOGO') && !empty($mysoc->logo)) {
            $logoDir = getMultidirOutput($mysoc, 'mycompany') ?: '';
            $logo    = getDolGlobalInt('MAIN_PDF_USE_LARGE_LOGO') ? $logoDir . '/logos/' . $mysoc->logo : $logoDir . '/logos/thumbs/' . $mysoc->logo_small;
            if (is_readable($logo)) {
                $pdf->Image($logo, $posX, $posY, 0, 14);
                $posX = $pdf->getImageRBX() + 4;
            }
        }

        $pdf->SetTextColor($this->colors['text'][0], $this->colors['text'][1], $this->colors['text'][2]);
        $pdf->SetFont('', 'B', $size + 8);
        $pdf->SetXY($posX, $posY);
        $pdf->Cell($width * 0.6, 8, $outputLangs->transnoentities('TimeSheet') . ' - ' . $infos['month'], 0, 0, 'L');

        $pdf->SetFont('', 'B', $size + 2);
        $pdf->SetXY($this->marge_gauche + $width * 0.6, $posY);
        $pdf->Cell($width * 0.4, 8, $object->ref, 0, 0, 'R');

        $pdf->SetTextColor($this->colors['muted'][0], $this->colors['muted'][1], $this->colors['muted'][2]);
        $pdf->SetFont('', '', $size);
        $pdf->SetXY($posX, $posY + 8);
        $pdf->Cell($width * 0.6, 5, $infos['project'], 0, 0, 'L');
        $pdf->SetXY($this->marge_gauche + $width * 0.6, $posY + 8);
        $pdf->Cell($width * 0.4, 5, $infos['period'], 0, 0, 'R');

        // Information band: the employee and the manager who sign the timesheet.
        $bandY = max($posY + 16, $pdf->GetY() + 7);
        $pdf->SetDrawColor($this->colors['border'][0], $this->colors['border'][1], $this->colors['border'][2]);
        $pdf->RoundedRect($this->marge_gauche, $bandY, $width, 9, 1.5, '1111', 'DF', [], $this->colors['light']);

        $cells = [
            $outputLangs->transnoentities('TimeSheetEmployee')    => $infos['employee'],
            $outputLangs->transnoentities('TimeSheetResponsible') => $infos['responsible'],
            $outputLangs->transnoentities('TimeSheetWorkingDays') => $infos['workingDays'],
        ];
        $cellWidth = $width / count($cells);
        $cellX     = $this->marge_gauche;
        foreach ($cells as $label => $value) {
            $pdf->SetXY($cellX + 3, $bandY + 2);
            $pdf->SetFont('', '', $size - 1);
            $pdf->SetTextColor($this->colors['muted'][0], $this->colors['muted'][1], $this->colors['muted'][2]);
            $labelWidth = $pdf->GetStringWidth($label . ' : ') + 1;
            $pdf->Cell($labelWidth, 5, $label . ' : ', 0, 0, 'L');
            $pdf->SetFont('', 'B', $size - 1);
            $pdf->SetTextColor($this->colors['text'][0], $this->colors['text'][1], $this->colors['text'][2]);
            $pdf->Cell($cellWidth - $labelWidth - 6, 5, $value, 0, 0, 'L', false, '', 1);
            $cellX += $cellWidth;
        }

        $pdf->SetY($bandY + 12);
    }

    /**
     * Label of the difference between logged and expected time, as the state of the browser extension.
     *
     * @param  int       $difference  Logged minus expected time, in seconds.
     * @param  Translate $outputLangs Lang object to use for output.
     * @return string                 Missing, excess or difference label.
     */
    protected function differenceLabel(int $difference, Translate $outputLangs): string
    {
        if ($difference < 0) {
            return $outputLangs->transnoentities('TimeSheetMissing');
        }
        if ($difference > 0) {
            return $outputLangs->transnoentities('TimeSheetExcess');
        }

        return $outputLangs->transnoentities('TimeSheetDifference');
    }

    /**
     * Summary tiles of the period, like the monthly counter of the browser extension.
     *
     * @param  TCPDF     $pdf         PDF handler.
     * @param  array     $totals      Expected, presence, absence, logged and difference totals.
     * @param  Translate $outputLangs Lang object to use for output.
     * @param  float     $size        Font size.
     * @return void
     */
    protected function drawSummaryTiles($pdf, array $totals, Translate $outputLangs, float $size)
    {
        $differenceLabel = $this->differenceLabel((int) $totals['difference'], $outputLangs);
        $differenceColor = $totals['difference'] == 0 ? $this->colors['ok'] : $this->colors['ko'];

        $tiles = [
            [$outputLangs->transnoentities('TimeSheetExpected'), $this->formatDuration($totals['expected']), $this->colors['expected']],
            [$outputLangs->transnoentities('TimeSheetPresence'), $this->formatDuration($totals['presence']), $this->colors['presence']],
            [$outputLangs->transnoentities('TimeSheetAbsence'), $this->formatDuration($totals['absence']), $this->colors['absence']],
            [$outputLangs->transnoentities('TimeSheetLogged'), $this->formatDuration($totals['logged']), $this->colors['summary']],
            [$differenceLabel, $totals['difference'] == 0 ? $outputLangs->transnoentities('TimeSheetBalanced') : $this->formatDuration($totals['difference'], true), $differenceColor],
        ];

        $gap       = 3;
        $height    = 14;
        $tileWidth = ($this->contentWidth($pdf) - $gap * (count($tiles) - 1)) / count($tiles);
        $posY      = $pdf->GetY();
        $posX      = $this->marge_gauche;

        $pdf->SetDrawColor($this->colors['border'][0], $this->colors['border'][1], $this->colors['border'][2]);
        foreach ($tiles as $tile) {
            list($label, $value, $color) = $tile;

            $pdf->RoundedRect($posX, $posY, $tileWidth, $height, 1.5, '1111', 'DF', [], $this->colors['light']);
            $pdf->SetFillColor($color[0], $color[1], $color[2]);
            $pdf->Rect($posX, $posY + 2, 1.2, $height - 4, 'F');

            $pdf->SetFont('', '', $size - 1);
            $pdf->SetTextColor($this->colors['muted'][0], $this->colors['muted'][1], $this->colors['muted'][2]);
            $pdf->SetXY($posX + 4, $posY + 1.5);
            $pdf->Cell($tileWidth - 6, 4, dol_strtoupper($label), 0, 0, 'L');

            $pdf->SetFont('', 'B', $size + 4);
            $pdf->SetTextColor($color[0], $color[1], $color[2]);
            $pdf->SetXY($posX + 4, $posY + 6);
            $pdf->Cell($tileWidth - 6, 7, $value, 0, 0, 'L');

            $posX += $tileWidth + $gap;
        }

        $pdf->SetTextColor($this->colors['text'][0], $this->colors['text'][1], $this->colors['text'][2]);
        $pdf->SetY($posY + $height + 4);
    }

    /**
     * Title of a section: a colored bar, the title and its total aligned on the right.
     *
     * @param  TCPDF  $pdf          PDF handler.
     * @param  string $title        Title.
     * @param  string $right        Text aligned on the right.
     * @param  array  $color        Color of the section.
     * @param  float  $size         Font size.
     * @param  float  $neededHeight Height of the block following the title, kept on the same page.
     * @return void
     */
    protected function drawSectionTitle($pdf, string $title, string $right, array $color, float $size, float $neededHeight = 20)
    {
        $this->checkPageBreak($pdf, 8 + $neededHeight);

        $width = $this->contentWidth($pdf);
        $posY  = $pdf->GetY();

        $pdf->SetFillColor($color[0], $color[1], $color[2]);
        $pdf->Rect($this->marge_gauche, $posY + 0.5, 1.5, 6, 'F');

        $pdf->SetFont('', 'B', $size + 2);
        $pdf->SetTextColor($this->colors['text'][0], $this->colors['text'][1], $this->colors['text'][2]);
        $pdf->SetXY($this->marge_gauche + 4, $posY);
        $pdf->Cell($width * 0.6, 7, $title, 0, 0, 'L');

        $pdf->SetFont('', '', $size);
        $pdf->SetTextColor($this->colors['muted'][0], $this->colors['muted'][1], $this->colors['muted'][2]);
        $pdf->Cell($width * 0.4 - 4, 7, $right, 0, 1, 'R');

        $pdf->SetTextColor($this->colors['text'][0], $this->colors['text'][1], $this->colors['text'][2]);
        $pdf->Ln(1);
    }

    /**
     * Widths of the grid columns: the label, one column per day and the total.
     *
     * @param  TCPDF $pdf    PDF handler.
     * @param  int   $nbDays Number of days of the period.
     * @return array         Width of the label, day and total columns.
     */
    protected function gridWidths($pdf, int $nbDays): array
    {
        $width       = $this->contentWidth($pdf);
        $totalWidth  = 16;
        $dayWidth    = min(12, ($width - 52 - $totalWidth) / max(1, $nbDays));
        $labelWidth  = $width - $totalWidth - $dayWidth * $nbDays;

        return [$labelWidth, $dayWidth, $totalWidth];
    }

    /**
     * Header row of a grid: the weekday and the day number of each day.
     *
     * @param  TCPDF     $pdf         PDF handler.
     * @param  array     $days        Days of the period.
     * @param  string    $label       Label of the first column.
     * @param  Translate $outputLangs Lang object to use for output.
     * @param  float     $size        Font size.
     * @return void
     */
    protected function drawGridHeader($pdf, array $days, string $label, Translate $outputLangs, float $size)
    {
        list($labelWidth, $dayWidth, $totalWidth) = $this->gridWidths($pdf, count($days));

        $height = 7;
        $posY   = $pdf->GetY();

        $pdf->SetFont('', 'B', $size - 1);
        $pdf->SetTextColor($this->colors['muted'][0], $this->colors['muted'][1], $this->colors['muted'][2]);
        $pdf->SetFillColor($this->colors['head'][0], $this->colors['head'][1], $this->colors['head'][2]);
        $pdf->MultiCell($labelWidth, $height, $label, 1, 'L', true, 0, $this->marge_gauche, $posY, true, 0, false, true, $height, 'M');

        $pdf->SetFont('', '', $size - 2.5);
        foreach ($days as $day) {
            $fill = $day['off'] ? $this->colors['offDay'] : $this->colors['head'];
            $pdf->SetFillColor($fill[0], $fill[1], $fill[2]);
            $pdf->MultiCell($dayWidth, $height, $day['weekday'] . "\n" . $day['mday'], 1, 'C', true, 0, null, null, true, 0, false, true, $height, 'M');
        }

        $pdf->SetFont('', 'B', $size - 1);
        $pdf->SetFillColor($this->colors['head'][0], $this->colors['head'][1], $this->colors['head'][2]);
        $pdf->MultiCell($totalWidth, $height, $outputLangs->transnoentities('Total'), 1, 'C', true, 1, null, null, true, 0, false, true, $height, 'M');

        $pdf->SetTextColor($this->colors['text'][0], $this->colors['text'][1], $this->colors['text'][2]);
    }

    /**
     * Grid of durations: one row per line, one column per day, a total column.
     *
     * A row is ['label' => string, 'values' => [Y-m-d => seconds], 'total' => seconds, 'style' => string]
     * where style is 'line', 'subtotal' or 'difference'.
     *
     * @param  TCPDF     $pdf         PDF handler.
     * @param  array     $days        Days of the period.
     * @param  array     $rows        Rows of the grid.
     * @param  string    $label       Label of the first column.
     * @param  array     $tint        Fill color of the subtotal rows.
     * @param  Translate $outputLangs Lang object to use for output.
     * @param  float     $size        Font size.
     * @return void
     */
    protected function drawGrid($pdf, array $days, array $rows, string $label, array $tint, Translate $outputLangs, float $size)
    {
        list($labelWidth, $dayWidth, $totalWidth) = $this->gridWidths($pdf, count($days));

        $cellPaddings = $pdf->getCellPaddings();
        $pdf->setCellPaddings(0.6, 0, 0.6, 0);
        $pdf->SetDrawColor($this->colors['border'][0], $this->colors['border'][1], $this->colors['border'][2]);
        $pdf->SetLineWidth(0.15);

        $this->drawGridHeader($pdf, $days, $label, $outputLangs, $size);

        foreach ($rows as $row) {
            $style     = $row['style'] ?? 'line';
            $isTotal   = $style != 'line';
            $rowFill   = $isTotal ? $tint : [255, 255, 255];
            $fontStyle = $isTotal ? 'B' : '';

            $pdf->SetFont('', $fontStyle, $size - 1);
            $height = max(5, $pdf->getStringHeight($labelWidth, $row['label']) + 1);

            // The header goes back on top of each continuation page, otherwise the days are unreadable.
            $pageBefore = $pdf->getPage();
            $this->checkPageBreak($pdf, $height);
            if ($pdf->getPage() != $pageBefore) {
                $this->drawGridHeader($pdf, $days, $label, $outputLangs, $size);
            }

            $posY = $pdf->GetY();
            $pdf->SetFillColor($rowFill[0], $rowFill[1], $rowFill[2]);
            $pdf->MultiCell($labelWidth, $height, $row['label'], 1, 'L', true, 0, $this->marge_gauche, $posY, true, 0, false, true, $height, 'M');

            $pdf->SetFont('', $fontStyle, $size - 2.5);
            foreach ($days as $dayKey => $day) {
                $value = (int) ($row['values'][$dayKey] ?? 0);
                $fill  = ($day['off'] && !$isTotal) ? $this->colors['offDay'] : $rowFill;
                $pdf->SetFillColor($fill[0], $fill[1], $fill[2]);

                if ($style == 'difference' && $value != 0) {
                    $pdf->SetTextColor($this->colors['ko'][0], $this->colors['ko'][1], $this->colors['ko'][2]);
                }
                $pdf->Cell($dayWidth, $height, $value != 0 ? $this->formatDuration($value, $style == 'difference') : '', 1, 0, 'C', true);
                $pdf->SetTextColor($this->colors['text'][0], $this->colors['text'][1], $this->colors['text'][2]);
            }

            $total = (int) $row['total'];
            if ($style == 'difference') {
                $color = $total == 0 ? $this->colors['ok'] : $this->colors['ko'];
                $pdf->SetTextColor($color[0], $color[1], $color[2]);
            }
            $pdf->SetFont('', 'B', $size - 1);
            $pdf->SetFillColor($rowFill[0], $rowFill[1], $rowFill[2]);
            $pdf->Cell($totalWidth, $height, $this->formatDuration($total, $style == 'difference'), 1, 1, 'C', true);
            $pdf->SetTextColor($this->colors['text'][0], $this->colors['text'][1], $this->colors['text'][2]);
        }

        $pdf->setCellPaddings($cellPaddings['L'], $cellPaddings['T'], $cellPaddings['R'], $cellPaddings['B']);
        $pdf->Ln(4);
    }

    /**
     * Block of HR tasks: its title with the block total, then the grid of its tasks.
     *
     * @param  TCPDF     $pdf         PDF handler.
     * @param  array     $days        Days of the period.
     * @param  array     $tasks       Tasks of the block, label indexed by task ID.
     * @param  array     $timeSpent   Time spent indexed by task then day.
     * @param  string    $title       Title of the block.
     * @param  string    $totalLabel  Label of the subtotal row.
     * @param  array     $color       Color of the block.
     * @param  array     $tint        Fill color of the subtotal row.
     * @param  Translate $outputLangs Lang object to use for output.
     * @param  float     $size        Font size.
     * @return array                  Durations of the block by day.
     */
    protected function drawTaskBlock($pdf, array $days, array $tasks, array $timeSpent, string $title, string $totalLabel, array $color, array $tint, Translate $outputLangs, float $size): array
    {
        $rows       = [];
        $blockByDay = [];
        foreach ($tasks as $taskID => $taskLabel) {
            $values = [];
            foreach ($days as $dayKey => $day) {
                $values[$dayKey]      = (int) ($timeSpent[$taskID][$dayKey] ?? 0);
                $blockByDay[$dayKey] = ($blockByDay[$dayKey] ?? 0) + $values[$dayKey];
            }
            $rows[] = ['label' => $taskLabel, 'values' => $values, 'total' => array_sum($values), 'style' => 'line'];
        }

        $blockTotal = array_sum($blockByDay);
        $rows[]     = ['label' => $totalLabel, 'values' => $blockByDay, 'total' => $blockTotal, 'style' => 'subtotal'];

        // A block holds a dozen rows at most: it is kept on a single page.
        $this->drawSectionTitle($pdf, $title, $outputLangs->transnoentities('TimeSheetLogged') . ' : ' . $this->formatDuration($blockTotal), $color, $size, 9 + 5 * count($rows));
        if (empty($tasks)) {
            $pdf->SetFont('', 'I', $size - 1);
            $pdf->SetTextColor($this->colors['muted'][0], $this->colors['muted'][1], $this->colors['muted'][2]);
            $pdf->Cell($this->contentWidth($pdf), 6, $outputLangs->transnoentities('TimeSheetNoTaskConfigured'), 0, 1, 'L');
            $pdf->SetTextColor($this->colors['text'][0], $this->colors['text'][1], $this->colors['text'][2]);
            $pdf->Ln(3);
            return $blockByDay;
        }

        $this->drawGrid($pdf, $days, $rows, $outputLangs->transnoentities('Task'), $tint, $outputLangs, $size);

        return $blockByDay;
    }

    /**
     * Payroll items of the timesheet: the products and services lines with their quantity.
     *
     * @param  TCPDF     $pdf         PDF handler.
     * @param  TimeSheet $object      Timesheet.
     * @param  Translate $outputLangs Lang object to use for output.
     * @param  float     $size        Font size.
     * @return void
     */
    protected function drawPayrollItems($pdf, $object, Translate $outputLangs, float $size)
    {
        if (empty($object->id)) {
            return;
        }

        $object->fetchLines();
        if (!is_array($object->lines) || empty($object->lines)) {
            return;
        }

        $items   = [];
        $product = new Product($this->db);
        foreach ($object->lines as $line) {
            if ($line->fk_product > 0 && $product->fetch($line->fk_product) > 0) {
                $items[] = [$product->label, $line->qty];
            } elseif (dol_strlen($line->description) > 0) {
                $items[] = [dol_string_nohtmltag($line->description, 0), $line->qty];
            }
        }
        if (empty($items)) {
            return;
        }

        // Two items per row: the list stays short next to the grids.
        $width      = $this->contentWidth($pdf);
        $pairWidth  = ($width - 4) / 2;
        $qtyWidth   = 22;
        $labelWidth = $pairWidth - $qtyWidth;
        $height     = 5.5;
        $nbRows     = (int) ceil(count($items) / 2);

        $this->drawSectionTitle($pdf, $outputLangs->transnoentities('TimeSheetPayrollItems'), '', $this->colors['summary'], $size, $height * $nbRows);

        $pdf->SetDrawColor($this->colors['border'][0], $this->colors['border'][1], $this->colors['border'][2]);
        $pdf->SetLineWidth(0.15);
        for ($i = 0; $i < $nbRows; $i++) {
            $posY = $pdf->GetY();
            for ($j = 0; $j < 2; $j++) {
                $item = $items[$i * 2 + $j] ?? null;
                if ($item === null) {
                    continue;
                }
                $posX = $this->marge_gauche + $j * ($pairWidth + 4);
                $pdf->SetFillColor($this->colors['light'][0], $this->colors['light'][1], $this->colors['light'][2]);
                $pdf->SetFont('', '', $size - 1);
                $pdf->SetXY($posX, $posY);
                $pdf->Cell($labelWidth, $height, $item[0], 1, 0, 'L', true, '', 1);
                $pdf->SetFont('', 'B', $size - 1);
                $pdf->Cell($qtyWidth, $height, (string) price2num((float) $item[1]), 1, 0, 'C');
            }
            $pdf->SetY($posY + $height);
        }

        $pdf->Ln(4);
    }

    /**
     * Public note of the timesheet.
     *
     * @param  TCPDF     $pdf         PDF handler.
     * @param  TimeSheet $object      Timesheet.
     * @param  Translate $outputLangs Lang object to use for output.
     * @param  float     $size        Font size.
     * @return void
     */
    protected function drawNote($pdf, $object, Translate $outputLangs, float $size)
    {
        $note = trim(dol_string_nohtmltag((string) $object->note_public, 0));
        if (!dol_strlen($note)) {
            return;
        }

        $width = $this->contentWidth($pdf);
        $pdf->SetFont('', '', $size - 1);
        $height = $pdf->getStringHeight($width - 6, $note) + 4;

        $this->drawSectionTitle($pdf, $outputLangs->transnoentities('NotePublic'), '', $this->colors['summary'], $size, $height);

        $posY = $pdf->GetY();
        $pdf->SetDrawColor($this->colors['border'][0], $this->colors['border'][1], $this->colors['border'][2]);
        $pdf->RoundedRect($this->marge_gauche, $posY, $width, $height, 1.5, '1111', 'DF', [], $this->colors['light']);
        $pdf->MultiCell($width - 6, $height - 4, $note, 0, 'L', false, 1, $this->marge_gauche + 3, $posY + 2);
        $pdf->SetY($posY + $height + 4);
    }

    /**
     * Signatures of the employee and of the manager, side by side.
     *
     * @param  TCPDF     $pdf         PDF handler.
     * @param  array     $signatories Employee and manager signatories.
     * @param  array     $moreParam   More param (specimen, etc).
     * @param  Translate $outputLangs Lang object to use for output.
     * @param  float     $size        Font size.
     * @return void
     */
    protected function drawSignatures($pdf, array $signatories, array $moreParam, Translate $outputLangs, float $size)
    {
        $height = 36;
        $gap    = 6;
        $width  = ($this->contentWidth($pdf) - $gap) / 2;

        $this->drawSectionTitle($pdf, $outputLangs->transnoentities('TimeSheetSignatures'), '', $this->colors['summary'], $size, $height);

        $posY = $pdf->GetY();
        $posX = $this->marge_gauche;

        $pdf->SetDrawColor($this->colors['border'][0], $this->colors['border'][1], $this->colors['border'][2]);
        foreach ($signatories as $title => $signatory) {
            $pdf->RoundedRect($posX, $posY, $width, $height, 1.5, '1111', 'D');

            $pdf->SetFont('', '', $size - 1);
            $pdf->SetTextColor($this->colors['muted'][0], $this->colors['muted'][1], $this->colors['muted'][2]);
            $pdf->SetXY($posX + 3, $posY + 2);
            $pdf->Cell($width - 6, 4, dol_strtoupper($title), 0, 0, 'L');

            $pdf->SetFont('', 'B', $size);
            $pdf->SetTextColor($this->colors['text'][0], $this->colors['text'][1], $this->colors['text'][2]);
            $pdf->SetXY($posX + 3, $posY + 6);
            $pdf->Cell($width - 6, 5, is_object($signatory) ? strtoupper($signatory->lastname) . ' ' . ucfirst($signatory->firstname) : '', 0, 0, 'L');

            $signed = is_object($signatory) && dol_strlen($signatory->signature) > 0 && $signatory->signature != $outputLangs->transnoentities('FileGenerated');
            $pdf->SetFont('', '', $size - 1);
            $pdf->SetTextColor($this->colors['muted'][0], $this->colors['muted'][1], $this->colors['muted'][2]);
            $pdf->SetXY($posX + 3, $posY + 11);
            $pdf->Cell($width - 6, 4, $signed ? $outputLangs->transnoentities('TimeSheetSignedOn', dol_print_date($signatory->signature_date, 'dayhour', 'tzuser')) : $outputLangs->transnoentities('TimeSheetWaitingSignature'), 0, 0, 'L');

            $showSignature = empty($moreParam['specimen']) || getDolGlobalInt('DOLISIRH_SHOW_SIGNATURE_SPECIMEN') == 1;
            if ($signed && $showSignature) {
                $encodedImage = explode(',', $signatory->signature);
                $decodedImage = base64_decode($encodedImage[1] ?? $encodedImage[0]);
                if (!empty($decodedImage)) {
                    $pdf->Image('@' . $decodedImage, $posX + 3, $posY + 16, $width - 6, $height - 18, '', '', '', false, 300, '', false, false, 0, 'CM');
                }
            }

            $posX += $width + $gap;
        }

        $pdf->SetTextColor($this->colors['text'][0], $this->colors['text'][1], $this->colors['text'][2]);
        $pdf->SetY($posY + $height + 4);
    }

    /**
     * Write the footer on every page, once the content is written.
     *
     * In a final pass: TCPDF opens pages by itself when a block overflows, and the total number of
     * pages is only known here.
     *
     * @param  TCPDF $pdf PDF handler.
     * @return void
     */
    protected function writeFooter($pdf)
    {
        $currentPage = $pdf->getPage();
        $currentY    = $pdf->GetY();
        $totalPages  = $pdf->getNumPages();
        $halfWidth   = $this->contentWidth($pdf) / 2;

        for ($page = 1; $page <= $totalPages; $page++) {
            $pdf->setPage($page);
            // setPage restores the page settings, font and automatic break included: both are set again,
            // otherwise the footer written in the bottom band would be pushed on a new page.
            $pdf->SetAutoPageBreak(false);
            $pdf->SetFont('', '', 7);
            $pdf->SetTextColor($this->colors['muted'][0], $this->colors['muted'][1], $this->colors['muted'][2]);
            $pdf->SetXY($this->marge_gauche, $pdf->getPageHeight() - 10);
            $pdf->Cell($halfWidth, 5, $this->footerText, 0, 0, 'L');
            $pdf->Cell($halfWidth, 5, $page . ' / ' . $totalPages, 0, 0, 'R');
        }

        $pdf->SetTextColor($this->colors['text'][0], $this->colors['text'][1], $this->colors['text'][2]);
        $pdf->setPage($currentPage);
        $pdf->SetXY($this->marge_gauche, $currentY);
        $pdf->SetAutoPageBreak(true, self::FOOTER_BAND);
    }

    /**
     * Function to build a document on disk.
     *
     * @param  SaturneDocuments $objectDocument  Object source to build document.
     * @param  Translate        $outputLangs     Lang object to use for output.
     * @param  string           $srcTemplatePath Unused, this model holds its own layout.
     * @param  int              $hideDetails     Do not show line details.
     * @param  int              $hideDesc        Do not show desc.
     * @param  int              $hideRef         Do not show ref.
     * @param  array            $moreParam       More param (Object/user/etc).
     * @return int                               1 if OK, <=0 if KO.
     * @throws Exception
     */
    public function write_file(SaturneDocuments $objectDocument, Translate $outputLangs, string $srcTemplatePath = '', int $hideDetails = 0, int $hideDesc = 0, int $hideRef = 0, array $moreParam = []): int
    {
        global $action, $hookmanager, $user;

        $moreParam = self::getMoreParam($objectDocument, $moreParam);

        // Saturne has no timesheet to give to the specimen of the admin page: an empty one is built here.
        $object = $moreParam['object'] ?? null;
        if (!($object instanceof TimeSheet) && !empty($moreParam['specimen'])) {
            $object = new TimeSheet($this->db);
        }
        if (!($object instanceof TimeSheet)) {
            $this->error = 'ErrorMissingTimeSheet';
            return -1;
        }

        // An empty timesheet has no user and no period: it shows the current month.
        $userAssignID = (int) ($object->fk_user_assign ?? 0);
        $dateStart    = !empty($object->date_start) ? (int) $object->date_start : dol_get_first_day((int) date('Y'), (int) date('m'));
        $dateEnd      = !empty($object->date_end) ? (int) $object->date_end : dol_get_last_day((int) date('Y'), (int) date('m'));
        if (empty($moreParam['user'])) {
            $moreParam['user'] = $user;
        }

        $outputLangs->charset_output = 'UTF-8';
        $outputLangs->loadLangs(['main', 'dict', 'projects', 'products', 'dolisirh@dolisirh']);

        // HR tasks first: without them the timesheet has nothing to show, no document reference is burnt.
        $hrTasks = $this->loadHrTasks($outputLangs);
        if (empty($hrTasks['work_time']) && empty($hrTasks['no_work_time'])) {
            $this->error = $outputLangs->transnoentities('ErrorConfigProjectPage');
            return -1;
        }

        $employee = new User($this->db);
        if ($userAssignID > 0) {
            $employee->fetch($userAssignID);
        }

        $moreParam['hideTemplateName'] = 1;
        $moreParam['documentName']     = $outputLangs->transnoentities('timesheetdocument') . '_' . ($userAssignID > 0 ? strtoupper($employee->lastname) . '_' . ucfirst($employee->firstname) . '_' : '');

        $file = $this->buildDocumentFilename($objectDocument, $outputLangs, $object, $moreParam);
        if (!is_string($file) || empty($file)) {
            $this->error = $outputLangs->transnoentities('ErrorFileNameCanNotBeBuilt');
            return -1;
        }

        $hookmanager->initHooks(['pdfgeneration']);
        $parameters = ['file' => $file, 'object' => $object, 'outputlangs' => $outputLangs];
        $hookmanager->executeHooks('beforePDFCreation', $parameters, $object, $action);

        // Data of the period.
        $workingHours = new Workinghours($this->db);
        $workingHours = $workingHours->fetchCurrentWorkingHours($userAssignID, 'user');

        $days      = $this->buildDays($dateStart, $dateEnd, $workingHours, $outputLangs);
        $dayKeys   = array_keys($days);
        $timeSpent = $this->loadTimeSpent(array_merge(array_keys($hrTasks['work_time']), array_keys($hrTasks['no_work_time'])), $userAssignID, reset($dayKeys) ?: '', end($dayKeys) ?: '');
        if (!is_array($timeSpent)) {
            return -1;
        }

        $project = new Project($this->db);
        $projectLabel = '';
        if (getDolGlobalInt('DOLISIRH_HR_PROJECT') > 0 && $project->fetch(getDolGlobalInt('DOLISIRH_HR_PROJECT')) > 0) {
            $projectLabel = $outputLangs->transnoentities('HRProject') . ' : ' . $project->ref . ' - ' . $project->title;
        }

        $signatory   = new SaturneSignature($this->db, $this->module, $object->element);
        $signatories = [];
        foreach (['Signatory' => 'TimeSheetEmployee', 'Responsible' => 'TimeSheetResponsible'] as $role => $titleKey) {
            $roleSignatories = $object->id > 0 ? $signatory->fetchSignatory($role, $object->id, $object->element) : [];
            $signatories[$outputLangs->transnoentities($titleKey)] = (is_array($roleSignatories) && !empty($roleSignatories)) ? array_shift($roleSignatories) : null;
        }

        $responsibleName = '';
        $responsible     = $signatories[$outputLangs->transnoentities('TimeSheetResponsible')];
        if (is_object($responsible)) {
            $responsibleName = strtoupper($responsible->lastname) . ' ' . ucfirst($responsible->firstname);
        }

        $workingDays = 0;
        foreach ($days as $day) {
            $workingDays += $day['expected'] > 0 ? 1 : 0;
        }

        $infos = [
            'month'       => dol_ucfirst(dol_print_date(dol_mktime(0, 0, 0, (int) date('m', $dateStart), 1, (int) date('Y', $dateStart), 'tzserver'), '%B %Y', 'tzserver', $outputLangs)),
            'project'     => $projectLabel,
            'period'      => $outputLangs->transnoentities('TimeSheetPeriod', dol_print_date($dateStart, 'day', 'tzserver', $outputLangs), dol_print_date($dateEnd, 'day', 'tzserver', $outputLangs)),
            'employee'    => strtoupper($employee->lastname) . ' ' . ucfirst($employee->firstname),
            'responsible' => $responsibleName,
            'workingDays' => $workingDays,
        ];

        // PDF: pdf_getInstance() loads the TCPDF library and its constants, a plain TCPDF is enough as no PDF is imported.
        pdf_getInstance($this->format);
        $pdf  = new TCPDF($this->orientation, 'mm', $this->format, true, 'UTF-8', false, getDolGlobalInt('PDF_USE_A'));
        $size = pdf_getPDFFontSize($outputLangs);

        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetFont(pdf_getPDFFont($outputLangs));
        $pdf->Open();
        $pdf->SetTitle($outputLangs->convToOutputCharset($outputLangs->transnoentities('TimeSheet') . ' ' . $object->ref));
        $pdf->SetSubject($outputLangs->transnoentities('TimeSheet'));
        $pdf->SetCreator('Dolibarr ' . DOL_VERSION);
        $pdf->SetAuthor($outputLangs->convToOutputCharset($moreParam['user']->getFullName($outputLangs)));
        $pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
        $pdf->SetAutoPageBreak(true, self::FOOTER_BAND);

        $pdf->AddPage($this->orientation);
        $pdf->SetFont(pdf_getPDFFont($outputLangs), '', $size);
        $pdf->SetLineWidth(0.15);

        $this->footerText = implode(' - ', array_filter([(string) $object->ref, $outputLangs->transnoentities('TimeSheetGeneratedOn', dol_print_date(dol_now(), 'dayhour', 'tzuser'))]));

        $this->drawHeader($pdf, $object, $infos, $outputLangs, $size);

        // The tiles need the block totals: they are computed before the blocks are drawn.
        $totals = ['expected' => 0, 'presence' => 0, 'absence' => 0];
        foreach ($days as $day) {
            $totals['expected'] += $day['expected'];
        }
        foreach (['work_time' => 'presence', 'no_work_time' => 'absence'] as $type => $totalKey) {
            foreach (array_keys($hrTasks[$type]) as $taskID) {
                $totals[$totalKey] += array_sum($timeSpent[$taskID] ?? []);
            }
        }
        $totals['logged']     = $totals['presence'] + $totals['absence'];
        $totals['difference'] = $totals['logged'] - $totals['expected'];

        $this->drawSummaryTiles($pdf, $totals, $outputLangs, $size);

        $presenceByDay = $this->drawTaskBlock($pdf, $days, $hrTasks['work_time'], $timeSpent, $outputLangs->transnoentities('TimeSheetPresenceBlock'), $outputLangs->transnoentities('TimeSheetTotalPresence'), $this->colors['presence'], $this->colors['presenceTint'], $outputLangs, $size);
        $absenceByDay  = $this->drawTaskBlock($pdf, $days, $hrTasks['no_work_time'], $timeSpent, $outputLangs->transnoentities('TimeSheetAbsenceBlock'), $outputLangs->transnoentities('TimeSheetTotalAbsence'), $this->colors['absence'], $this->colors['absenceTint'], $outputLangs, $size);

        // Summary of each day: what was expected, what was logged and the difference.
        $expectedByDay   = [];
        $loggedByDay     = [];
        $differenceByDay = [];
        foreach ($days as $dayKey => $day) {
            $expectedByDay[$dayKey]   = $day['expected'];
            $loggedByDay[$dayKey]     = ($presenceByDay[$dayKey] ?? 0) + ($absenceByDay[$dayKey] ?? 0);
            $differenceByDay[$dayKey] = $loggedByDay[$dayKey] - $day['expected'];
        }

        $summaryRows = [
            ['label' => $outputLangs->transnoentities('TimeSheetExpectedTime'), 'values' => $expectedByDay, 'total' => $totals['expected'], 'style' => 'line'],
            ['label' => $outputLangs->transnoentities('TimeSheetTotalLogged'), 'values' => $loggedByDay, 'total' => $totals['logged'], 'style' => 'subtotal'],
            ['label' => $outputLangs->transnoentities('TimeSheetDifference'), 'values' => $differenceByDay, 'total' => $totals['difference'], 'style' => 'difference'],
        ];

        // Same state line as the section headers of the browser extension: expected | logged | state.
        $summaryState = $outputLangs->transnoentities('TimeSheetExpected') . ' : ' . $this->formatDuration($totals['expected']);
        $summaryState .= '  |  ' . $outputLangs->transnoentities('TimeSheetLogged') . ' : ' . $this->formatDuration($totals['logged']);
        $summaryState .= '  |  ' . $this->differenceLabel((int) $totals['difference'], $outputLangs) . ' : ' . $this->formatDuration($totals['difference'], true);
        $this->drawSectionTitle($pdf, $outputLangs->transnoentities('TimeSheetSummary'), $summaryState, $this->colors['summary'], $size, 9 + 5 * count($summaryRows));
        $this->drawGrid($pdf, $days, $summaryRows, '', $this->colors['summaryTint'], $outputLangs, $size);

        $this->drawPayrollItems($pdf, $object, $outputLangs, $size);
        $this->drawNote($pdf, $object, $outputLangs, $size);
        $this->drawSignatures($pdf, $signatories, $moreParam, $outputLangs, $size);

        $this->writeFooter($pdf);

        $pdf->Close();

        try {
            $pdf->Output($file, 'F');
        } catch (Exception $exception) {
            $this->error = $exception->getMessage();
            dol_syslog($this->error, LOG_ERR);
            return -1;
        }

        $hookmanager->initHooks(['pdfgeneration']);
        $parameters = ['file' => $file, 'object' => $object, 'outputlangs' => $outputLangs];
        $hookmanager->executeHooks('afterPDFCreation', $parameters, $this, $action);

        dolChmod($file);

        $this->result = ['fullpath' => $file];

        return 1;
    }
}
