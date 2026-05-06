<?php

namespace Stats4sd\FilamentOdkLink\Commands;

use DateTime;
use DateInterval;
use SimpleXMLElement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Exports\XlsformExport\XlsformWorkbookExport;

class GenerateSubmissions extends Command
{

    protected $signature = 'app:generate-submissions {xlsform_id} {--count=1}';
    protected $description = 'Creates test submissions and pushes them to ODK Central';

    public function handle()
    {
        ini_set('memory_limit', '-1');

        $xlsformId = $this->argument('xlsform_id');
        $count = (int) $this->option('count');

        $xlsform = Xlsform::find($xlsformId);
        $projectId = $xlsform->odk_project_id;
        $odkId = $xlsform->odk_id;
        $xlsformVersion = $xlsform->odk_version_id;
        $odkUrl = env('ODK_URL');
        $username = env('ODK_USERNAME');
        $password = env('ODK_PASSWORD');

        if (!$xlsform) {
            $this->error("XLSForm with ID $xlsformId not found.");
            return;
        }

        for ($i = 0; $i < $count; $i++) {
            $currentGroup = '';
            $currentRepeat = '';
            $activeGroups = [];
            $activeRepeats = [];

            $filePath = 'temp/' . $xlsform->getKey() . '/' . $xlsform->title . '.xlsx';
            Storage::disk('local')->makeDirectory('temp/' . $xlsform->getKey());
            Excel::store(new XlsformWorkbookExport($xlsform), $filePath, 'local');
            $data = Excel::toArray(new XlsformWorkbookExport($xlsform), $filePath);

            // Get the survey rows
            $rows = $data[0];
            $headings = array_shift($rows);

            // Remove null values from headings
            $headings = array_filter($headings, fn($h) => !is_null($h));

            // Get the choices
            $choices = $data[1];
            $choiceHeadings = array_shift($choices);
            $list_data_labels = [];
            $list_data_values = [];

            foreach ($choices as $row) {
                $choiceRow = array_combine($choiceHeadings, $row);
                $listName = $choiceRow['list_name'];
                $value = $choiceRow['name'];
                $label = $choiceRow['label::English (en)'];

                $list_data_labels[$listName][$value] = $label;
                $list_data_values[$listName][] = $value;
            }

            $list_name_mapping = [];
            foreach ($rows as &$row) {
                // Ensure the row has the same number of elements as headings
                $row = array_slice($row, 0, count($headings));

                if (count($headings) !== count($row)) {
                    $this->comment("Skipping row: Headings and row count do not match", [
                        'headings_count' => count($headings),
                        'row_count' => count($row),
                        'headings' => $headings,
                        'row' => $row
                    ]);
                    continue; // Skip problematic row
                }

                $row = array_combine($headings, $row);
                if ($row === false) {
                    continue;
                }

                if (!isset($row['type']) || !isset($row['name'])) {
                    continue;
                }

                if (strpos($row['type'], 'select_multiple') === 0) {
                    $parts = explode(' ', $row['type']);
                    if (isset($parts[1])) {
                        $list_name_mapping[$row['name']] = $parts[1];
                    }
                }
            }

            $submission = $this->processRows($rows, $headings, $list_name_mapping, $activeGroups, $activeRepeats, $list_data_labels, $list_data_values);
            // ray($submission);

            // Convert the submission to XML
            $xml = new SimpleXMLElement('<data/>');
            $this->arrayToXml($submission, $xml, $xlsform->odk_id, $xlsformVersion);
            $xmlContent = $xml->asXML();

            // Send the POST request to ODK Central API
            $url = "{$odkUrl}/v1/projects/{$projectId}/forms/{$odkId}/submissions";

            $response = Http::withHeaders([
                'Content-Type' => 'application/xml',
            ])
            ->withBasicAuth($username, $password)
            ->withBody($xmlContent, 'application/xml')
            ->post($url);

            if ($response->successful()) {
            } else {
            }

            $this->info("Generated submission #".($i+1)." of ".$count." for XLSForm ID: $xlsformId");
        }
    }

