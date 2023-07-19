<?php
/* Copyright (C) 2021-2023 EVARISK <technique@evarisk.com>
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
 */

/**
 * \file    class/dolisirhdashboard.class.php
 * \ingroup dolisirh
 * \brief   Class file for manage DolisirhDashboard.
 */

// Load DoliSIRH libraries.
require_once __DIR__ . '/../lib/dolisirh_timespent.lib.php';

/**
 * Class for DolisirhDashboard.
 */
class DolisirhDashboard
{
    /**
     * @var DoliDB Database handler.
     */
    public DoliDB $db;

    /**
     * Constructor.
     *
     * @param DoliDB $db Database handler.
     */
    public function __construct(DoliDB $db)
    {
        $this->db = $db;
    }

    /**
     * get color range for key
     *
     * @param  int $key Key to find in color array.
     * @return string
     */
    public function getColorRange(int $key): string
    {
        $colorArray = ['#f44336', '#e81e63', '#9c27b0', '#673ab7', '#3f51b5', '#2196f3', '#03a9f4', '#00bcd4', '#009688', '#4caf50', '#8bc34a', '#cddc39', '#ffeb3b', '#ffc107', '#ff9800', '#ff5722', '#795548', '#9e9e9e', '#607d8b'];
        return $colorArray[$key % count($colorArray)];
    }

    /**
     * Load dashboard info.
     *
     * @return array
     * @throws Exception
     */
    public function load_dashboard(): array
    {
        global $langs;

        $timeSpendingInfos                      = self::getTimeSpendingInfos();
        $timeSpentReport                        = self::getTimeSpentReport();
        $timeSpentCurrentMonthByTaskAndProject  = self::getTimeSpentCurrentMonthByTaskAndProject();
        $globalTimeCurrentMonthByTaskAndProject = self::getTimeSpentCurrentMonthByTaskAndProject(1);

        $array['timesheet']['widgets'] = [
            0 => [
                'label'      => [$timeSpendingInfos['planned']['label'], $timeSpendingInfos['passed']['label'], $timeSpendingInfos['spent']['label'], $timeSpendingInfos['difference']['label']],
                'content'    => [$timeSpendingInfos['planned']['content'], $timeSpendingInfos['passed']['content'], $timeSpendingInfos['spent']['content'], $timeSpendingInfos['difference']['content']],
                'picto'      => 'fas fa-clock',
                'widgetName' => $langs->transnoentities('TimeSpent')
            ],
        ];

        $array['timesheet']['graphs'] = [$timeSpentReport, $timeSpentCurrentMonthByTaskAndProject, $globalTimeCurrentMonthByTaskAndProject];

        return $array;
    }

