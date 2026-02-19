<?php

namespace Stats4sd\FilamentOdkLink\Models\OdkLink;

use App\Models\Project;
use App\Models\ProjectActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Traits\HasXlsforms;

class Dataset extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $guarded = [];

    /** @return BelongsTo<HasXlsforms, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(config('filament-odk-link.models.form_owner'), 'owner_id');
    }

    // Datasets might relate to one another with 'parent-child' style relationships.
    // E.g., a farm-group might have many farms, and a farm might belong to a farm-group. A farm might also belong to a location.
    /** @return BelongsToMany<self, $this> */
    public function parentDatasets(): BelongsToMany
    {
        return $this->belongsToMany(
            related: self::class,
            table: 'dataset_parents',
            foreignPivotKey: 'child_id',
            relatedPivotKey: 'parent_id',
        )->using(ParentDatasetPivot::class);

    }

    /** @return BelongsToMany<self, $this> */
    public function childDatasets(): BelongsToMany
    {
        return $this->belongsToMany(
            related: self::class,
            table: 'dataset_parents',
            relatedPivotKey: 'parent_id',
            foreignPivotKey: 'child_id',
        )->using(ParentDatasetPivot::class);

    }

    // a dataset might be a subset of another dataset (e.g. data from a repeat group in a form; household members in a household, etc);

    /** @return BelongsTo<self, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    // a dataset might have many children (e.g. if a form has 3 repeat group sections, the 'main survey' dataset would have 3 child datasets);

    /** @return HasMany<self, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return BelongsTo<ChoiceList, $this> */
    public function choiceList(): BelongsTo
    {
        return $this->belongsTo(ChoiceList::class);
    }

    /** @return HasMany<OdkDataset, $this> */
    public function odkDatasets(): HasMany
    {
        return $this->hasMany(OdkDataset::class);
    }

    /** @return HasMany<DatasetVariable, $this> */
    public function variables(): HasMany
    {
        return $this->hasMany(DatasetVariable::class);
    }

    /** @return HasMany<Entity, $this> */
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

    /** @return HasMany<XlsformTemplateSection, $this> */
    public function xlsformTemplateSections(): HasMany
    {
        return $this->hasMany(XlsformTemplateSection::class);
    }

    /** @return HasMany<XlsformTemplate, $this> */
    public function dataSubjectXlsformTemplateSections(): HasMany
    {
        return $this->hasMany(XlsformTemplateSection::class, 'data_subject_dataset_id');
    }

    /** @return BelongsToMany<XlsformTemplate, $this> */
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
    /** @return HasMany<RequiredMedia, $this> */
    public function requiredMedia(): HasMany
    {
        return $this->hasMany(RequiredMedia::class);
    }

    // xlsform templates that use this dataset as a source

    /** @return BelongsToMany<XlsformTemplate, $this> */
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