    function processRows(array &$rows, array $headings, array $list_name_mapping, array &$activeGroups, array &$activeRepeats, array $list_data_labels, array $list_data_values, array &$submission = [], int $repeat_position = 0, $parentSubmission = null): array
    {
        while ($row = array_shift($rows)) {
            $row = array_combine($headings, $row);
            if (!isset($row['type']) || !isset($row['name'])) {
                continue;
            }

            if (in_array($row['type'], ['note', 'trigger', 'acknowledge', 'image', 'video', 'audio', 'file',
                        'hidden', 'date', 'rank', 'range', 'barcode', 'geotrace', 'geoshape'])) {
                continue;

            } elseif ($row['type'] === 'begin_group' || $row['type'] === 'begin group') {
                $activeGroups[] = [
                    'name' => $row['name'],
                    'level' => count($activeGroups),
                ];

                // Initialize the group data in submission
                if (!isset($submission[$row['name']])) {
                    $submission[$row['name']] = [];
                }

                // Collect all rows that belong to this group
                $groupRows = [];
                while ($rows && $rows[0]) {
                    $nextRow = array_combine($headings, $rows[0]);

                    // Check if we have reached an end_group that matches the current group
                    if ($nextRow['type'] === 'end_group' || $nextRow['type'] === 'end group') {
                        // Ensure we match the correct end_group by checking level and name
                        $lastGroup = end($activeGroups);
                        if ($nextRow['name'] === $lastGroup['name'] && $lastGroup['level'] === count($activeGroups) - 1) {
                            array_shift($rows); // Removes the 'end_group' row and stops collecting
                            break;
                        }
                    }

                    // Add the row to the group rows list
                    $groupRows[] = array_shift($rows);
                }

                // Process the group rows after collecting them all
                $processedGroup = [];
                $this->processRows($groupRows, $headings, $list_name_mapping, $activeGroups, $activeRepeats, $list_data_labels, $list_data_values, $processedGroup);

                // Store processed data inside the group array
                $submission[$row['name']] = $processedGroup;

                // After processing, remove the group context from the active list
                array_pop($activeGroups);

            } elseif ($row['type'] === 'begin_repeat' || $row['type'] === 'begin repeat') {
                    $activeRepeats[] = [
                        'name' => $row['name'],
                        'level' => count($activeRepeats),
                    ];

                    // Get the repeat count
                    if (!empty($row['repeat_count'])) {

                        // Case: count-selected(${variable})
                        if (preg_match('/^count-selected\(\$\{(.+?)\}\)$/', $row['repeat_count'], $matches)) {
                            $variableName = $matches[1]; // Extract variable name from ${}
                            $repeatCount = $this->getCountSelected($variableName, $submission, $parentSubmission);
                        // Case: ${variable}
                        } elseif (preg_match('/\$\{(.+?)\}/', $row['repeat_count'], $matches) ||
                            preg_match('/^number\((.+?)\)$/', $row['repeat_count'], $matches)) {
                            // ray($row['name'], 'repat count: '.$row['repeat_count']);

                            $variableName = $matches[1]; // Extract variable name from ${}
                            $repeatCount = $this->findValueInSubmission($variableName, $submission, $parentSubmission);

                        // Case: specified number
                        }else {
                            $repeatCount = (int) $row['repeat_count'];
                        }

                    // No repeat count specified
                    } else {
                        $repeatCount = 2;
                    }
                    // Initialize the repeat data in submission
                    if (!isset($submission[$row['name']])) {
                        $submission[$row['name']] = [];
                    }

                    // Collect all rows that belong to this repeat group
                    $repeatRows = [];
                    while ($rows && $rows[0]) {
                        $nextRow = array_combine($headings, $rows[0]);

                        // Check if we have reached an end_repeat that matches the current repeat
                        if ($nextRow['type'] === 'end_repeat' || $nextRow['type'] === 'end repeat') {
                            // Ensure we match the correct end_repeat by checking level and name
                            $lastRepeat = end($activeRepeats);
                            if ($nextRow['name'] === $lastRepeat['name'] && $lastRepeat['level'] === count($activeRepeats) - 1) {
                                array_shift($rows); // Removes the 'end_repeat' row and stops collecting
                                break;
                            }
                        }

                        // Add the row to the repeat rows list
                        $repeatRows[] = array_shift($rows);
                    }

                    // Process the repeat group rows based on the repeat count
                    for ($repeat_position = 1; $repeat_position <= $repeatCount; $repeat_position++) {
                        $repeatSubmission = [];
                        // Clone repeatRows so each iteration gets new data
                        $repeatRowsCopy = $repeatRows;
                        // Process the repeat rows and get the data
                        $processedRepeat = $this->processRows($repeatRowsCopy, $headings, $list_name_mapping, $activeGroups, $activeRepeats, $list_data_labels, $list_data_values, $repeatSubmission, $repeat_position, $submission);
                        if (!empty($processedRepeat)) {
                            // Add the processed repeat data to the submission
                            $submission[$row['name']][] = $processedRepeat;
                        }
                    }

                    // After processing, remove the repeat context from the active list
                    array_pop($activeRepeats);

            } elseif (strpos($row['type'], 'select_one') === 0) {
                $listname = explode(' ', $row['type'])[1] ?? '';
                $submission[$row['name']] = (!empty($listname) && isset($list_data_values[$listname]) && is_array($list_data_values[$listname]) && count($list_data_values[$listname]) > 0)
                    ? $list_data_values[$listname][array_rand($list_data_values[$listname])]
                    : 'CANNOT FIND CHOICE LIST';

            } elseif (strpos($row['type'], 'select_multiple') === 0) {
                $listname = explode(' ', $row['type'])[1] ?? '';
                if (!empty($listname) && isset($list_data_values[$listname]) && is_array($list_data_values[$listname]) && count($list_data_values[$listname]) > 0) {
                    $listItems = array_unique($list_data_values[$listname]);
                    $numberOfItems = rand(1, count($listItems));
                    $numberOfItems = min($numberOfItems, count($listItems));
                    // ray($listname, $listItems, $numberOfItems);
                    $selectedItems = array_rand(array_flip($listItems), $numberOfItems);
                    $selectedItems = is_array($selectedItems) ? $selectedItems : [$selectedItems];
                    $submission[$row['name']] = implode(' ', $selectedItems);
                } else {
                    $submission[$row['name']] = 'CANNOT FIND CHOICE LIST';
                }

            } elseif ($row['type'] === 'integer') {
                $submission[$row['name']] = $this->generateConstrainedInteger($row['constraint'], $submission);

            } elseif ($row['type'] === 'decimal') {
                $submission[$row['name']] = $this->generateConstrainedDecimal($row['constraint'], $submission);

            } elseif ($row['type'] === 'text') {
                $submission[$row['name']] = str_shuffle('abcdefghijklmnopqrstuvwxyz');

            } elseif ($row['type'] === 'calculate') {
                // position(..)
                if($row['calculation'] === 'position(..)') {
                    $submission[$row['name']] = $repeat_position;

                // string
                } elseif (preg_match('/^"(.*)"$/', $row['calculation'], $matches)) {
                    $submission[$row['name']] = $matches[1];

                // single variable reference eg ${village_id}
                } elseif (preg_match('/^\$\{([^{}]+)\}$/', $row['calculation'], $matches)) {
                    $variableName = $matches[1];
                    $submission[$row['name']] = $this->findValueInSubmission($variableName, $submission, $parentSubmission) ?? 'CANNOT FIND VALUE';

                // count-selected
                } elseif (preg_match('/^count-selected\(\$\{(.+?)\}\)$/', $row['calculation'], $matches)) {
                    $variableName = $matches[1]; // Extract variable name from ${}
                    $submission[$row['name']] = $this->getCountSelected($variableName, $submission);

                // years from today
                } elseif (preg_match('/^number\(format-date\(today\(\),\s?[\'"]%Y[\'"]\)\)\s?-\s?\$\{(.+?)\}$/', $row['calculation'], $matches)) {
                    $variableName = $matches[1]; // Extract any variable inside ${}
                    $currentYear = (int) date('Y');
                    $variableValue = $this->findValueInSubmission($variableName, $submission, $parentSubmission);

                    // Ensure the variable is numeric before performing subtraction
                    $submission[$row['name']] = is_numeric($variableValue) ? $currentYear - (int) $variableValue : 'INVALID VALUE';

                // jr:choice-name
                } elseif (preg_match('/^jr:choice-name\((.+?),\s*[\'"](.+?)[\'"]\)$/', $row['calculation'], $matches)) {
                    $variableExpression = trim($matches[1]); // The value reference
                    $listName = trim($matches[2]); // The choice list name
                    // Remove ${} if present in list name
                    if (preg_match('/^\$\{([^{}]+)\}$/', $listName, $listMatch)) {
                        $listName = $listMatch[1];
                    }

                    // Check if $listName is actually a question name in a select_multiple
                    if (isset($list_name_mapping[$listName])) {
                        $originalListName = $listName;
                        $listName = $list_name_mapping[$listName]; // Use actual choice list name
                    }

                    // Determine if it's a direct variable or an indexed selection
                    if (preg_match('/^\$\{([^{}]+)\}$/', $variableExpression, $varMatch)) {
                        $variableName = $varMatch[1];
                        $selectedValue = $this->findValueInSubmission($variableName, $submission, $parentSubmission);

                    } elseif (preg_match('/^selected-at\(\$\{([^{}]+)\},\s*(.+?)\)$/', $variableExpression, $selectedMatches)) {
                        $variableName = $selectedMatches[1];
                        $indexExpression = $selectedMatches[2];
                        // ray($variableExpression, $indexExpression);
                        // Evaluate the index expression (e.g., position(..)-1)
                        if (isset($repeat_position)) {
                            $indexExpression = str_replace('position(..)', $repeat_position, $indexExpression);
                        }
                        $index = $this->evaluateOdkExpression($indexExpression, $submission);

                        // Fetch the list from submission
                        $listValue = $this->findValueInSubmission($variableName, $submission, $parentSubmission);

                        if (is_string($listValue)) {
                            $listItems = explode(' ', trim($listValue));
                            if (isset($listItems[$index])) {
                                $selectedValue = $listItems[$index];
                            } else {
                                $selectedValue = 'OUT OF BOUNDS';
                            }
                        } else {
                            $selectedValue = 'INVALID VALUE';
                        }

                    } else {
                        $selectedValue = 'UNKNOWN EXPRESSION';
                    }

                    // Find the corresponding label in the choice list
                    if (!empty($selectedValue) && isset($list_data_labels[$listName][$selectedValue])) {
                        $submission[$row['name']] = $list_data_labels[$listName][$selectedValue];
                    } else {
                        $submission[$row['name']] = 'CHOICE NOT FOUND';
                    }


                // // indexed-repeat
                } elseif (preg_match('/^indexed-repeat\(\$\{([^}]+)\},\s?\$\{([^}]+)\},\s?(\d+)\)$/', $row['calculation'], $matches)) {
                    $variableName = $matches[1];
                    $repeatGroupName = $matches[2];
                    $index = (int) $matches[3] - 1; // Convert to zero-based index
                    // Check if the repeat group exists in submission
                    if (isset($submission[$repeatGroupName]) && is_array($submission[$repeatGroupName])) {
                        $repeatGroup = $submission[$repeatGroupName];

                        // Ensure the index is within bounds
                        if (isset($repeatGroup[$index][$variableName])) {
                            $submission[$row['name']] = $repeatGroup[$index][$variableName];
                        } else {
                            $submission[$row['name']] = null;
                        }
                    } else {
                        $submission[$row['name']] = null;
                    }

                // selected-at
                } elseif (preg_match('/^selected-at\(\$\{([^{}]+)\},\s*(.+?)\)$/', $row['calculation'], $matches)) {
                    // ray($row['name'], $row['calculation']);

                    $variableName = $matches[1];  // Extract list variable name
                    $indexExpression = $matches[2]; // Extract index expression


                    // Retrieve the list from submission
                    $listValue = $this->findValueInSubmission($variableName, $submission, $parentSubmission);

                    if (!is_string($listValue) || trim($listValue) === '') {
                        $submission[$row['name']] = 'INVALID VALUE';
                    } else {
                        // Convert space-separated string into an array
                        $listItems = explode(' ', trim($listValue));

                        // Replace position(..) with the actual repeat position
                        if (isset($repeat_position)) {
                            $indexExpression = str_replace('position(..)', $repeat_position, $indexExpression);
                        }

                        // Evaluate the modified index expression
                        $index = $this->evaluateOdkExpression($indexExpression, $submission);

                        // Ensure the index is valid
                        if (!is_numeric($index)) {
                            $submission[$row['name']] = 'INVALID INDEX';
                        } else {
                            $index = (int) $index; // Convert to integer
                            if ($index < 0 || $index >= count($listItems)) {
                                $submission[$row['name']] = 'OUT OF BOUNDS';
                            } else {
                                // Assign the selected item
                                $submission[$row['name']] = $listItems[$index];
                            }
                        }
                    }

                } elseif (strpos($row['calculation'], 'coalesce(') !== false) {
                    $submission[$row['name']] = 'SKIPPED coalesce()';
                } elseif (strpos($row['calculation'], 'selected(') !== false) {
                    $submission[$row['name']] = 'SKIPPED selected()';
                } elseif (strpos($row['calculation'], 'concat(') !== false) {
                    $submission[$row['name']] = 'SKIPPED concat()';
                } elseif (strpos($row['calculation'], 'contains(') !== false) {
                    $submission[$row['name']] = 'SKIPPED contains()';
                } elseif (strpos($row['calculation'], 'jr:choice-name(selected-at') !== false) {
                    $submission[$row['name']] = 'SKIPPED jr:choice-name(selected-at)';

                } elseif (preg_match('/^(if\(.+\)|[\d\s\(\)]*([\+\-\*\/]|div|mod)[\d\s\(\)]*)$/', $row['calculation'])) {
                    $submission[$row['name']] = $this->evaluateOdkExpression($row['calculation'], $submission);

                } else {
                    $submission[$row['name']] = 'TODO: CALCULATE VALUE';
                }

            } elseif ($row['type'] === 'geopoint') {
                $submission[$row['name']] = $this->generateRandomGeopoint();

            } elseif ($row['type'] === 'start') {
                $startTime = date('Y-m-d\TH:i:s.', time()) . substr(microtime(), 2, 3) . 'Z';
                $submission['start'] = $startTime;

            } elseif ($row['type'] === 'end') {
                if ($startTime) {
                    $randomMinutes = rand(1, 60);
                    $randomSeconds = rand(0, 59);
                    $startDateTime = new DateTime($startTime);
                    $startDateTime->add(new DateInterval('PT' . $randomMinutes . 'M' . $randomSeconds . 'S'));
                    $endTime = $startDateTime->format('Y-m-d\TH:i:s.') . substr(microtime(), 2, 3) . 'Z';
                    $submission['end'] = $endTime;
                }

            } elseif ($row['type'] === 'username') {
                $submission[$row['name']] = 'submission-generator';

            } elseif ($row['type'] === 'deviceid') {
                $submission['deviceid'] = 'submission-generator';

            } elseif ($row['type'] === 'today') {
                $submission['today'] = date('Y-m-d');

            }
        }
        return $submission;
    }

