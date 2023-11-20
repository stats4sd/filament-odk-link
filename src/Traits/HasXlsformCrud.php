<?php

namespace Stats4sd\FilamentOdkLink\Traits;

use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Application;

trait HasXlsformCrud
{
    public function setupXlsformColumns(): void
    {
        CRUD::column('odkProject')->limit(1000)->wrapper([
            'href' => function ($crud, $column, $entry) {
                return config('odk-link.odk.url') . '/#/projects/' . $entry->odkProject->id;
            },
        ]);
        CRUD::column('xlsforms')->type('relationship_count')->suffix('');

    }

    public function setupXlsformCreateFields(): void
    {
        CRUD::field('xlsforms')
            ->type('relationship')
            ->subfields([
                [
                    'name' => 'xlsformTemplate',
                    'type' => 'relationship',
                ],
                [
                    'name' => 'title',
                    'label' => 'If you want this form to have a custom title, add it here.',
                    'hint' => 'Leave empty to inherit the default title from the chosen template',
                    'type' => 'text',
                    //'type' => 'xlsform-title',
                    //'view_namespace' => 'stats4sd.odk-link::fields',
                ],
            ]);
    }

    /**
     * Overwrite the default "show" method for CRUD panels to show a custom page that includes all the XLSform details of the selected owner.
     **/
    public function show($id): View | Application | Factory | \Illuminate\Contracts\Foundation\Application
    {
        $this->crud->hasAccessOrFail('show');

        // get entry ID from Request (makes sure its the last ID for nested resources)
        $id = $this->crud->getCurrentEntryId() ?? $id;

        // get the info for that entry (include softDeleted items if the trait is used)
        if ($this->crud->get('show.softDeletes') && in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses($this->crud->model), true)) {
            $this->data['entry'] = $this->crud->getModel()->withTrashed()->findOrFail($id);
        } else {
            $this->data['entry'] = $this->crud->getEntryWithLocale($id);
        }

        $this->data['crud'] = $this->crud;
        $this->data['title'] = $this->crud->getTitle() ?? trans('backpack::crud.preview') . ' ' . $this->crud->entity_name;

        // load the view from /resources/views/vendor/backpack/crud/ if it exists, otherwise load the one in the package
        return view($this->crud->getShowView(), $this->data);
    }
}
