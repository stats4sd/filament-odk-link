<?php

/** @noinspection ALL */

/** @noinspection PhpStanGlobal */

namespace Stats4sd\FilamentOdkLink\Services;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Stats4sd\FilamentOdkLink\Contracts\FormOwner;
use Stats4sd\FilamentOdkLink\Exports\ChoiceListModelsExport;
use Stats4sd\FilamentOdkLink\Models\OdkLink\RequiredMedia;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Stats4sd\FilamentOdkLink\Support\ConfiguredModelRegistry;
use Stats4sd\FilamentOdkLink\Support\CurrentOwner;

class HelperService
{
    public static function getModels(): Collection
    {
        return app(ConfiguredModelRegistry::class)->classes();
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

    /** @return Collection<int, covariant Collection<string, covariant string|null>>
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

        $header = str_getcsv(array_shift($lines));
        $rows = [];

        foreach ($lines as $line) {
            $rows[] = new Collection(array_combine($header, str_getcsv($line)));
        }

        return new Collection($rows);
    }

    public static function getCurrentOwner(): (Model & FormOwner) | null
    {
        return app(CurrentOwner::class)->current();
    }

    public static function getModelByTablename(string $tableName): ?Model
    {
        return app(ConfiguredModelRegistry::class)->findByTable($tableName);
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