    function evaluateOdkExpression(string $expression, array $submission, $parentSubmission = null) {

        // Handle nested `if()` expressions correctly
        while (preg_match('/if\(([^,]+),([^,]+),([^()]*)\)/', $expression, $matches)) {
            $condition = trim($matches[1]);
            $trueValue = trim($matches[2]);
            $falseValue = trim($matches[3]);

            // Fix "=" to "==" for PHP compatibility
            $condition = preg_replace('/([^<>=!])=([^=])/', '$1 == $2', $condition);

            // Evaluate the condition first
            $evaluatedCondition = $this->evaluateOdkExpression($condition, $submission, $parentSubmission);

            // If the condition is invalid, stop the calculation and return early
            if ($evaluatedCondition === 'INVALID VALUE') {
                return 'INVALID VALUE'; // Exit immediately
            }

            // Evaluate true or false value based on condition
            $replacement = $evaluatedCondition
                ? $this->evaluateOdkExpression($trueValue, $submission, $parentSubmission)
                : $this->evaluateOdkExpression($falseValue, $submission, $parentSubmission);

            // Check if the replacement value is invalid and exit if so
            if ($replacement === 'INVALID VALUE') {
                return 'INVALID VALUE'; // Exit immediately if the replacement is invalid
            }

            // Replace the entire "if()" expression with the evaluated value
            $expression = preg_replace('/if\(([^,]+),([^,]+),([^()]*)\)/', $replacement, $expression, 1);
        }

        // Process remaining expressions (variables, numbers, operators, and strings)
        preg_match_all('/\$\{([^{}]+)\}|("[^"]*")|(\d+(\.\d+)?)|div|mod|[+\-*\/\(\)]|!=|==|>=|<=|=/', $expression, $matches);
        $tokens = $matches[0];
        $parsedExpression = '';

        foreach ($tokens as $token) {
            if (preg_match('/^\d+(\.\d+)?$/', $token)) {
                // If it's a number, keep it as is
                $parsedExpression .= ' ' . $token . ' ';
            } elseif (in_array($token, ['+', '-', '*', 'div', 'mod', '!=', '==', '>', '<', '>=', '<=', '(', ')'])) {
                // Handle operators
                $phpOperator = ($token === 'div') ? '/' : (($token === 'mod') ? '%' : $token);
                $parsedExpression .= ' ' . $phpOperator . ' ';
            } elseif (preg_match('/^\$\{([^{}]+)\}$/', $token, $varMatch)) {
                // Handle variable substitution
                $variableName = $varMatch[1];
                $value = $this->findValueInSubmission($variableName, $submission, $parentSubmission);

                if ($value === null || $value === '') {
                    $parsedExpression .= ' "" ';  // Represent empty values as an empty string
                } elseif (is_numeric($value)) {
                    $parsedExpression .= ' ' . $value . ' ';
                } else {
                    $parsedExpression .= ' "' . addslashes($value) . '" ';  // Preserve strings correctly
                }
            } elseif (preg_match('/^".*"$/', $token)) {
                // Preserve string literals as they are
                $parsedExpression .= ' ' . $token . ' ';
            }
        }

        // Fix possible errors by ensuring the expression is wrapped correctly
        $parsedExpression = preg_replace('/([^\d\+\-\*\/\(\)])\s*\)\s*\+/', '$1) +', $parsedExpression);

        // Check if the expression is valid before evaluating
        if (strpos($parsedExpression, ')') === false && strpos($parsedExpression, '(') === false) {
            return 'ERROR';
        }

        try {
            eval('$result = (' . $parsedExpression . ');');

            // If result is null, return 'ERROR'
            if ($result === null) {
                return 'ERROR';
            }

            return $result;
        } catch (Throwable $e) {
            return 'ERROR';
        }
    }