    /**
     * Get all timespent infos.
     *
     * @return array     Widget datas label/content
     * @throws Exception
     */
    public function getTimeSpendingInfos(): array
    {
        require_once __DIR__ . '/../lib/dolisirh_function.lib.php';

        global $db, $langs, $user;

        $userID = GETPOSTISSET('search_userid') ? GETPOST('search_userid', 'int') : $user->id;
        $year   = GETPOSTISSET('search_year') ? GETPOST('search_year', 'int') : date('Y');
        $month  = GETPOSTISSET('search_month') ? GETPOST('search_month', 'int') : date('m');

        $firstdaytoshow = dol_get_first_day($year, $month);
        $lastdayofmonth = strtotime(date('Y-m-t', $firstdaytoshow));

        $currentMonth = date('m', dol_now());
        $currentYear = date('Y', dol_now());
        if ($currentMonth == $month && $currentYear == $year) {
            $currentDate   = dol_getdate(dol_now());
            $lastdaytoshow = dol_mktime(0, 0, 0, $currentDate['mon'], $currentDate['mday'], $currentDate['year']);
        } else {
            $lastdaytoshow = $lastdayofmonth;
        }

        $daysInMonth = dolisirh_num_between_day($firstdaytoshow, $lastdayofmonth, 1);

        $isavailable = [];
        for ($idw = 0; $idw < $daysInMonth; $idw++) {
            $dayInLoop =  dol_time_plus_duree($firstdaytoshow, $idw, 'd');
            if (is_day_available($dayInLoop, $userID)) {
                $isavailable[$dayInLoop] = ['morning'=>1, 'afternoon'=>1];
            } else if (date('N', $dayInLoop) >= 6) {
                $isavailable[$dayInLoop] = ['morning'=>false, 'afternoon'=>false, 'morning_reason'=>'week_end', 'afternoon_reason'=>'week_end'];
            } else {
                $isavailable[$dayInLoop] = ['morning'=>false, 'afternoon'=>false, 'morning_reason'=>'public_holiday', 'afternoon_reason'=>'public_holiday'];
            }
        }

        $workinghours = new Workinghours($db);
        $workingHours = $workinghours->fetchCurrentWorkingHours($userID, 'user');

        $timeSpendingInfos = load_time_spending_infos_within_range($firstdaytoshow, dol_time_plus_duree($lastdaytoshow, 1, 'd'), $workingHours, $isavailable, $userID);

        // Planned working time
        $planned_working_time = load_planned_time_within_range($firstdaytoshow, dol_time_plus_duree($lastdayofmonth, 1, 'd'), $workingHours, $isavailable);
        $array['planned']['label']   = $langs->trans('Total') . ' - ' . $langs->trans('ExpectedWorkingHoursMonth', dol_print_date(dol_mktime(0, 0, 0, $month, date('d'), $year), '%B %Y'));
        $array['planned']['content'] = (($planned_working_time['minutes'] != 0) ? convertSecondToTime($planned_working_time['minutes'] * 60, 'allhourmin') : '00:00');

        // Hours passed
        $passed_working_time        = $timeSpendingInfos['passed'];
        $array['passed']['label']   = $langs->trans('Total') . ' - ' . $langs->trans('SpentWorkingHoursMonth', dol_print_date($firstdaytoshow, 'dayreduceformat'), dol_print_date($lastdaytoshow, 'dayreduceformat'));
        $array['passed']['content'] = (($passed_working_time['minutes'] != 0) ? convertSecondToTime($passed_working_time['minutes'] * 60, 'allhourmin') : '00:00');

        //Working hours
        $working_time               = $timeSpendingInfos['spent'];
        $array['spent']['label']   = $langs->trans('Total') . ' - ' . $langs->trans('ConsumedWorkingHoursMonth', dol_print_date($firstdaytoshow, 'dayreduceformat'), dol_print_date($lastdaytoshow, 'dayreduceformat'));
        $array['spent']['content'] = convertSecondToTime($working_time['total'], 'allhourmin');

        //Difference between passed and working hours
        $difftotaltime                  = $timeSpendingInfos['difference'] * 60;
        $array['difference']['label']   = $langs->trans('Total') . ' - ' . $langs->trans('DiffSpentAndConsumedWorkingHoursMonth', dol_print_date($firstdaytoshow, 'dayreduceformat'), dol_print_date($lastdaytoshow, 'dayreduceformat'));
        $array['difference']['content'] = (($difftotaltime != 0) ? convertSecondToTime(abs($difftotaltime), 'allhourmin') : '00:00');

        return $array;
    }

