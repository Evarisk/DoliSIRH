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
 * or see https://www.gnu.org/
 */

/**
 * \file    htdocs/core/modules/dolisirh/dolisirhdocuments/certificatedocument/doc_certificatedocument_odt.modules.php
 * \ingroup dolisirh
 * \brief   File of class to build ODT certificate document.
 */

// Load Dolibarr libraries.
require_once DOL_DOCUMENT_ROOT . '/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/images.lib.php';
require_once DOL_DOCUMENT_ROOT . '/core/lib/company.lib.php';

// Load DoliSIRH Libraries.
require_once __DIR__ . '/mod_certificatedocument_standard.php';

/**
 * Class to build documents using ODF templates generator.
 */
class doc_certificatedocument_odt extends SaturneDocumentModel
{
    /**
     * @var string Module.
     */
    public string $module = 'dolisirh';

    /**
     * @var string Document type.
     */
    public string $document_type = 'certificatedocument';

    /**
     * Constructor.
     *
     * @param DoliDB $db Database handler.
     */
    public function __construct(DoliDB $db)
    {
        parent::__construct($db, $this->module, $this->document_type);
    }

    /**
     * Return description of a module.
     *
     * @param  Translate $langs Lang object to use for output.
     * @return string           Description.
     */
    public function info(Translate $langs): string
    {
        return parent::info($langs);
    }

    /**
     * Function to build a document on disk.
     *
     * @param  SaturneDocuments $objectDocument  Object source to build document.
     * @param  Translate        $outputLangs     Lang object to use for output.
     * @param  string           $srcTemplatePath Full path of source filename for generator using a template file.
     * @param  int              $hideDetails     Do not show line details.
     * @param  int              $hideDesc        Do not show desc.
     * @param  int              $hideRef         Do not show ref.
     * @param  array            $moreParam       More param (Object/user/etc).
     * @return int                               1 if OK, <=0 if KO.
     * @throws Exception
     */
    public function write_file(SaturneDocuments $objectDocument, Translate $outputLangs, string $srcTemplatePath, int $hideDetails = 0, int $hideDesc = 0, int $hideRef = 0, array $moreParam): int
    {
        global $conf, $langs;

        $object = $moreParam['object'];

        $signatory = new SaturneSignature($this->db);

        $tmpArray['object_ref']         = $object->ref;
        $tmpArray['object_description'] = $object->description;
        $tmpArray['object_date_start']  = dol_print_date($object->date_start, 'day', 'tzuser');
        $tmpArray['object_date_end']    = dol_print_date($object->date_end, 'day', 'tzuser');
        $tmpArray['object_public_url']  = $object->public_url;

        $signatories = $signatory->fetchSignatory('Signatory', $object->id, $object->element);
        if (is_array($signatories) && !empty($signatories)) {
            $signatory = array_shift($signatories);
            $tmpArray['attendant_fullname'] = strtoupper($signatory->lastname) . ' ' . ucfirst($signatory->firstname);
            if (dol_strlen($signatory->signature) > 0 && $signatory->signature != $langs->transnoentities('FileGenerated')) {
                if ($moreParam['specimen'] == 0 || ($moreParam['specimen'] == 1 && $conf->global->DOLISIRH_SHOW_SIGNATURE_SPECIMEN == 1)) {
                    $tempDir      = $conf->dolisirh->multidir_output[$object->entity ?? 1] . '/temp/';
                    $encodedImage = explode(',', $signatory->signature)[1];
                    $decodedImage = base64_decode($encodedImage);
                    file_put_contents($tempDir . 'signature.png', $decodedImage);
                    $tmpArray['attendant_signature'] = $tempDir . 'signature.png';
                } else {
                    $tmpArray['attendant_signature'] = '';
                }
            } else {
                $tmpArray['attendant_signature'] = '';
            }
        } else {
            $tmpArray['attendant_fullname']  = '';
            $tmpArray['attendant_signature'] = '';
        }

        $signatories = $signatory->fetchSignatory('Responsible', $object->id, $object->element);
        if (is_array($signatories) && !empty($signatories)) {
            $signatory = array_shift($signatories);
            if (dol_strlen($signatory->signature) > 0 && $signatory->signature != $langs->transnoentities('FileGenerated')) {
                if ($moreParam['specimen'] == 0 || ($moreParam['specimen'] == 1 && $conf->global->DOLISIRH_SHOW_SIGNATURE_SPECIMEN == 1)) {
                    $tempDir      = $conf->dolisirh->multidir_output[$object->entity ?? 1] . '/temp/';
                    $encodedImage = explode(',', $signatory->signature)[1];
                    $decodedImage = base64_decode($encodedImage);
                    file_put_contents($tempDir . 'signature2.png', $decodedImage);
                    $tmpArray['responsible_signature'] = $tempDir . 'signature2.png';
                } else {
                    $tmpArray['responsible_signature'] = '';
                }
            } else {
                $tmpArray['responsible_signature'] = '';
            }
        } else {
            $tmpArray['responsible_signature'] = '';
        }

        $tmpArray['object_document_date_creation'] = dol_print_date(dol_now(), 'dayhour', 'tzuser');

        $moreParam['tmparray'] = $tmpArray;

        return parent::write_file($objectDocument, $outputLangs, $srcTemplatePath, $hideDetails, $hideDesc, $hideRef, $moreParam);
    }
}
