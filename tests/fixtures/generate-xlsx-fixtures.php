<?php

/*
 * Regenerates the binary XLSForm fixtures used by the import tests.
 *
 *   php tests/fixtures/generate-xlsx-fixtures.php
 *
 * The .xlsx files are committed (the import logic — sheet/heading parsing — is
 * the behaviour worth testing, so we use real workbooks rather than mocking
 * maatwebsite/excel). Re-run this script whenever the fixtures need to change,
 * and commit the regenerated files alongside it.
 *
 * The column layout mirrors the real XLSForms in tests/assets/.
 */

require __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Write a workbook from an associative array of [sheetName => rows[]].
 */
function writeWorkbook(string $path, array $sheets): void
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->removeSheetByIndex(0);

    foreach ($sheets as $sheetName => $rows) {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($sheetName);

        foreach ($rows as $r => $row) {
            foreach ($row as $c => $value) {
                $sheet->setCellValue([$c + 1, $r + 1], $value);
            }
        }
    }

    (new Xlsx($spreadsheet))->save($path);
    $spreadsheet->disconnectWorksheets();

    echo 'wrote ' . $path . "\n";
}

// ── valid-form.xlsx ─────────────────────────────────────────────────────────
// Minimal, single-language XLSForm exercising:
//  - named modules (demographics, repeats) + a row with no module (generic)
//  - a select_one referencing the `yn` choice list
//  - a begin_repeat / end_repeat (end_repeat has no name → name generated)
//  - the `required` column in true/false/1 forms

writeWorkbook(__DIR__ . '/valid-form.xlsx', [
    'survey' => [
        ['module', 'type', 'name', 'label::English (en)', 'hint::English (en)', 'required', 'relevant', 'appearance', 'calculation', 'constraint', 'choice_filter', 'repeat_count'],
        ['demographics', 'text', 'full_name', 'What is your name?', null, 'yes'],
        ['demographics', 'integer', 'age', 'How old are you?', 'In years', 'true'],
        ['demographics', 'select_one yn', 'consent', 'Do you consent?', null, '1'],
        [null, 'note', 'intro_note', 'Just a note', null, null],
        ['repeats', 'begin_repeat', 'hh_member', 'Household member', null, null, null, null, null, null, null, '3'],
        ['repeats', 'text', 'member_name', 'Member name', null, 'no'],
        ['repeats', 'end_repeat', null, null, null, null],
    ],
    'choices' => [
        ['list_name', 'name', 'label::English (en)'],
        ['yn', '1', 'Yes'],
        ['yn', '0', 'No'],
    ],
    'settings' => [
        ['form_title', 'form_id', 'version', 'default_language'],
        ['Valid Test Form', 'valid_test_form', '1', 'English (en)'],
    ],
]);

// ── multilang-form.xlsx ─────────────────────────────────────────────────────
// Same shape with a second label/hint language (Français) so translatable-
// heading detection has more than one locale to find.

writeWorkbook(__DIR__ . '/multilang-form.xlsx', [
    'survey' => [
        ['module', 'type', 'name', 'label::English (en)', 'label::Français (fr)', 'hint::English (en)', 'hint::Français (fr)', 'required'],
        ['demographics', 'text', 'full_name', 'What is your name?', 'Quel est votre nom?', null, null, 'yes'],
        ['demographics', 'select_one yn', 'consent', 'Do you consent?', 'Consentez-vous?', null, null, '1'],
    ],
    'choices' => [
        ['list_name', 'name', 'label::English (en)', 'label::Français (fr)'],
        ['yn', '1', 'Yes', 'Oui'],
        ['yn', '0', 'No', 'Non'],
    ],
    'settings' => [
        ['form_title', 'form_id', 'version', 'default_language'],
        ['Multi-language Test Form', 'multilang_test_form', '1', 'English (en)'],
    ],
]);
