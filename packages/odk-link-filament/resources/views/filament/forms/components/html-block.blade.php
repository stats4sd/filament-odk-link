<x-dynamic-component
    :component="$getFieldWrapperView()"
    :id="$getId()"
    :state-path="$getStatePath()"
>
    <div
        {{
            $attributes
                ->merge($getExtraAttributes(), escape: false)
                ->class(['fi-fo-placeholder sm:text-sm'])
        }}
    >
        {{ $getContent() }}
    </div>
</x-dynamic-component>
