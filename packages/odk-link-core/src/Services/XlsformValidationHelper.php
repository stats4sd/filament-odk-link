<?php

namespace Stats4sd\FilamentOdkLink\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Stats4sd\FilamentOdkLink\Imports\XlsformTemplate\XlsformTemplateValidator;

/**
 * This helper class aims to centralise business logic to validate the uploaded xlsform template excel file before sending it to test on ODK central.
 * It includes below customised validations. More validations will be added from time to time.
 *
 * Customised validations:
 * 1. ODK variable with type "or_other"
 * 2. Language string column header without a defined language (without a language or with a non-existed language)
 */
class XlsformValidationHelper
{
    /**
     * A validation to check if the uploaded xlsform template survey excel sheet contains any ODK variable with type "or_other".
     *
     * ODK central does not recommend to use "or_other" as it does not support multiple languages.
     * It is advised to manually add an "Other" option to choices list. Then use a follow-up text question that is only relevant if "Other" is selected'
     *
     *
     * Input parameters:
     *  - pathName is the full file path of the uploaded xlsform template excel file
     *
     * Output parameters:
     *  - a collection of error messages
     */
    public static function validateTypeOrOther($pathName): Collection
    {
        // initialise error messages collection
        $result = collect();

        // convert the uploaded excel file into a collection
        $collection = Excel::toCollection(new XlsformTemplateValidator, $pathName);

        // get type columns of all ODK variables from survey excel sheet
        $types = $collection['survey']->pluck('type');

        // check if any ODK variable with type "or_other"
        foreach ($types as $type) {
            if (Str::contains($type, 'or_other')) {
                $result->add('Type "or_other" is not supported by this platform. It is not recommended by the ODK team, as it does not support translation or choice filters. Please update your form to add an "other" option to your choice lists and a follow-up "enter the other response" question if required. For more information, see the <a href="https://docs.getodk.org/form-question-types/#including-other-as-a-choice" class="text-blue-800 underline">ODK documentation</a>.');

                break;
            }
        }

        return $result;
    }

    /**
     * A validation to check if the uploaded xlsform template excel file contains any column header without language or with a non-existed language.
     *
     * For header columns starts with any language string type (defined in language_string_types table):
     * e.g. label, hint, relevant_message, required_message, constraint_message, guidance_hint, mediaimage, mdeiaaudio, mediavideo, image, audio, video
     *
     * These header columns should contain a two chars language code inside (), e.g. (en), (fr), (es)
     *
     * A validation error should be showed if a header column without a two chars language code, or the language code is not existed in application.
     *
     * Assumptions:
     * 1. The column headers are in pre-defined format. e.g. label::English (en), label::French (fr)
     * 2. If there is a new column header like label^^English___en, it will pass this validation but this column is assumed not to be handled in ODK central.
     * 3. For simplicity, only column header prefix and column header postfix will be checked by string comparison. Regular expression will not be used.
     * 4. Column header prefix will be checked for language string type, column header postfix will be checked for two chars language code
     *
     *
     * Input parameters:
     *  - pathName is the full file path of the uploaded xlsform template excel file
     *
     * Output parameters:
     *  - a collection of error messages
     */
    public static function validateColumnHeadersWithLanguageString($pathName): Collection
    {
        // initialise error messages collection
        $result = collect();

        // get language string types and languages from XlsformTranslationHelper
        $xlsformTranslationHelper = new XlsformTranslationHelper;
        $languageStringTypes = $xlsformTranslationHelper->languageStringTypes;
        $languages = $xlsformTranslationHelper->languages;

        // convert the uploaded excel file into a collection
        $collection = Excel::toCollection(new XlsformTemplateValidator, $pathName);

        // XlsformTemplateValidator returns survey excel sheet and choices excel sheet only, check all items in the returned collection
        foreach ($collection as $sheet) {
            // each item is an associative array with column header as key, only get the first item from excel sheet is good enough
            $firstRow = $sheet->first();

            // get all keys (i.e. column headers) from associative array
            $columnHeaders = array_keys($firstRow->toArray());

            // check if a column header belongs to a language string type
            foreach ($columnHeaders as $columnHeader) {
                $isLanguageStringType = false;

                foreach ($languageStringTypes as $languageStringType) {
                    if (Str::startsWith($columnHeader, $languageStringType->name)) {
                        $isLanguageStringType = true;

                        break;
                    }
                }

                // if this column header is a language string type, check language code
                if ($isLanguageStringType == true) {
                    $hasLanguageCode = false;

                    // if a column header belongs to a language string type, check if a column header contains a two chars language
                    foreach ($languages as $language) {
                        if (Str::endsWith($columnHeader, '_' . $language->iso_alpha2)) {
                            $hasLanguageCode = true;

                            break;
                        }
                    }

                    // if column header is a language string type, but without language code, add error message
                    if (! $hasLanguageCode) {
                        // Note: after loading excel file into collection, column header does not appear exactly the same in xlsform template excel file
                        $result->add('Column header "' . $columnHeader . '" does not have a defined language code. To fully support translation of forms, please specify the language for each translatable column in your ODK form.  For more information, see the <a href="https://docs.getodk.org/form-language/" class="text-blue-800 underline">ODK documentation</a>.');
                    }

                }

            } // end foreach column header

        } // end foreach excel sheet

        return $result;
    }
}
