<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Stats4sd\FilamentOdkLink\Contracts\CurrentOwnerResolver;
use Stats4sd\FilamentOdkLink\Contracts\FormOwner;
use Stats4sd\FilamentOdkLink\Models\OdkLink\ChoiceListEntry;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Traits\HasXlsforms;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Xlsform;
use Stats4sd\FilamentOdkLink\Services\HelperService;
use Stats4sd\FilamentOdkLink\Tests\Models\Team;

class SwitchingOwnerResolver implements CurrentOwnerResolver
{
    public (Model & FormOwner) | null $owner = null;

    public function current(): (Model & FormOwner) | null
    {
        return $this->owner;
    }
}

class OtherHostOwner extends Model implements FormOwner
{
    use HasXlsforms;
}

it('keeps forms submissions and shared choices scoped across sequential owner contexts', function () {
    $resolver = new SwitchingOwnerResolver;
    app()->instance(CurrentOwnerResolver::class, $resolver);
    $first = Team::factory()->create();
    $second = Team::factory()->create();
    $template = makeXlsformTemplate();
    $version = addModuleVersion($template, 'Main');
    $choices = addChoiceList($version, 'villages');
    addChoiceListEntry($choices, 'shared');
    addChoiceListEntry($choices, 'first', ['owner_id' => $first->id]);
    addChoiceListEntry($choices, 'second', ['owner_id' => $second->id]);
    foreach ([$first, $second] as $owner) {
        $formId = DB::table('xlsforms')->insertGetId(['xlsform_template_id' => $template->id, 'owner_id' => $owner->id]);
        $versionId = DB::table('xlsform_versions')->insertGetId(['xlsform_id' => $formId, 'version' => 'v1', 'odk_version' => 'v1']);
        DB::table('submissions')->insert(['xlsform_version_id' => $versionId, 'odk_id' => "owner-{$owner->id}", 'submitted_at' => now(), 'content' => '{}']);
    }
    foreach ([[$first, 'first'], [$second, 'second'], [$first, 'first']] as [$owner, $choice]) {
        $resolver->owner = $owner;
        expect(Xlsform::pluck('owner_id')->all())->toBe([$owner->id])->and(Submission::pluck('odk_id')->all())->toBe(["owner-{$owner->id}"])->and(ChoiceListEntry::pluck('name')->all())->toBe(['shared', $choice]);
    }
    $resolver->owner = null;
    expect(Xlsform::count())->toBe(2)->and(Submission::count())->toBe(2)->and(ChoiceListEntry::count())->toBe(3);
});

it('rejects a custom resolver returning an unrelated owner class with the same key', function () {
    $resolver = new SwitchingOwnerResolver;
    $resolver->owner = new OtherHostOwner;
    $resolver->owner->id = Team::factory()->create()->id;
    app()->instance(CurrentOwnerResolver::class, $resolver);
    expect(fn () => HelperService::getCurrentOwner())->toThrow(InvalidArgumentException::class, 'models.form_owner');
    expect(fn () => Xlsform::count())->toThrow(InvalidArgumentException::class, 'models.form_owner');
});

it('makes locales editable only by a selected creator and preserves no-owner status', function () {
    $resolver = new SwitchingOwnerResolver;
    app()->instance(CurrentOwnerResolver::class, $resolver);
    $locale = makeLocale();
    $first = Team::factory()->create();
    $second = Team::factory()->create();
    expect($locale->is_editable)->toBeFalse()->and($locale->status)->toBe('Ready for use');
    $locale->setRelation('creator', $first);
    expect($locale->is_editable)->toBeFalse();
    $resolver->owner = $first;
    expect($locale->is_editable)->toBeTrue()->and($locale->status)->toBe('Ready for use');
    $resolver->owner = $second;
    expect($locale->is_editable)->toBeFalse();
});
