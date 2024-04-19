<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Symfony\Contracts\Service\Attribute\Required;

class Dataset extends Model
{

    // a dataset might be a subset of another dataset (e.g. data from a repeat group in a form; household members in a household, etc);
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    // a dataset might have many children (e.g. if a form has 3 repeat group sections, the 'main survey' dataset would have 3 child datasets);
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function odkDatasets(): HasMany
    {
        return $this->hasMany(OdkDataset::class);
    }

    public function variables(): HasMany
    {
        return $this->hasMany(DatasetVariable::class);
    }

    public function entities(): HasMany
    {
        return $this->hasMany(Entity::class);
    }

    // A dataset may be linked to a specific model in the main application
    public function model(): MorphTo
    {
        return $this->morphTo();
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
