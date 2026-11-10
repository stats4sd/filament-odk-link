<?php

namespace Stats4sd\FilamentOdkLink\Exports\XlsformExport;

use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformLanguages\Locale;
use Stats4sd\FilamentOdkLink\Services\XlsformInstanceNameResolver;

class XlsformSettingsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithStyles, WithTitle, ShouldQueue
{
    /** @var array<string, string>|null */
    private ?array $settings = null;

    public function __construct(public Xlsform $xlsform) {}

    public function collection(): Collection
    {
        return collect([$this->settings()]);
    }

    public function headings(): array
    {
        return array_keys($this->settings());
    }

    public function title(): string
    {
        return 'settings';
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    /** @return array<string, string> */
    private function settings(): array
    {
        if ($this->settings !== null) {
            return $this->settings;
        }

        $defaultLanguage = $this->defaultLanguage();

        $this->settings = [
            'form_id' => $this->xlsform->odk_id ?? Str::slug($this->xlsform->title),
            'form_title' => $this->xlsform->title,
            'version' => Carbon::now()->toDateTimeString(),
            'instance_name' => app(XlsformInstanceNameResolver::class)->resolve($this->xlsform),
            ...($defaultLanguage ? ['default_language' => $defaultLanguage] : []),
            'allow_choice_duplicates' => 'yes',
        ];

        return $this->settings;
    }

    private function defaultLanguage(): ?string
    {
        $templateDefaultLanguage = $this->xlsform->xlsformTemplate?->default_language;

        if (blank($templateDefaultLanguage)) {
            return null;
        }

        $formLanguages = $this->xlsform->locale_list
            ->map(fn (Locale $locale) => "{$locale->language->name} ({$locale->language->iso_alpha2})");

        if ($formLanguages->contains($templateDefaultLanguage)) {
            return $templateDefaultLanguage;
        }

        Log::warning("Leaving default_language out of the settings for xlsform {$this->xlsform->id}: the form has no language called \"{$templateDefaultLanguage}\".", [
            'xlsform_id' => $this->xlsform->id,
            'form_languages' => $formLanguages->values()->all(),
        ]);

        return null;
    }
}
