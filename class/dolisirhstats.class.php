<?php
/* Copyright (C) 2023 EVARISK <dev@evarisk.com>
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
 *  \file       class/dolisirhstats.class.php
 *  \ingroup    dolisirh
 *  \brief      DoliSIRH class to manage statistics reports
 */

/**
 * 	Parent class of statistics class
 */
abstract class DoliSIRHStats
{
	/**
	 * @var DoliDB Database handler.
	 */
	protected $db;

	/**
	 * @var array Dates of cache file read by methods.
	 */
	protected $lastfetchdate = array();

	/**
	 * @var string Suffix to add to name of cache file (to avoid file name conflicts)
	 */
	public $cachefilesuffix = '';

	/**
	 * Return nb of elements by month for several years
	 *
	 * @param  int       $endyear    End year
	 * @param  int       $startyear  Start year
	 * @param  int       $cachedelay Delay we accept for cache file (0=No read, no save of cache, -1=No read but save)
	 * @param  int       $format     0=Label of abscissa is a translated text, 1=Label of abscissa is month number, 2=Label of abscissa is first letter of month
	 * @param  int       $startmonth Month of the fiscal year start min 1 max 12 ; if 1 = january
	 * @return array|int             Array of values
	 * @throws Exception
	 */
	public function getNbByMonthWithPrevYear(int $endyear, int $startyear, int $cachedelay = 0, int $format = 0, int $startmonth = 1)
	{
		global $conf, $user, $langs;

		if ($startyear > $endyear) {
			return -1;
		}

		$datay = array();

		// Search into cache
		if (!empty($cachedelay)) {
			include_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
			include_once DOL_DOCUMENT_ROOT.'/core/lib/json.lib.php';
		}

		$newpathofdestfile = $conf->user->dir_temp.'/'.get_class($this).'_'.__FUNCTION__.'_'.(empty($this->cachefilesuffix) ? '' : $this->cachefilesuffix.'_').$langs->defaultlang.'_entity.'.$conf->entity.'_user'.$user->id.'.cache';
		$newmask = '0644';

		$nowgmt = dol_now();

		$foundintocache = 0;
		if ($cachedelay > 0) {
			$filedate = dol_filemtime($newpathofdestfile);
			if ($filedate >= ($nowgmt - $cachedelay)) {
				$foundintocache = 1;

				$this->lastfetchdate[get_class($this).'_'.__FUNCTION__] = $filedate;
			} else {
				dol_syslog(get_class($this).'::'.__FUNCTION__. ' cache file ' .$newpathofdestfile. ' is not found or older than now - cachedelay (' .$nowgmt. ' - ' .$cachedelay.") so we can't use it.");
			}
		}
		// Load file into $data
		if ($foundintocache) {    // Cache file found and is not too old
			if (!empty($filedate)) {
				dol_syslog(get_class($this).'::'.__FUNCTION__. ' read data from cache file ' .$newpathofdestfile. ' ' .$filedate. '.');
			}
			$data = json_decode(file_get_contents($newpathofdestfile), true);
		} else {
			$year = $startyear;
			$sm = $startmonth - 1;
			if ($sm != 0) {
				$year = $year - 1;
			}
			while ($year <= $endyear) {
				$datay[$year] = $this->getNbByMonth($year, $format);
				$year++;
			}

			$data = array();

			for ($i = 0; $i < 12; $i++) {
				$data[$i][] = $datay[$endyear][($i + $sm) % 12][0];
				$year = $startyear;
				while ($year <= $endyear) {
					$data[$i][] = $datay[$year][($i + $sm) % 12][1];
					$year++;
				}
			}
		}

		// Save cache file
		if (empty($foundintocache) && ($cachedelay > 0 || $cachedelay == -1)) {
			dol_syslog(get_class($this).'::'.__FUNCTION__. ' save cache file ' .$newpathofdestfile. ' onto disk.');
			if (!dol_is_dir($conf->user->dir_temp)) {
				dol_mkdir($conf->user->dir_temp);
			}
			$fp = fopen($newpathofdestfile, 'w');
			fwrite($fp, json_encode($data));
			fclose($fp);
			if (!empty($conf->global->MAIN_UMASK)) {
				$newmask = $conf->global->MAIN_UMASK;
			}
			@chmod($newpathofdestfile, octdec($newmask));

			$this->lastfetchdate[get_class($this).'_'.__FUNCTION__] = $nowgmt;
		}

		// return array(array('Month',val1,val2,val3),...)
		return $data;
	}