    /**
     * Get timespent report on current year.
     *
     * @return array      Graph datas (label/color/type/title/data etc..)
     * @throws Exception
     */
    public function getTimeSpentReport(): array
    {
        require_once __DIR__ . '/../lib/dolisirh_function.lib.php';

        global $conf, $db, $langs, $user;

        $startmonth = $conf->global->SOCIETE_FISCAL_MONTH_START;

        $userID = GETPOSTISSET('search_userid') ? GETPOST('search_userid', 'int') : $user->id;
        $year   = GETPOSTISSET('search_year') ? GETPOST('search_year', 'int') : date('Y');

        // Graph Title parameters
        $array['title'] = $langs->transnoentities('TimeSpentReportByFiscalYear');
        $array['picto'] = 'clock';

        // Graph parameters
        $array['width']   = 800;
        $array['height']  = 400;
        $array['type']    = 'bars';
        $array['dataset'] = 2;

        $array['labels'] = [
            0 => [
                'label' => $langs->transnoentities('ExpectedWorkingHours'),
                'color' => '#008ECC'
            ],
            1 => [
                'label' => $langs->transnoentities('ConsumedWorkingHours'),
                'color' => '#49AF4A'
            ]
        ];

        $workinghours = new Workinghours($db);
        $workingHours = $workinghours->fetchCurrentWorkingHours($userID, 'user');

        for ($i = 1; $i < 13; $i++) {
            $firstdaytoshow = dol_get_first_day($year, $i);
            $lastdayofmonth = strtotime(date('Y-m-t', $firstdaytoshow));

            $currentMonth = date('m', dol_now());
            $currentYear  = date('Y', dol_now());
            if ($currentMonth == date('m') && $currentYear == $year) {
                $currentDate   = dol_getdate(dol_now());
                $lastdaytoshow = dol_mktime(0, 0, 0, $currentDate['mon'], $currentDate['mday'], $currentDate['year']);
            } else {
                $lastdaytoshow = $lastdayofmonth;
            }

            $daysInMonth = dolisirh_num_between_day($firstdaytoshow, $lastdayofmonth, 1);

            $isavailable = [];
            for ($idw = 0; $idw < $daysInMonth; $idw++) {
                $dayInLoop =  dol_time_plus_duree($firstdaytoshow, $idw, 'd');
                if (is_day_available($dayInLoop, $userID)) {
                    $isavailable[$dayInLoop] = ['morning'=>1, 'afternoon'=>1];
                } else if (date('N', $dayInLoop) >= 6) {
                    $isavailable[$dayInLoop] = ['morning'=>false, 'afternoon'=>false, 'morning_reason'=>'week_end', 'afternoon_reason'=>'week_end'];
                } else {
                    $isavailable[$dayInLoop] = ['morning'=>false, 'afternoon'=>false, 'morning_reason'=>'public_holiday', 'afternoon_reason'=>'public_holiday'];
                }
            }

            $planned_working_time = load_planned_time_within_range($firstdaytoshow, dol_time_plus_duree($lastdayofmonth, 1, 'd'), $workingHours, $isavailable);
            $working_time         = load_time_spent_on_tasks_within_range($firstdaytoshow, dol_time_plus_duree($lastdaytoshow, 1, 'd'), $isavailable, $userID);

            $planned_working_time_data = (($planned_working_time['minutes'] != 0) ? convertSecondToTime($planned_working_time['minutes'] * 60, 'fullhour') : 0);
            $working_time_data = convertSecondToTime($working_time['total'], 'fullhour');

            $month = $langs->transnoentitiesnoconv('MonthShort'.sprintf('%02d', $i));
			$array_key = $i - $startmonth;
			$array_key = $array_key >= 0 ? $array_key : $array_key + 12;
            $array['data'][$array_key] = [$month, $planned_working_time_data, $working_time_data];
        }
		ksort($array['data']);

		return $array;
    }

