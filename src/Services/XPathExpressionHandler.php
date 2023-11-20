<?php

namespace Stats4sd\FilamentOdkLink\Services;

use Faker\Generator;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ParseError;
use Stats4sd\FilamentOdkLink\Models\XlsformVersion;
use Throwable;

class XPathExpressionHandler
{
    protected Generator $faker;

    /**
     * @throws BindingResolutionException
     */
    public function __construct(
        protected Collection $variables,
        protected Collection $choices,
        protected XlsformVersion $xlsformVersion,
    ) {
        $this->faker = $this->withFaker();
    }

    /**
     * Get a new Faker instance.
     *
     * @throws BindingResolutionException
     */
    protected function withFaker(): Generator
    {
        return Container::getInstance()->make(Generator::class);
    }

    public function evaluateXPathExpression($expression, $content, $position = null, $referenceContent = null)
    {

        dump('start expression', $expression);
        // given we can't just "calculate the XPath expression", we need to convert the expression to something that PHP can evaluate.
        // Eventually, there should probably be an accompanying dictionary of things to handle...
        $expression = Str::of($expression);

        // leave the 2nd prop of a jr:choice-name() expression, as it's needed to find the correct choice list...
        $expression = $expression->replaceMatches('/jr:choice-name\((.+)\,\s*[\'\"]?\$\{(.+)\}[\'\"]?\)/', function ($matches) {
            // match 1 = the name of the choice to find the label for
            // match 2 = the question to use to find the choice for...
            // find the correct choice list...
            $varToFind = Str::of($matches[2])->replaceMatches('/[${}\']/', '')->trim();

            $varFound = $this->variables->filter(fn ($var) => $var['name'] === (string) $varToFind)->first();

            // get the choice list...
            $choiceList = Str::of($varFound['type'])->replaceMatches('/(?:select_one|select_multiple)/', '')->trim();

            // return the original jr:choice-name for processing after some other replacements;
            // replace the 2nd property with the choice list to ease the later processing;
            return 'jr:choice-name(' . $matches[1] . ', ' . $choiceList . ')';
        });

        // ************** handle ${varname} references: ***************** //
        $matches = [];
        preg_match_all('/\$\{([A-z_-]+)\}/', $expression, $matches);

        $varsToReplace = $matches[1];
        foreach ($varsToReplace as $var) {
            if ($var === 'species') {
                dump('SUBCHECK: ', $content, $referenceContent);
            }
            // first, check if the $var is in the full variables list. If not, there is a form syntax error:
            if ($this->variables->pluck('name')->doesntContain($var)) {
                throw new ParseError('No variable with name ${' . $var . '} was found in the form.');
            }

            // find $var with any potential group prefix:
            $previousProp = $content->keys()->filter(fn ($key) => Str::of($key)->endsWith($var))->first();
            dump($previousProp);
            if ($previousProp) {
                $replacement = $content[$previousProp];
                dump($replacement);
                if (! is_numeric($replacement)) {
                    $replacement = '"' . $replacement . '"';
                }

                $expression = $expression->replace('${' . $var . '}', $replacement);

                if ($previousProp === 'trt_main/species') {
                    dump('hello there', $expression);
                }

                continue;
            }

            // if the previousProp is not found in the main $content, check the $reference content...
            $previousProp = $referenceContent?->keys()->filter(fn ($key) => Str::of($key)->endsWith($var))->first();
            if ($previousProp) {
                $replacement = $referenceContent[$previousProp];
                if (! is_numeric($replacement)) {
                    $replacement = '"' . $replacement . '"';
                }
                $expression = $expression->replace('${' . $var . '}', $replacement);

                continue;
            }

            // if we haven't found the variable so far, it is likely nested inside an already-complete repeat group.
            // 1. Find any complete repeat groups (variable is collection)
            // 2. Check inside collection for the property name;
            // 3. If found, process...

            $innerRepeatValue = $this->checkContentForRepeats($content, $var);

            // if no inner repeat value is found in the main content, check the reference content;
            if (! $innerRepeatValue && $referenceContent) {
                $innerRepeatValue = $this->checkContentForRepeats($referenceContent, $var);
            }

            if ($innerRepeatValue) {
                // prepare the array of content
                // dump('inner repeat value found');
                // dump('pre expression', $expression, $var);

                $replacement = '[';
                foreach ($innerRepeatValue as $item) {
                    $replacement .= "'" . $item . "','";
                }
                $replacement .= ']';
                $expression = $expression->replace('${' . $var . '}', $replacement);

                // dump('post expression', $expression);

                continue;
            }

            // if we get this far, there is no previous prop found. This means that the ${var} being referenced either:
            // 1. does not exist due to relevancy
            // 2. is inside a repeat group that had 0 iterations this time round.
            // 3. is later on in the form and has not yet been calculated.
            // For options 1 and 2, this is fine and we can return an empty string. For option 3, there's not much we can do until we refactor...

            $expression = $expression->replace('${' . $var . '}', '');

        }

        // ************** handle position(..) references: ***************** //
        $expression = $expression->replace('position(..)', $position ?? '1');

        // ************** count-selected() to count() ***************** //
        $expression = $expression->replace('count-selected()', 'count([])');
        $expression = $expression->replace('count-selected("")', 'count([])');

        $expression = $expression->replaceMatches('/count-selected\([\'\"]([A-z0-9\_\-\s]+)[\'\"]\)/', function ($matches) {
            $output = 'count([';
            foreach (explode(' ', $matches[1]) as $item) {
                $output .= "'" . $item . "',";
            }

            $output .= '])';

            return $output;
        });

        // handle case where counted variable does not exist (due to relevancy or no repeats)
        $expression = $expression->replace('count()', '');

        // ************** handle selected-at() function ***************** //
        $expression = $expression->replaceMatches('/selected-at\([\'\"]*([A-z0-9\_\-\s]+)[\'\"]*\,([A-z0-9\_\-\s]+)\)/', function ($matches) {
            // match 1 = variable;
            // match 2 = the position to get from;

            $items = explode(' ', $matches[1]);
            $index = eval('return ' . $matches[2] . ';');

            return '"' . $items[$index] . '"';

        });

        // ************** handle jr:choice-name() function ***************** //
        $expression = $expression->replaceMatches('/jr:choice-name\([\"\']?([A-z0-9-]+)[\"\']?\,\s*[\"\']?([A-z0-9-\s]+)[\"\']?\)/', function ($matches) {
            // match 1 = the name of the choice to find the label for
            // match 2 = name of the choice list;
            dump($matches, $this->choices);
            $choices = $this->choices[(string) $matches[2]]->pluck('label', 'name');

            return '"' . $choices[$matches[1]] . '"';
        });

        // ************** handle if() function ***************** //
        // NOTE - does not handle situations where 1st or 2nd property of if() statement contains a comma, so pre-filter for that:[

        $expression = $expression->replaceMatches('/if\((.+)\,(.+)\,(.+)\)/', function ($matches) {
            // match 1 = boolean query
            // match 2 = result if statement === true
            // match 3 = result if statement === false

            try {

                // turn "=" into a php-friendly "=="
                $statement = Str::of($matches[1])->replace('=', '==');

                // dump('if-test', $statement);
                $test = eval('return ' . $statement . ';');

                if ($test) {
                    return '"' . $matches[2] . '"';
                }

                return '"' . $matches[3] . '"';

            } catch (ParseError $exception) {
                // this cannot handle situations where the query part of the if() statement includes a comma. This is the likely cause of this ParseError exception

                // for now, return random string;
                return $this->faker->words(1);

            } catch (Throwable $exception) {
                return $this->faker->words(1);
            }

        });

        // ************** handle pulldata() function ***************** //
        // search for strings matching: pulldata('something', 'something', 'something', something)
        $test = false;
        if ($expression->contains('treatment_list')) {
            $test = true;
        }
        $expression = $expression->replaceMatches('/pulldata\([\'\"]+(.+)[\'\"]+\,\s*[\'\"]+(.+)[\'\"]+\,\s*[\'\"]+(.+)[\'\"]+\,\s*(.+)\)/', function ($matches) {
            // match 1 = csv file name (
            // match 2 = column header to use for the return value
            // match 3 = column header to use for search
            // match 4 = the value to search
            $csvName = (string) $matches[1];
            $returnHeader = (string) $matches[2];
            $searchHeader = (string) $matches[3];
            $searchValue = (string) $matches[4]; // might be surrounded by quotes. Might not...

            // if the $searchValue is a string, unwrap it from quotes
            $searchValue = Str::of($searchValue);
            if ($searchValue->startsWith(["'", '"']) || $searchValue->endsWith(["'", '"'])) {
                $searchValue = $searchValue->trim("'")->trim('"');
            }

            // find the db table that matches the csv file
            $lookups = collect($this->xlsform->xlsform->csv_lookups);
            $lookup = $lookups->where('csv_name', $csvName)->first();

            $choiceQuery = DB::table($lookup['mysql_name']);

            if ((int) $lookup['per_team'] === 1) {
                $choiceQuery = $choiceQuery->where('team_id', $this->xlsform->team->id);
            }
            $choiceList = $choiceQuery->get();

            // find the item in the choice list where the search column === search value
            return '"' . $choiceList->pluck($returnHeader, $searchHeader)[(string) $searchValue] . '"';

        });

        // ************** handle coalesce() function ***************** //
        // search for strings matching: coalesce('something', 'something')
        // NOTE - this does not work when one or both parameters of the coalesce function include commas...

        $expression = $expression->replaceMatches('/coalesce\((.+)\,(.+)\)/', function ($matches) {
            return $matches[1] . ' ?? ' . $matches[2];
        });

        // ************** handle translate() function ***************** //
        // search for strings matching: translate('string to find', 'needle', 'replace')
        // NOTE - this does not work when one or both parameters of the coalesce function include commas...

        $expression = $expression->replaceMatches('/translate\((.+)\,\s*[\'\"](.+)[\'\"],\s*[\'\"](.+)[\'\"]\)/', function ($matches) {
            // $matches[1] -> the string to search
            // $matches[2] -> the substring to find
            // $matches[3] -> the replkacment
            return (string) Str::of($matches[1])->replace($matches[2], $matches[3]);
        });

        // when replacing expression components with strings, we may end up with multiple quote marks stacked together. Remove them before evaluating:
        // iterate over and over until all double quotes have been swapped with single quotes.
        // but then we might have " something " something else " ...
        do {

            // if double quotes are at the start, replace with 1:
            $expression = $expression->replaceMatches('/^[\"\'][\s]*[\"\']/', function ($matches) {
                return '"';
            });

            // if double quotes are at the end, replace with 1:
            $expression = $expression->replaceMatches('/[\"\'][\s]*[\"\']$/', function ($matches) {
                return '"';
            });

            // if double quotes are in the middle, concatenate the strings... but only if there are
            $expression = $expression->replaceMatches(('/(?=[\"\'].+)[\"\'][\s]*[\"\'](?=.+[\"\'])/'), function ($matches) {
                return '"';
            });

            $quotesFound = preg_match('/\"[\s]*\"/', (string) $expression);

        } while ($quotesFound === 1);

        // HANDLE RESULT Calculation
        dump('result calc');
        dump((string) $expression);

        try {
            $result = eval('return  ' . $expression . ';');
        } catch (ParseError $exception) {
            // if the resulting calculate cannot be parsed, simply return a random string.
            // TODO: enable user to specify if un-evaluatable calculate should return a random value based on the given parameters or halt execution and return the error.

            $result = $this->faker->words(1, true);
        }

        return $result;

    }

