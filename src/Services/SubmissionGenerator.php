<?php

namespace Stats4sd\FilamentOdkLink\Services;

use Carbon\Carbon;
use Faker\Generator;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Stats4sd\FilamentOdkLink\Models\XlsformVersion;

class SubmissionGenerator {

    protected $faker;
    private $xPathHandler;
    protected Collection $choiceNames;


    /**
     * @throws BindingResolutionException
     */
    public function __construct(

        // The xlsform
        protected XLsformVersion    $xlsformVersion,
        // variables and choices pulled from the Xlsform definition
        protected Collection $variables,
        protected Collection $choices,

        // The generated content - to be built up during the processing;
        protected Collection $content,

        // The variable index to begin at (0 by default, but changes if we are inside a repeat group)
        protected int        $startIndex = 0,

        // ** Variables that get updated during processing ** //
        // The current index - how far are we through the list of variables?
        protected int $index = 0,
        // The current 'root' - used to correctly add group prefixes to variables names;
        protected string $root = '',

        // ** Repeat handling variables ** //
        // These will be null when not inside a repeat group
        // If inside a repeat, we should know which iteration it is.
        protected ?int $repeatPos = null,

        // If inside a repeat, include the current content from outside the repeat (for reference when calculating ${varname} inside XPath expressions)
        protected ?Collection $referenceContent = null
    )
    {
        $this->faker = $this->withFaker();
        $this->xPathHandler = new XPathExpressionHandler(variables: $this->variables, choices: $this->choices, xlsformVersion: $this->xlsformVersion);

        // prepare choice names for the Choices picker
        $this->choiceNames = $this->choices->map(fn($choice) => $choice->pluck('name'));
    }

    /**
     * Get a new Faker instance.
     *
     * @return Generator
     * @throws BindingResolutionException
     */
    protected function withFaker(): Generator
    {
        return Container::getInstance()->make(Generator::class);
    }

    /**
     * Process a list of variables in the order defined by their index (position in the XLS Survey)
     * For the root, this is the complete list of variables in the form. (This is the default option)
     * For repeat groups, this is the complete list of variables *within the repeat*.
     * @throws BindingResolutionException
     */
    public function processVariablesSequentially(?Collection $variablesToProcess = null): Collection
    {
        // if no variables have been passed, assume we are processing the entire form:
        if(!$variablesToProcess) {
            $variablesToProcess = $this->variables;
        }
        dump('variablesToProcess', $variablesToProcess);
        while ($this->index < $variablesToProcess->pluck('index')->max()) {

            // handle case where the variable doesn't exist (equates to a blank line in the Excel file)
            if(!isset($variablesToProcess[$this->index]) || !$variablesToProcess[$this->index]['type']) {
                $this->index++;
                continue;
            }
            $this->processVariable($variablesToProcess[$this->index]);
            $this->index ++;
        }

        return $this->content;
    }


    /**
     * Process a single variable
     * @throws BindingResolutionException
     */

