<div>
    {{ $getRecord()->title }}

    <p>Fixed Media Count</p>
    {{ $getRecord()->requiredFixedMedia()->count()  }};
</div>
