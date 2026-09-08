<?php

/** @noinspection ALL */

/** @noinspection PhpStanGlobal */

namespace Stats4sd\FilamentOdkLink\Services;

use Filament\Facades\Filament;
use HaydenPierce\ClassFinder\ClassFinder;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Stats4sd\FilamentOdkLink\Exports\ChoiceListModelsExport;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Interfaces\WithXlsforms;
use Stats4sd\FilamentOdkLink\Models\OdkLink\RequiredMedia;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;

class HelperService
{
    public static function getModels(): Collection
    {
        $models = ClassFinder::getClassesInNamespace('App\Models');
        $packageModels = ClassFinder::getClassesInNamespace('Stats4sd\FilamentOdkLink\Models');

        return collect($models)->merge($packageModels);
    }

    public static function getOdkVariablesToIgnore(): array
    {
        return [
            '__id',
            'instanceID',
            'meta',
            'deviceid',
            'start_time',
            'end_time',
            '_id',
            'uuid',
            '__version__',
            '_xform_id_string',
            '_uuid',
            '_attachments',
            '_status',
            '_geolocation',
            '_submission_time',
            '_tags',
            '_notes',
            '_validation_status',
            '_submitted_by',
        ];
    }

    /** @return Collection<int, string|null>
     * @throws FileNotFoundException
     */
    public static function importCsvFileToCollection(string $filePath): Collection
    {
        // Read CSV file content, call trim() to remove last blank line
        $csvFileContent = trim(File::get($filePath));

        // remove newlines within cells
        $csvFileContent = preg_replace('/\"(.+)\n\"/', '$1', $csvFileContent);

        // remove \u{FEFF} within cells
        $csvFileContent = str_replace("\u{FEFF}", '', $csvFileContent);

        // Split by new line. Use the PHP_EOL constant for cross-platform compatibility.
        $lines = explode(PHP_EOL, $csvFileContent);

        // Extract the header and convert it into a Laravel collection.
        $header = collect(str_getcsv(array_shift($lines)));

        // Map through the rows and combine them with the header to produce the final collection.
        return collect($lines)->map(function ($row) use ($header): Collection {
            return $header->combine(str_getcsv($row));
        });
    }

    // helper function to return the currently selected team in a Filament panel.
    // useful because it always returns a Team::class (or null), so you can use it in a type hint.
    public static function getCurrentOwner(): WithXlsforms | Model | null
    {
        if (Filament::hasTenancy() && is_a(Filament::getTenant(), WithXlsforms::class)) {

            return Filament::getTenant();
        }

        return null;
    }

    // helper function to get model by table name
    public static function getModelByTablename($tableName)
    {
        // get all models
        $classes = HelperService::getModels();

        foreach ($classes as $class) {
            $model = new $class;

            if ($model->getTable() == $tableName) {
                // found a matched model
                return $model;
            }
        }

        // cannot find a matched model
        return null;
    }

    // TODO: Move this into the ODK Link package when we move over the ChoiceList stuff
    /**
     * Creates a new csv lookup file from the database;
     */
    public function createCsvLookupFile(Xlsform | XlsformTemplate $xlsform, RequiredMedia $requiredMedia): string
    {

        $choiceList = $requiredMedia->choiceList;

        $filePath = 'xlsforms/' . $xlsform->getKey() . '/' . $requiredMedia->name;

        // check if the folder exists; if not, create it
        if (! Storage::disk(config('filament-odk-link.storage.xlsforms'))->exists('xlsforms')) {
            Storage::disk(config('filament-odk-link.storage.xlsforms'))->makeDirectory('xlsforms');
        }

        if (! Storage::disk(config('filament-odk-link.storage.xlsforms'))->exists('xlsforms/' . $xlsform->getKey())) {
            Storage::disk(config('filament-odk-link.storage.xlsforms'))->makeDirectory('xlsforms/' . $xlsform->getKey());
        }

        Excel::store(
            new ChoiceListModelsExport($choiceList, $xlsform),
            $filePath,
            config('filament-odk-link.storage.xlsforms')
        );

        // TODO: Explore if we need select_one_from_external_file support.

        return Storage::disk(config('filament-odk-link.storage.xlsforms'))->path($filePath);
    }
}