    protected function processVariable($variable): void
    {
        // TEMPORARY CODE
        // Hardcode forms selected

        //dump('variable', $variable);

        if($variable['name'] === "surveyforms") {
            $this->content[$this->root . $variable['name']] = '10_1 9 13';
            return;
        }


        // If the variable is a begin group or begin repeat, append the group name to the root instead of creating a value;
        if( preg_match('/begin group|begin_group/', $variable['type']) === 1) {
            $this->root .= $variable['name'] . '/';
            return;
        }

        // if the variable is an end group, remove the group name from the root istead of creating a value;
        if( preg_match('/end group|end_group/', $variable['type']) === 1) {
            $this->root = Str::of($this->root)->replaceLast($variable['name'] . '/', '');
            return;
        }

        // if the variable is a begin repeat:
        // - figure out how many repeats are needed;
        // - create a new SubmissionGenerator for each repeat iteration and create the new content

        if( preg_match('/begin repeat|begin_repeat/', $variable['type']) === 1) {

           // dump('########################## TESTING', $variable, $this->variables);


            // check repeat count - if none is set, randomly choose 0 - 5 repeats
            if($repeatCount = $variable['repeat_count']) {

                $repeatCount = $this->xPathHandler->evaluateXPathExpression($repeatCount, $this->content, $this->repeatPos, $this->referenceContent);

            } else {
                $repeatCount = $this->faker->numberBetween(0, 5);
            }


            // find the end of the repeat


                $endRepeat = $this->variables->filter(function($varToCheck) use ($variable) {
                    return $variable['name'] === $varToCheck['name']
                    && preg_match('/end repeat|end_repeat/', $varToCheck['type']) === 1;
                })->first();

                if(!$endRepeat) {
                    throw new \ParseError('It looks like the repeat group ' . $variable['name'] . ' does not have a corresponding end repeat. Please check the XLS form definition.');
                }

                $endRepeatIndex = $endRepeat['index'];


            // grab all variables in between the current index (the start repeat) and the endRepeatIndex (the end repeat)
            $repeatVariables = $this->variables
                ->skipUntil(fn($var) => $var['index'] === $this->index + 1)
                ->takeUntil(fn($var) => $var['index'] === $endRepeatIndex + 1);


            // build up collection of repeat entries, ready to add to the core content;
            $repeatEntries = collect([]);
dump($repeatCount);
            // for each needed iteration, create a new SubmissionGenerator to handle the group.
            for($j = 0; $j < $repeatCount; $j++) {

                $repeatEntries->push(
                    (new SubmissionGenerator(
                        xlsformVersion: $this->xlsformVersion,
                        variables: $this->variables,
                        choices: $this->choices,
                        content: collect([]),
                        startIndex: $this->index,
                        index: $this->index,
                        root: $this->root,
                        repeatPos: $j + 1,
                        // merge content (current layer) with referencecontent (previous layer)
                        // This allows for ${var} replacemenet within nested repeat groups
                        referenceContent: $this->content->merge($this->referenceContent))
                    )->processVariablesSequentially($repeatVariables)
                );

            }

            $this->content[$this->root . $variable['name']] = $repeatEntries;

            // now the repeat group is complete, remove the group name from the root:
            $this->root = Str::of($this->root)->replaceLast($variable['name'] . '/', '');

            // reset the index to the end of the repeat group;
            $this->index = $endRepeatIndex;

            return;
        }

        // otherwise, generate a value for the variable and add it into the content object.
        $this->content[$this->root . $variable['name']] = $this->generateValue($variable);

    }

