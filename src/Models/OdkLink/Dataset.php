<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Contracts\Service\Attribute\Required;

class Dataset extends Model
{
    protected $table = 'datasets';
    protected $guarded = [];


    public function odkDatasets(): HasMany
    {
        return $this->hasMany(OdkDataset::class);
    }

    public function variables(): HasMany
    {
        return $this->hasMany(DatasetVariable::class);
    }

    // A dataset may hold data collected from multiple xlsforms. Xlsform sections table acts as the "pivot" table.
    public function xlsformTemplateSections(): HasMany
    {
        return $this->hasMany(XlsformTemplateSection::class);
    }

    public function xlsformTemplateSources(): BelongsToMany
    {
        return $this->belongsToMany(XlsformTemplate::class, 'xlsform_template_sections')
            ->withPivot([
                'structure_item',
                'is_repeat',
                'schema',
            ])
            ->using(XlsformTemplateSection::class);
    }

    // A dataset may be used as a source for xlsformtemplate lookup data
    // Using the required_media as a pivot table
    public function requiredMedia(): HasMany
    {
        return $this->hasMany(RequiredMedia::class);
    }

    // xlsform templates that use this dataset as a source
    public function xlsformTemplates(): BelongsToMany
    {
        return $this->belongsToMany(XlsformTemplate::class, 'required_media')
            ->withPivot([
                'name',
                'type',
                'is_static',
                'exists_on_odk',
            ])
            ->using(RequiredMedia::class);
    }


}