	/**
	 * Return amount of elements by month for several years.
	 *
	 * @param  int       $endyear    End year
	 * @param  int       $startyear  Start year
	 * @param  int       $cachedelay Delay we accept for cache file (0=No read, no save of cache, -1=No read but save)
	 * @param  int       $format     0=Label of abscissa is a translated text, 1=Label of abscissa is month number, 2=Label of abscissa is first letter of month
	 * @param  int       $startmonth Month of the fiscal year start min 1 max 12 ; if 1 = january
	 * @return array|int             Array of values
	 * @throws Exception
	 */
	public function getAmountByMonthWithPrevYear(int $endyear, int $startyear, int $cachedelay = 0, int $format = 0, int $startmonth = 1)
	{
		global $conf, $user, $langs;

		if ($startyear > $endyear) {
			return -1;
		}

		$datay = array();

		// Search into cache
		if (!empty($cachedelay)) {
			include_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
			include_once DOL_DOCUMENT_ROOT.'/core/lib/json.lib.php';
		}

		$newpathofdestfile = $conf->user->dir_temp.'/'.get_class($this).'_'.__FUNCTION__.'_'.(empty($this->cachefilesuffix) ? '' : $this->cachefilesuffix.'_').$langs->defaultlang.'_entity.'.$conf->entity.'_user'.$user->id.'.cache';
		$newmask = '0644';

		$nowgmt = dol_now();

		$foundintocache = 0;
		if ($cachedelay > 0) {
			$filedate = dol_filemtime($newpathofdestfile);
			if ($filedate >= ($nowgmt - $cachedelay)) {
				$foundintocache = 1;

				$this->lastfetchdate[get_class($this).'_'.__FUNCTION__] = $filedate;
			} else {
				dol_syslog(get_class($this).'::'.__FUNCTION__. ' cache file ' .$newpathofdestfile. ' is not found or older than now - cachedelay (' .$nowgmt. ' - ' .$cachedelay.") so we can't use it.");
			}
		}

		// Load file into $data
		if ($foundintocache) {    // Cache file found and is not too old
			if (!empty($filedate)) {
				dol_syslog(get_class($this).'::'.__FUNCTION__. ' read data from cache file ' .$newpathofdestfile. ' ' .$filedate. '.');
			}
			$data = json_decode(file_get_contents($newpathofdestfile), true);
		} else {
			$year = $startyear;
			$sm = $startmonth - 1;
			if ($sm != 0) {
				$year = $year - 1;
			}
			while ($year <= $endyear) {
				$datay[$year] = $this->getAmountByMonth($year, $format);
				$year++;
			}

			$data = array();
			// $data = array('xval'=>array(0=>xlabel,1=>yval1,2=>yval2...),...)
			for ($i = 0; $i < 12; $i++) {
				$data[$i][] = $datay[$endyear][($i + $sm) % 12]['label'] ?? $datay[$endyear][($i + $sm) % 12][0]; // set label
				$year = $startyear;
				while ($year <= $endyear) {
					$data[$i][] = $datay[$year][($i + $sm) % 12][1]; // set yval for x=i
					$year++;
				}
			}
		}

		// Save cache file
		if (empty($foundintocache) && ($cachedelay > 0 || $cachedelay == -1)) {
			dol_syslog(get_class($this).'::'.__FUNCTION__. ' save cache file ' .$newpathofdestfile. ' onto disk.');
			if (!dol_is_dir($conf->user->dir_temp)) {
				dol_mkdir($conf->user->dir_temp);
			}
			$fp = fopen($newpathofdestfile, 'w');
			if ($fp) {
				fwrite($fp, json_encode($data));
				fclose($fp);
				if (!empty($conf->global->MAIN_UMASK)) {
					$newmask = $conf->global->MAIN_UMASK;
				}
				@chmod($newpathofdestfile, octdec($newmask));
			} else {
				dol_syslog('Failed to write cache file', LOG_ERR);
			}
			$this->lastfetchdate[get_class($this).'_'.__FUNCTION__] = $nowgmt;
		}

		return $data;
	}