    /**
     * Function to get the full list of choices available to a select_one or select_multiple question
     * The function first checks if a csv lookup is involved by checking for search() in the appearance property;
     *  - If a csv lookup is used, it uses the form metadata to find the correct MySQL table or view, runs the query and then merges with any hard-coded variables from the choices sheet.
     *  - Otherwise, it gets the correct list of choice names from the choices sheet.
     * @param array $variable
     * @return Collection $xlsChoiceList
     */
    private function getChoicesList(array $variable): Collection
    {
        $xlsChoiceListName = [];
        preg_match('/(?:select_one|select_multiple) (.+)/', $variable['type'], $xlsChoiceListName);

        $xlsChoiceListName = $xlsChoiceListName[1];

        $xlsChoiceList = $this->choiceNames[$xlsChoiceListName];

        // check if select is from choice list or db table / csv lookup:
        $matches = [];

        // if searching csv file with no filters
        if (preg_match('/search\(\'(.+)\'\)/', $variable['appearance'], $matches)) {

            // find the matching database table
            $lookups = collect($this->xlsformVersion->xlsform->xlsformTemplate->csv_lookups);

            // this will fail if there is no lookup;
            $lookup = $lookups->where('csv_name', $matches[1])->first();

            $choiceQuery = DB::table($lookup['mysql_name']);

            if ((integer)$lookup['per_team'] === 1) {
                $choiceQuery = $choiceQuery->where('owner_id', $this->xlsformVersion->xlsform->owner_id)
                ->where('owner_type', $this->xlsformVersion->xlsform->owner_type);
            }
            $choiceList = $choiceQuery->get();

            // there must be only 1 non-integer item in the $xlsChoiceList
            foreach ($xlsChoiceList as $choiceName) {

                // ignore integer choices - they are additional hard-coded options;
                if (is_int($choiceName)) {
                    continue;
                }

                // prepend the options from the database table
                $choiceList = $choiceList->pluck($choiceName);
                $xlsChoiceList = $choiceList
                    ->merge($xlsChoiceList)
                    ->unique()
                    ->filter(fn($item) => $item !== $choiceName);
                break;
            }
        }

        // if searching csv file with a 'matches' filter;
        $matches = [];
        if (preg_match('/search\([\'\"](.+)[\'\"], [\'\"]matches[\'\"], [\'\"](.+)[\'\"], \${(.+)}\)/', $variable['appearance'], $matches)) {

            // find the matching database table
            $lookups = collect($this->xlsformVersion->xlsform->xlsformTemplate->csv_lookups);
            $lookup = $lookups->where('csv_name', $matches[1])->first();

            $choiceQuery = DB::table($lookup['mysql_name']);

            if ((integer)$lookup['per_team'] === 1) {
                $choiceQuery = $choiceQuery->where('owner_id', $this->xlsformVersion->xlsform->owner_id)
                ->where('owner_type', $this->xlsformVersion->xlsform->owner_type);
            }

            // find correct submission property, (as it might have a prepended group name)
            $previousProp = $this->content->keys()->filter(fn($key) => Str::of($key)->endsWith($matches[3]))->first();

            // add matches filter
            $choiceQuery = $choiceQuery->where($matches[2], '=', $this->content[$previousProp]);
            $choiceList = $choiceQuery->get();

            // there must be only 1 non-integer item in the $xlsChoiceList
            foreach ($xlsChoiceList as $choiceName) {

                // ignore integer choices - they are additional hard-coded options;
                if (is_int($choiceName)) {
                    continue;
                }

                // prepend the options from the database table
                $choiceList = $choiceList->pluck($choiceName);
                $xlsChoiceList = $choiceList
                    ->merge($xlsChoiceList)
                    ->unique()
                    ->filter(fn($item) => $item !== $choiceName);
                break;
            }

        }
        return $xlsChoiceList;
    }

    /**
     * @param array $variable - the variable properties from the survey sheet
     * @param integer? $position - if the process is currently inside a repeat group, what is the pos(..)? (What number repeat is it?)
     * @param Collection? $repeatSubmission - if the process is currently inside a repeat group, this is the data generated already for this current repeat.
     * @return mixed|string|void|null
     */
    private function generateValue(array $variable)
    {

        switch ($variable['type']) {
            case "start":
            case "end":
                return Carbon::now()->toISOString();
            case "today":
                return Carbon::now()->format('Y-m-d');

            case null:
            case "note":
                return null;

            case "geopoint":
                // return space-separated string: lat long altitude accuracy
                return $this->faker->latitude . " " .
                    $this->faker->longitude . " " .
                    $this->faker->numberBetween(20, 2000) . " " .
                    $this->faker->numberBetween(5, 200);
            case (bool)preg_match('/select_one /', $variable['type']):

                $xlsChoiceList = $this->getChoicesList($variable);

                // return a random entry from the list;
                return $this->faker->randomElement($xlsChoiceList);

            case (bool)preg_match('/select_multiple /', $variable['type']):

                $xlsChoiceList = $this->getChoicesList($variable);

                $variables = $this->faker->randomElements(
                    $xlsChoiceList,
                    $this->faker->numberBetween(0, count($xlsChoiceList)),
                    false
                );

                return collect($variables)->join(' ');

            case "deviceid":
                return "faker:" . $this->faker->randomNumber(9);

            case "date":
                return $this->faker->date();

            case "integer":
                return $this->faker->numberBetween(0, 50);
            case "decimal":
                return $this->faker->randomFloat(2,0,50);

            case "calculate":
                // dump('found calculate ' . $variable['calculation'] . '- evaluating');
                return $this->xPathHandler->evaluateXPathExpression($variable['calculation'], $this->content, $this->repeatPos, $this->referenceContent);

            case "text":
                return $this->faker->sentence();
            default:
                return "";
        }

    }

}