    function getCountSelected($variableName, $submission) {
        if (!empty($submission[$variableName])) {
            $rawValue = trim((string) $submission[$variableName]);
            $cleanedValue = preg_replace('/\s+/', ' ', $rawValue);
            // Split by space and count items
            $items = explode(' ', $cleanedValue);
            return count($items);
        }
        return 2; // Return default value if empty
    }

    function findValueInSubmission($key, $currentSubmission, $parentSubmission = null) {
        // Check the current level
        if (isset($currentSubmission[$key])) {
            return $currentSubmission[$key];
        }

        foreach ($currentSubmission as $k => $value) {
            if (is_array($value)) {
                $found = $this->findValueInSubmission($key, $value, null);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        // If not found, check the parent submission
        if ($parentSubmission !== null) {
            return $this->findValueInSubmission($key, $parentSubmission, null);
        }

        return null;
    }

    function generateConstrainedInteger($constraint, $submission)
    {
        // If no constraint given, return a random integer between 1 and 10
        if (!$constraint) {
            return rand(1, 10);
        }

        $min = PHP_INT_MIN;
        $max = PHP_INT_MAX;
        $exact = null;

        // Split the constraints into an array
        $constraints = preg_split('/\s+and\s+|\s+or\s+/', $constraint);

        // If no lower constraint given, set min to 0
        if (count($constraints) === 1 && (preg_match('/\.<(\d+)/', $constraints[0], $matches) || preg_match('/\.<=([0-9]+)/', $constraints[0], $matches))) {
            $min = 0;
        }

        // Process each constraint
        foreach ($constraints as $constraintItem) {
            if (preg_match('/\.(>=|<=|>|<|=)(\d+|\$\{(.+?)\})/', $constraintItem, $matches)) {
                $operator = $matches[1]; // The operator: >=, <=, >, <, =
                $value = $matches[2]; // The value to compare with

                // Check if the value is a variable reference
                if (preg_match('/^\$\{(.+?)\}$/', $value, $varMatch)) {
                    $variableName = $varMatch[1]; // Extract the variable name from ${}
                    // Fetch the value of the variable from $submission
                    if (isset($submission[$variableName]) || array_key_exists($variableName, $submission)) {
                        $value = (int) trim((string) $submission[$variableName]);
                    } else {
                        $value = 0; // Default value if the variable is not found
                    }
                } else {
                    // Get the specified value
                    $value = (int) $value;
                }

                // Adjust min/max/exact based on the operator
                switch ($operator) {
                    case '>=':
                        $min = max($min, $value);
                        break;
                    case '<=':
                        $max = min($max, $value);
                        break;
                    case '>':
                        $min = max($min, $value + 1);
                        break;
                    case '<':
                        $max = min($max, $value - 1);
                        break;
                    case '=':
                        $exact = $value;
                }
            }
        }

        // If no upper constraint given, set max to 100
        if ($max === PHP_INT_MAX) {
            $max = 100;
        }
        // If an exact value given, randomly choose between exact or a random number in the range
        if ($exact !== null) {
            return rand(0, 1) === 0 ? $exact : rand($min, $max);
        } else {
            return rand($min, $max);
        }
    }

    function generateConstrainedDecimal($constraint, $submission)
    {
        // If no constraint given, return a random decimal between 0 and 10
        if (!$constraint) {
            return mt_rand(0, 1000) / 100;
        }

        $min = -PHP_INT_MAX;
        $max = PHP_INT_MAX;
        $exact = null;

        // Split the constraints into an array
        $constraints = preg_split('/\s+and\s+|\s+or\s+/', $constraint);

        // If no lower constraint given, set min to 0
        if (count($constraints) === 1 && (preg_match('/\.<(\d+(\.\d+)?)$/', $constraints[0], $matches) || preg_match('/\.<=([0-9]+(\.[0-9]+)?)$/', $constraints[0], $matches))) {
            $min = 0;
        }

        // Process each constraint
        foreach ($constraints as $constraintItem) {
            if (preg_match('/\.(>=|<=|>|<|=)(\d+|\$\{(.+?)\})/', $constraintItem, $matches)) {
                $operator = $matches[1]; // The operator: >=, <=, >, <, =
                $value = $matches[2]; // The value to compare with

                if (preg_match('/^\$\{(.+?)\}$/', $value, $varMatch)) {
                    $variableName = $varMatch[1]; // Extract the variable name from ${}
                    // Fetch the value of the variable from $submission
                    if (isset($submission[$variableName]) || array_key_exists($variableName, $submission)) {
                        $value = (float) trim((string) $submission[$variableName]);
                    } else {
                        $value = 0; // Default value if the variable is not found
                    }
                } else {
                    // Get the specified value
                    $value = (float) $value;
                }

                // Adjust min/max/exact based on the operator
                switch ($operator) {
                    case '>=':
                        $min = max($min, $value);
                        break;
                    case '<=':
                        $max = min($max, $value);
                        break;
                    case '>':
                        $min = max($min, $value + 0.0001);
                        break;
                    case '<':
                        $max = min($max, $value - 0.0001);
                        break;
                    case '=':
                        $exact = $value;
                        break;
                }
            }
        }

        // If no upper constraint given, set max to 100
        if ($max === PHP_INT_MAX) {
            $max = 100;
        }

        // If an exact value given, randomly choose between exact or a random number in the range
        if ($exact !== null) {
            return rand(0, 1) === 0 ? $exact : mt_rand((int)($min * 100), (int)($max * 100)) / 100;
        } else {
            return mt_rand((int)($min * 100), (int)($max * 100)) / 100;
        }
    }

    function generateRandomGeopoint($minLat = -90, $maxLat = 90, $minLon = -180, $maxLon = 180, $minAlt = 0, $maxAlt = 5000)
    {
        $latitude = mt_rand($minLat * 1000000, $maxLat * 1000000) / 1000000;
        $longitude = mt_rand($minLon * 1000000, $maxLon * 1000000) / 1000000;
        $altitude = mt_rand($minAlt * 10, $maxAlt * 10) / 10; // Altitude with 1 decimal place
        return "{$latitude} {$longitude} {$altitude}";
    }

    function arrayToXml(array $data, SimpleXMLElement $xml, $formId, $xlsformVersion, $isRoot=true): void
    {
        if ($isRoot) {
            $xml->addAttribute('id', $formId);
            $xml->addAttribute('version', $xlsformVersion);
        }

        foreach ($data as $key => $value) {
            // Ensure valid XML tag names
            $key = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);

            if (is_array($value)) {
                if (array_is_list($value)) {
                    foreach ($value as $item) {
                        $subnode = $xml->addChild($key);
                        if (is_array($item)) {
                            $this->arrayToXml($item, $subnode, $formId, $xlsformVersion, false);
                        } else {
                            $subnode[0] = htmlspecialchars((string) $item, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                        }
                    }
                } else {
                    $subnode = $xml->addChild($key);
                    $this->arrayToXml($value, $subnode, $formId, $xlsformVersion, false);
                }
            } else {
                $xml->addChild($key, htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8'));
            }
        }
    }
}
