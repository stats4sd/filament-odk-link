<?php

namespace Stats4sd\FilamentOdkLink\Exports\XlsformExport;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Stats4sd\FilamentOdkLink\Concerns\NotifiesOnJobFailure;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Throwable;


class XlsformWorkbookExport implements WithMultipleSheets, ShouldQueue
{
    use NotifiesOnJobFailure;

    public function __construct(public Xlsform $xlsform, public ?Authenticatable $user = null)
    {
    }

    public function sheets(): array
    {
        $sheets = [
            new XlsformSurveyExport($this->xlsform),
            new XlsformChoicesExport($this->xlsform),
            new XlsformSettingsExport($this->xlsform),
        ];

        if ($this->xlsform->xlsformTemplate->templateEntityLists()->exists()) {
            $sheets[] = new XlsformEntitiesExport($this->xlsform);
        }

        return $sheets;
    }

    public function failed(Throwable $exception): void
    {
        $this->xlsform->updateQuietly(['processing' => false]);

        $this->notifyJobFailure(
            "Form file generation failed: {$this->xlsform->title}",
            $exception,
            $this->user ?? $this->superAdmins(),
        );
    }
}
