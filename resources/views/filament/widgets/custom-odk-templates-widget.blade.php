<x-filament-widgets::widget>
    <x-filament::section>
        <h3 class="text-xl font-bold mb-4">Custom Templates - {{ \App\Services\HelperService::getSelectedTeam()->name }}</h3>
        <p class="mb-2">Custom ODK forms are only available to your team. Once a custom ODK form has been marked as available for use, it can be deployed on the
            <a href="{{ url(\App\Services\HelperService::getSelectedTeam()->slug . '/xlsforms/xlsform-templates') }} class="text-primary-600">Available ODK Templates</a> tab.</p>
    </x-filament::section>
</x-filament-widgets::widget>
