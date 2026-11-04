<?php
/* Copyright (C) 2026 EVARISK <technique@evarisk.com>
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
 * \file    core/tpl/frontend/dolisirh_pwa_header.tpl.php
 * \ingroup dolisirh
 * \brief   Top header bar for DoliSIRH PWA pages
 */

/** @var Conf $conf */
/** @var Translate $langs */
/** @var User $user */

$companyName = getDolGlobalString('MAIN_INFO_SOCIETE_NOM', 'Dolibarr');
$moduleIcon  = dol_buildpath('/custom/dolisirh/img/dolisirh_color.png', 1);
$desktopUrl  = dol_buildpath('/custom/dolisirh/view/timesheet/timesheet_page.php', 1);
?>
<div class="dolisirh-pwa-header ts-header">
	<div class="ts-header__left">
		<span class="ts-profile-badge" title="<?php echo dol_escape_htmltag($companyName); ?>">
			<img class="ts-profile-favicon" src="<?php echo dol_escape_htmltag($moduleIcon); ?>" alt="DoliSIRH">
			<span class="ts-profile-badge__label"><?php echo dol_escape_htmltag($companyName); ?></span>
		</span>
		<a href="<?php echo dol_escape_htmltag($desktopUrl); ?>" class="ts-btn-icon" title="<?php echo dol_escape_htmltag($langs->trans('BackToDolibarr')); ?>">
			<i class="fas fa-external-link-alt"></i>
		</a>
	</div>
	<h1 class="ts-title"><?php echo $langs->trans('TimeSpending'); ?></h1>
	<div class="ts-header__right">
		<span class="ts-user-chip" title="<?php echo dol_escape_htmltag($user->getFullName($langs)); ?>">
			<?php echo Form::showphoto('userphoto', $user, 0, 0, 0, 'photouserphoto userphoto', 'mini', 0, 1); ?>
		</span>
	</div>
</div>
