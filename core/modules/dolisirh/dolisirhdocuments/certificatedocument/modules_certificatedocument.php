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
 * or see https://www.gnu.org/
 */

/**
 *  \file			htdocs/core/modules/dolisirh/certificatedocument/modules_certificatedocument.php
 *  \ingroup		dolisirh
 *  \brief			File that contains parent class for certificates document models
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commondocgenerator.class.php';

/**
 *	Parent class for documents models
 */
abstract class ModeleODTCertificate extends CommonDocGenerator
{
	/**
	 *  Return list of active generation modules
	 *
	 *  @param  DoliDB  $db     			Database handler
	 *  @param  integer $maxfilenamelength  Max length of value to show
	 *  @return	array					    List of templates
	 */
	public static function liste_modeles(DoliDB $db, int $maxfilenamelength = 0): array
	{
		$type = 'certificatedocument';
		$list = array();

        require_once __DIR__ . '/../../../../../lib/dolisirh_function.lib.php';
		return getListOfModelsDoliSIRH($db, $type, $maxfilenamelength);
	}
}