    public function checkContentForRepeats(Collection $content, string $varName): ?array
    {
        // start off with null;
        $previousProp = null;

        dump('checking content for repeats');

        foreach ($content as $index => $value) {
            if ($value instanceof Collection) {
                // check inside the collection...

                // dump('found repeat: ' . $index);
                $previousProp = $this->checkRepeatForVariableName($value, $varName);

                // if the prop has been found inside a repeat collection, stop checking other repeat collections;
                if ($previousProp) {
                    break;
                }
            }
        }
        dump('returning value from checkContentForRepeats', $previousProp);

        return $previousProp;
    }

    /**
     * Check inside a completed repeat group collection to search for a variable name referenced in the form using ${varName}.
     */
    public function checkRepeatForVariableName(Collection $repeatCollection, string $varName): ?array
    {
        // as we are checking repeats, the result will be an array, that can then be processed into a string based on the surrounding expression
        // start off with an empty array;
        $previousProp = null;
        $returnValue = [];

        dump('looking for variable ' . $varName);
        dump('checking repeat for variable name', $repeatCollection);
        // when outside of a repeat, ${innerRepeatVarName} will return the entire nodeset of values.
        // This means, if there are 5 repeats and ${varName} has a non-null value in 4 of them, the expression ${varName} should be replaced by all the values.
        foreach ($repeatCollection as $repeatInstance) {
            $previousProp = $repeatInstance->keys()->filter(fn ($key) => Str::of($key)->endsWith($varName))->first();

            if ($previousProp) {
                dump('prop found', $repeatInstance[$previousProp]);
                $returnValue[] = $repeatInstance[$previousProp];
            }
        }

        if ($previousProp) {
            // dump('returning value from checkRepeatForVariableName...', $returnValue);
            return $returnValue;
        }

        return null;

    }
}
