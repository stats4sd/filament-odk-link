<?php

namespace Stats4sd\FilamentOdkLink\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Stats4sd\FilamentOdkLink\Imports\XlsformTemplate\XlsformTemplateValidator;

/**
 * 
 * This helper class aims to contain business logic to validate the uploaded xlsform template excel file before sending it to test on ODK central.
 * It includes below customised validations. More validations will be added from time to time.
 * 
 * Customised validations:
 * 1. ODK variable with type "or_other"
 * 2. language string column header without language or with a non-existed language (TODO)
 * 
 */
class XlsformValidationHelper
{
    /**
     * A validation to check if the uploaded xlsform template survey excel sheet contains any ODK variable with type "or_other".
     * 
     * ODK central does not recommend to use "or_other" as it does not support multiple languages.
     * It is advised to manually add an "Other" option to choices list. Then use a follow-up text question that is only relevant if "Other" is selected'
     * 
     * Input parameters:
     *  - pathName is the full file path of the uploaded xlsform template excel file
     * 
     * Output parameters:
     *  - a collection of error messages
     * 
     */
    public static function validateTypeOrOther($pathName): Collection {
        // initialise error messages collection
        $result = collect();

        // convert the uploaded excel file into a collection
        $collection = Excel::toCollection(new XlsformTemplateValidator(), $pathName);

        // get type columns of all ODK variables from survey excel sheet
        $types = $collection['survey']->pluck('type');

        // check if any ODK variable with type "or_other"
        foreach ($types as $type) {
            if (Str::contains($type, 'or_other')) {
                $result->add('Type "or_other" is not recommended. Please do below updates on xlsform template and then try again. 1. Manually add an "Other" option to choices list. 2. Use a follow-up text question that is only relevant if "Other" is selected');
                break;
            }
        }

        return $result;
    }

}