	/**
	 * Return average of entity by month for several years
	 *
	 * @param  int       $endyear    End year
	 * @param  int       $startyear  Start year
	 * @param  int       $cachedelay Delay we accept for cache file (0=No read, no save of cache, -1=No read but save)
	 * @param  int       $format     0=Label of abscissa is a translated text, 1=Label of abscissa is month number, 2=Label of abscissa is first letter of month
	 * @param  int       $startmonth Month of the fiscal year start min 1 max 12 ; if 1 = january
	 * @return array|int             Array of values
	 * @throws Exception
	 */
	public function getAverageByMonthWithPrevYear(int $endyear, int $startyear, int $cachedelay = 0, int $format = 0, int $startmonth = 1)
	{
		global $conf, $user, $langs;

		if ($startyear > $endyear) {
			return -1;
		}

		$datay = array();

		// Search into cache
		if (!empty($cachedelay)) {
			include_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
			include_once DOL_DOCUMENT_ROOT.'/core/lib/json.lib.php';
		}

		$newpathofdestfile = $conf->user->dir_temp.'/'.get_class($this).'_'.__FUNCTION__.'_'.(empty($this->cachefilesuffix) ? '' : $this->cachefilesuffix.'_').$langs->defaultlang.'_entity.'.$conf->entity.'_user'.$user->id.'.cache';
		$newmask = '0644';

		$nowgmt = dol_now();

		$foundintocache = 0;
		if ($cachedelay > 0) {
			$filedate = dol_filemtime($newpathofdestfile);
			if ($filedate >= ($nowgmt - $cachedelay)) {
				$foundintocache = 1;

				$this->lastfetchdate[get_class($this).'_'.__FUNCTION__] = $filedate;
			} else {
				dol_syslog(get_class($this).'::'.__FUNCTION__. ' cache file ' .$newpathofdestfile. ' is not found or older than now - cachedelay (' .$nowgmt. ' - ' .$cachedelay.") so we can't use it.");
			}
		}

		// Load file into $data
		if ($foundintocache) {    // Cache file found and is not too old
			if (!empty($filedate)) {
				dol_syslog(get_class($this).'::'.__FUNCTION__. ' read data from cache file ' .$newpathofdestfile. ' ' .$filedate. '.');
			}
			$data = json_decode(file_get_contents($newpathofdestfile), true);
		} else {
			$year = $startyear;
			$sm = $startmonth - 1;
			if ($sm != 0) {
				$year = $year - 1;
			}
			while ($year <= $endyear) {
				$datay[$year] = $this->getAverageByMonth($year, $format);
				$year++;
			}

			$data = array();

			for ($i = 0; $i < 12; $i++) {
				$data[$i][] = $datay[$endyear][($i + $sm) % 12][0];
				$year = $startyear;
				while ($year <= $endyear) {
					$data[$i][] = $datay[$year][($i + $sm) % 12][1];
					$year++;
				}
			}
		}

		// Save cache file
		if (empty($foundintocache) && ($cachedelay > 0 || $cachedelay == -1)) {
			dol_syslog(get_class($this).'::'.__FUNCTION__. ' save cache file ' .$newpathofdestfile. ' onto disk.');
			if (!dol_is_dir($conf->user->dir_temp)) {
				dol_mkdir($conf->user->dir_temp);
			}
			$fp = fopen($newpathofdestfile, 'w');
			if ($fp) {
				fwrite($fp, json_encode($data));
				fclose($fp);
				if (!empty($conf->global->MAIN_UMASK)) {
					$newmask = $conf->global->MAIN_UMASK;
				}
				@chmod($newpathofdestfile, octdec($newmask));
			} else {
				dol_syslog('Failed to write cache file', LOG_ERR);
			}
			$this->lastfetchdate[get_class($this).'_'.__FUNCTION__] = $nowgmt;
		}

		return $data;
	}