    /**
     * Get timespent on current month by task and project.
     *
     * @param  int       $showNotConsumedWorkingHours  Display not consumed working hours
     * @return array                                  Graph datas (label/color/type/title/data etc..)
     * @throws Exception
     */
    public function getTimeSpentCurrentMonthByTaskAndProject(int $showNotConsumedWorkingHours = 0): array
    {
        require_once __DIR__ . '/../lib/dolisirh_function.lib.php';

        global $db, $langs, $user;

        $userID = GETPOSTISSET('search_userid') ? GETPOST('search_userid', 'int') : $user->id;
        $year   = GETPOSTISSET('search_year') ? GETPOST('search_year', 'int') : date('Y');
        $month  = GETPOSTISSET('search_month') ? GETPOST('search_month', 'int') : date('m');

        $datasetOrder = $user->conf->DOLISIRH_TIMESPENT_DATASET_ORDER;

        // Graph Title parameters
        $array['title'] = $langs->transnoentities(($showNotConsumedWorkingHours > 0 ? 'GlobalTimeCurrentMonthByTaskAndProject' : 'TimeSpentCurrentMonthByTaskAndProject'), dol_print_date(dol_mktime(0, 0, 0, $month, date('d'), $year), '%B %Y'));
        $array['picto'] = 'projecttask';

        // Graph parameters
        $array['width']   = 800;
        $array['height']  = 400;
        $array['type']    = 'pie';
        $array['dataset'] = 2;

        $workinghours = new Workinghours($db);
        $workingHours = $workinghours->fetchCurrentWorkingHours($userID, 'user');

        $firstdaytoshow = dol_get_first_day($year, $month);
        $lastdayofmonth = strtotime(date('Y-m-t', $firstdaytoshow));

        $currentMonth = date('m', dol_now());
        $currentYear  = date('Y', dol_now());
        if ($currentMonth == $month && $currentYear == $year) {
            $currentDate   = dol_getdate(dol_now());
            $lastdaytoshow = dol_mktime(0, 0, 0, $currentDate['mon'], $currentDate['mday'], $currentDate['year']);
        } else {
            $lastdaytoshow = $lastdayofmonth;
        }

        $daysInMonth = dolisirh_num_between_day($firstdaytoshow, $lastdayofmonth, 1);

        $isavailable = [];
        for ($idw = 0; $idw < $daysInMonth; $idw++) {
            $dayInLoop =  dol_time_plus_duree($firstdaytoshow, $idw, 'd');
            if (is_day_available($dayInLoop, $userID)) {
                $isavailable[$dayInLoop] = ['morning' => 1, 'afternoon' => 1];
            } else if (date('N', $dayInLoop) >= 6) {
                $isavailable[$dayInLoop] = ['morning' => false, 'afternoon' => false, 'morning_reason' => 'week_end', 'afternoon_reason' => 'week_end'];
            } else {
                $isavailable[$dayInLoop] = ['morning' => false, 'afternoon' => false, 'morning_reason' => 'public_holiday', 'afternoon_reason' => 'public_holiday'];
            }

        }

        $timeSpentOnTasks = load_time_spent_on_tasks_within_range($firstdaytoshow, dol_time_plus_duree($lastdaytoshow, 1, 'd'), $isavailable, $userID);
        $datas = [];
        $totalTimeSpent = 0;

        if (is_array($timeSpentOnTasks) && !empty($timeSpentOnTasks)) {
            $timeSpentOnTasks = array_values($timeSpentOnTasks);
            foreach ($timeSpentOnTasks as $key => $timeSpent) {
                $timeSpentDuration = 0;
                $datas[$timeSpent['project_ref'] . ' - ' . $timeSpent['project_label']]['labels'][] = ['color' => self::getColorRange($key)];
                for ($idw = 0; $idw < $daysInMonth; $idw++) {
                    $dayInLoop = dol_time_plus_duree($firstdaytoshow, $idw, 'd');
                    $timeSpentDuration += $timeSpent[dol_print_date($dayInLoop, 'day')] / 3600;
                }
                $datas[$timeSpent['project_ref'] . ' - ' . $timeSpent['project_label']]['data'][] = [$timeSpent['task_ref'] . ' - ' . $timeSpent['task_label'], ($datasetOrder == 0 ? 0 : $timeSpentDuration), ($datasetOrder == 0 ? $timeSpentDuration : 0)];
                $datas[$timeSpent['project_ref'] . ' - ' . $timeSpent['project_label']]['timespent_duration_task'][] = $timeSpentDuration;
            }
        }

        if (is_array($datas) && !empty($datas)) {
            $array['data'] = [];
            $array['labels'] = [];
            $key2 = 0;
            foreach ($datas as $key => $data) {
                $array['labels'] = array_merge($array['labels'], $data['labels']);
                $array['data'] = array_merge($array['data'], $data['data']);
                $array['labels'][] = ['color' => self::getColorRange($key2++)];
                $array['data'][] = [$key, ($datasetOrder == 0 ? array_sum($data['timespent_duration_task']) : 0), ($datasetOrder == 0 ? 0 : array_sum($data['timespent_duration_task']))];
                $totalTimeSpent += array_sum($data['timespent_duration_task']);
            }
        }

        if ($showNotConsumedWorkingHours > 0) {
            $plannedWorkingTime = load_planned_time_within_range($firstdaytoshow, dol_time_plus_duree($lastdayofmonth, 1, 'd'), $workingHours, $isavailable);
            $plannedWorkingTimeData = (($plannedWorkingTime['minutes'] != 0) ? convertSecondToTime($plannedWorkingTime['minutes'] * 60, 'fullhour') : 0);
            $array['labels'][] = ['color' => '#008ECC'];
            $array['data'][] = [$langs->transnoentities('NotConsumedWorkingHours'), $plannedWorkingTimeData - $totalTimeSpent, $plannedWorkingTimeData - $totalTimeSpent];
        }

        return $array;
    }
}
