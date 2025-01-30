<?php

namespace Stats4sd\FilamentOdkLink\Imports\XlsformTemplate;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Events\AfterImport;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceList;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\SurveyRow;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformModuleVersion;

class XlsformTemplateWorkbookImport implements WithMultipleSheets, ShouldQueue, WithChunkReading, WithEvents
{

    use RegistersEventListeners;
    use Importable;

    public function __construct(public XlsformModuleVersion $xlsformModuleVersion, public Collection $translatableHeadings)
    {
    }

    // Specify the "survey" sheet
    public function sheets(): array
    {
        return [
            'survey' => new XlsformTemplateSurveyImport($this->xlsformModuleVersion, $this->translatableHeadings['survey']),
            'choices' => new XlsformTemplateChoicesImport($this->xlsformModuleVersion, $this->translatableHeadings['choices']),
        ];
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function afterImport(AfterImport $event): void
    {
        // find all Survey Rows linked to the XlsformTemplate that were not updated during the import... and delete them.
        $surveyRowsToDelete = $this->xlsformModuleVersion
            ->surveyRows()
            ->select(['survey_rows.id', 'survey_rows.updated_during_import'])
            ->get()
            ->filter(fn(SurveyRow $surveyRow) => $surveyRow->updated_during_import === false);

        // we need to actually get the models instead of deleting them with a query, because we need to trigger the deleting event.
        SurveyRow::destroy($surveyRowsToDelete->pluck('id'));

        // we also need to delete the choiceLists that were not updated during the import.
        $choicesToDelete = $this->xlsformModuleVersion
            ->choiceListEntries()
            ->where('owner_id', null) // do not delete entries owned by a team.
            ->select(['choice_list_entries.id', 'choice_list_entries.updated_during_import'])
            ->get()
            ->filter(fn(ChoiceListEntry $choiceListEntry) => $choiceListEntry->updated_during_import === false);

        ChoiceListEntry::destroy($choicesToDelete->pluck('id'));

        ChoiceList::has('choiceListEntries', '=', 0)->delete();
    }
}