	/**
	 * Return nb of elements by year
	 *
	 * @param string     $sql SQL request
	 * @return array
	 * @throws Exception
	 */
	protected function _getNbByYear(string $sql): array
	{
		// phpcs:enable
		$result = array();

		dol_syslog(get_class($this).'::'.__FUNCTION__, LOG_DEBUG);
		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			$i = 0;
			while ($i < $num) {
				$row = $this->db->fetch_row($resql);
				$result[$i] = $row;
				$i++;
			}
			$this->db->free($resql);
		} else {
			dol_print_error($this->db);
		}
		return $result;
	}

	/**
	 * Return nb of elements, total amount and avg amount each year
	 *
	 * @param string     $sql SQL request
	 * @return array          Array with nb, total amount, average for each year
	 * @throws Exception
	 */
	protected function _getAllByYear(string $sql): array
	{
		// phpcs:enable
		$result = array();

		dol_syslog(get_class($this).'::'.__FUNCTION__, LOG_DEBUG);
		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			$i = 0;
			while ($i < $num) {
				$row = $this->db->fetch_object($resql);
				$result[$i]['year'] = $row->year;
				$result[$i]['nb'] = $row->nb;
				if ($i > 0 && $row->nb > 0) {
					$result[$i - 1]['nb_diff'] = ($result[$i - 1]['nb'] - $row->nb) / $row->nb * 100;
				}
				$result[$i]['total'] = $row->total;
				if ($i > 0 && $row->total > 0) {
					$result[$i - 1]['total_diff'] = ($result[$i - 1]['total'] - $row->total) / $row->total * 100;
				}
				$result[$i]['avg'] = $row->avg;
				if ($i > 0 && $row->avg > 0) {
					$result[$i - 1]['avg_diff'] = ($result[$i - 1]['avg'] - $row->avg) / $row->avg * 100;
				}
				// For some $sql only
				if (isset($row->weighted)) {
					$result[$i]['weighted'] = $row->weighted;
					if ($i > 0 && $row->weighted > 0) {
						$result[$i - 1]['avg_weighted'] = ($result[$i - 1]['weighted'] - $row->weighted) / $row->weighted * 100;
					}
				}
				$i++;
			}
			$this->db->free($resql);
		} else {
			dol_print_error($this->db);
		}
		return $result;
	}

	/**
	 * Return number of elements per month for a given year
	 *
	 * @param  string     $sql    SQL
	 * @param  int        $format 0=Label of abscissa is a translated text, 1=Label of abscissa is month number, 2=Label of abscissa is first letter of month
	 * @return array              Array of nb each month
	 * @throws Exception
	 */
	protected function _getNbByMonth(string $sql, int $format = 0): array
	{
		// phpcs:enable
		global $langs;

		$result = array();
		$res = array();

		dol_syslog(get_class($this).'::'.__FUNCTION__, LOG_DEBUG);
		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			$i = 0;
			while ($i < $num) {
				$row = $this->db->fetch_row($resql);
				$j = $row[0] * 1;
				$result[$j] = $row[1];
				$i++;
			}
			$this->db->free($resql);
		} else {
			dol_print_error($this->db);
		}

		for ($i = 1; $i < 13; $i++) {
			$res[$i] = ($result[$i] ?? 0);
		}

		$data = array();

		for ($i = 1; $i < 13; $i++) {
			$month = 'unknown';
			if ($format == 0) {
				$month = $langs->transnoentitiesnoconv('MonthShort'.sprintf('%02d', $i));
			} elseif ($format == 1) {
				$month = $i;
			} elseif ($format == 2) {
				$month = $langs->transnoentitiesnoconv('MonthVeryShort'.sprintf('%02d', $i));
			}
			$data[$i - 1] = array($month, $res[$i]);
		}

		return $data;
	}

	/**
	 * Return the amount per month for a given year
	 *
	 * @param  string     $sql    SQL
	 * @param  int        $format 0=Label of abscissa is a translated text, 1=Label of abscissa is month number, 2=Label of abscissa is first letter of month
	 * @return array              Array of amount each month
	 * @throws Exception
	 */
	protected function _getAmountByMonth(string $sql, int $format = 0): array
	{
		// phpcs:enable
		global $langs;

		$result = array();
		$res = array();

		dol_syslog(get_class($this).'::'.__FUNCTION__, LOG_DEBUG);

		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			$i = 0;
			while ($i < $num) {
				$row = $this->db->fetch_row($resql);
				$j = $row[0] * 1;
				$result[$j] = $row[1];
				$i++;
			}
			$this->db->free($resql);
		} else {
			dol_print_error($this->db);
		}

		for ($i = 1; $i < 13; $i++) {
			$res[$i] = (int) round(($result[$i] ?? 0));
		}

		$data = array();

		for ($i = 1; $i < 13; $i++) {
			$month = 'unknown';
			if ($format == 0) {
				$month = $langs->transnoentitiesnoconv('MonthShort'.sprintf('%02d', $i));
			} elseif ($format == 1) {
				$month = $i;
			} elseif ($format == 2) {
				$month = $langs->transnoentitiesnoconv('MonthVeryShort'.sprintf('%02d', $i));
			}
			$data[$i - 1] = array($month, $res[$i]);
		}

		return $data;
	}

	/**
	 *  Return the amount average par month for a given year
	 *
	 * @param  string     $sql    SQL
	 * @param  int        $format 0=Label of abscissa is a translated text, 1=Label of abscissa is month number, 2=Label of abscissa is first letter of month
	 * @return array
	 * @throws Exception
	 */
	protected function _getAverageByMonth(string $sql, int $format = 0): array
	{
		// phpcs:enable
		global $langs;

		$result = array();
		$res = array();

		dol_syslog(get_class($this).'::'.__FUNCTION__, LOG_DEBUG);
		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			$i = 0;
			while ($i < $num) {
				$row = $this->db->fetch_row($resql);
				$j = $row[0] * 1;
				$result[$j] = $row[1];
				$i++;
			}
			$this->db->free($resql);
		} else {
			dol_print_error($this->db);
		}

		for ($i = 1; $i < 13; $i++) {
			$res[$i] = ($result[$i] ?? 0);
		}

		$data = array();

		for ($i = 1; $i < 13; $i++) {
			$month = 'unknown';
			if ($format == 0) {
				$month = $langs->transnoentitiesnoconv('MonthShort'.sprintf('%02d', $i));
			} elseif ($format == 1) {
				$month = $i;
			} elseif ($format == 2) {
				$month = $langs->transnoentitiesnoconv('MonthVeryShort'.sprintf('%02d', $i));
			}
			$data[$i - 1] = array($month, $res[$i]);
		}

		return $data;
	}

	/**
	 *  Returns the summed amounts per year for a given number of past years ending now
	 *
	 *  @param  string $sql SQL
	 *  @return array
	 */
	protected function _getAmountByYear(string $sql): array
	{
		$result = array();
		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			$i = 0;
			while ($i < $num) {
				$row = $this->db->fetch_row($resql);
				$result[] = [
					0 => (int) $row[0],
					1 => (int) $row[1],
				];
				$i++;
			}
			$this->db->free($resql);
		}
		return $result;
	}

    /**
     * Load dashboard info dolisirh
     *
     * @return array
     * @throws Exception
     */
    public function load_dashboard()
    {
        global $langs;

        $timeSpendingInfos = $this->getTimeSpendingInfos();
        $TimeSpentReport   = $this->getTimeSpentReport();

        $array['widgets'] = [
            0 => [
                'label'      => [$timeSpendingInfos['planned']['label'], $timeSpendingInfos['passed']['label'], $timeSpendingInfos['spent']['label'], $timeSpendingInfos['difference']['label']],
                'content'    => [$timeSpendingInfos['planned']['content'], $timeSpendingInfos['passed']['content'], $timeSpendingInfos['spent']['content'], $timeSpendingInfos['difference']['content']],
                'picto'      => 'fas fa-clock',
                'widgetName' => $langs->transnoentities('TimeSpent')
            ],
        ];

        $array['graphs'] = $TimeSpentReport;

        return $array;
    }

    /**
     * Get all timespent infos.
     *
     * @return array
     * @throws Exception
     */
    public function getTimeSpendingInfos()
    {
        require_once __DIR__ . '/../lib/dolisirh_function.lib.php';

        global $db, $langs, $user;

        $firstdaytoshow = dol_get_first_day(date('Y'), date('m'));
        $lastdayofmonth = strtotime(date('Y-m-t', $firstdaytoshow));

        $currentMonth = date('m', dol_now());
        if ($currentMonth == date('m')) {
            $lastdaytoshow = dol_now();
        } else {
            $lastdaytoshow = $lastdayofmonth;
        }

        $daysInMonth = num_between_day($firstdaytoshow, $lastdayofmonth, 1);

        $isavailable = [];
        for ($idw = 0; $idw < $daysInMonth; $idw++) {
            $dayInLoop =  dol_time_plus_duree($firstdaytoshow, $idw, 'd');
            if (isDayAvailable($dayInLoop, $user->id)) {
                $isavailable[$dayInLoop] = ['morning'=>1, 'afternoon'=>1];
            } else if (date('N', $dayInLoop) >= 6) {
                $isavailable[$dayInLoop] = ['morning'=>false, 'afternoon'=>false, 'morning_reason'=>'week_end', 'afternoon_reason'=>'week_end'];
            } else {
                $isavailable[$dayInLoop] = ['morning'=>false, 'afternoon'=>false, 'morning_reason'=>'public_holiday', 'afternoon_reason'=>'public_holiday'];
            }
        }

        $workinghours = new Workinghours($db);
        $workingHours = $workinghours->fetchCurrentWorkingHours($user->id, 'user');

        $timeSpendingInfos = loadTimeSpendingInfosWithinRange($firstdaytoshow, dol_time_plus_duree($lastdaytoshow, 1, 'd'), $workingHours, $isavailable, $user->id);

        // Planned working time
        $planned_working_time = loadPlannedTimeWithinRange($firstdaytoshow, dol_time_plus_duree($lastdayofmonth, 1, 'd'), $workingHours, $isavailable);
        $array['planned']['label']   = $langs->trans('Total') . ' - ' . $langs->trans('ExpectedWorkedHoursMonth', dol_print_date(dol_mktime(0, 0, 0, date('m'), date('d'), date('Y')), '%B %Y'));
        $array['planned']['content'] = (($planned_working_time['minutes'] != 0) ? convertSecondToTime($planned_working_time['minutes'] * 60, 'allhourmin') : '00:00');

        // Hours passed
        $passed_working_time        = $timeSpendingInfos['passed'];
        $array['passed']['label']   = $langs->trans('Total') . ' - ' . $langs->trans('SpentWorkedHoursMonth', dol_print_date($firstdaytoshow, 'dayreduceformat'), dol_print_date($lastdaytoshow, 'dayreduceformat'));
        $array['passed']['content'] = (($passed_working_time['minutes'] != 0) ? convertSecondToTime($passed_working_time['minutes'] * 60, 'allhourmin') : '00:00');

        //Worked hours
        $worked_time               = $timeSpendingInfos['spent'];
        $array['spent']['label']   = $langs->trans('Total') . ' - ' . $langs->trans('ConsumedWorkedHoursMonth', dol_print_date($firstdaytoshow, 'dayreduceformat'), dol_print_date($lastdaytoshow, 'dayreduceformat'));
        $array['spent']['content'] = convertSecondToTime($worked_time['total'], 'allhourmin');

        //Difference between passed and worked hours
        $difftotaltime                  = $timeSpendingInfos['difference'] * 60;
        $array['difference']['label']   = $langs->trans('Total') . ' - ' . $langs->trans('DiffSpentAndConsumedWorkedHoursMonth', dol_print_date($firstdaytoshow, 'dayreduceformat'), dol_print_date($lastdaytoshow, 'dayreduceformat'));
        $array['difference']['content'] = (($difftotaltime != 0) ? convertSecondToTime(abs($difftotaltime), 'allhourmin') : '00:00');

        return $array;
    }

    /**
     * Get timespent report on current year.
     *
     * @return array
     * @throws Exception
     */
    public function getTimeSpentReport()
    {
        require_once __DIR__ . '/../lib/dolisirh_function.lib.php';

        global $conf, $db, $langs, $user;

        $startmonth = $conf->global->SOCIETE_FISCAL_MONTH_START - 2;

        $array['title']  = $langs->transnoentities('TimeSpentReportByFiscalYear');
        $array['picto']  = '<i class="fas fa-clock"></i>';
        $array['width']  = 800;
        $array['height'] = 400;
        $array['type']   = 'bars';
        $array['labels'] = [
            0 => [
                'label' => $langs->transnoentities('ExpectedWorkedHours'),
                'color' => '#008ECC'
            ],
            1 => [
                'label' => $langs->transnoentities('ConsumedWorkedHours'),
                'color' => '#49AF4A'
            ]
        ];

        $workinghours = new Workinghours($db);
        $workingHours = $workinghours->fetchCurrentWorkingHours($user->id, 'user');

        for ($i = 1; $i < 13; $i++) {
            $firstdaytoshow = dol_get_first_day(date('Y'), $i);
            $lastdayofmonth = strtotime(date('Y-m-t', $firstdaytoshow));

            $currentMonth = date('m', dol_now());
            if ($currentMonth == date('m')) {
                $lastdaytoshow = dol_now();
            } else {
                $lastdaytoshow = $lastdayofmonth;
            }

            $daysInMonth = num_between_day($firstdaytoshow, $lastdayofmonth, 1);

            $isavailable = [];
            for ($idw = 0; $idw < $daysInMonth; $idw++) {
                $dayInLoop =  dol_time_plus_duree($firstdaytoshow, $idw, 'd');
                if (isDayAvailable($dayInLoop, $user->id)) {
                    $isavailable[$dayInLoop] = ['morning'=>1, 'afternoon'=>1];
                } else if (date('N', $dayInLoop) >= 6) {
                    $isavailable[$dayInLoop] = ['morning'=>false, 'afternoon'=>false, 'morning_reason'=>'week_end', 'afternoon_reason'=>'week_end'];
                } else {
                    $isavailable[$dayInLoop] = ['morning'=>false, 'afternoon'=>false, 'morning_reason'=>'public_holiday', 'afternoon_reason'=>'public_holiday'];
                }
            }

            $planned_working_time = loadPlannedTimeWithinRange($firstdaytoshow, dol_time_plus_duree($lastdayofmonth, 1, 'd'), $workingHours, $isavailable);
            $worked_time          = loadTimeSpentWithinRange($firstdaytoshow, dol_time_plus_duree($lastdaytoshow, 1, 'd'), $isavailable, $user->id);

            $planned_working_time_data = (($planned_working_time['minutes'] != 0) ? convertSecondToTime($planned_working_time['minutes'] * 60, 'fullhour') : 0);
            $worked_time_data = convertSecondToTime($worked_time['total'], 'fullhour');

            $month = $langs->transnoentitiesnoconv('MonthShort'.sprintf('%02d', $i));
            $array['data'][($i + $startmonth) % 12] = [$month, $planned_working_time_data, $worked_time_data];
            ksort($array['data']);
        }

        return $array;
    }
}
