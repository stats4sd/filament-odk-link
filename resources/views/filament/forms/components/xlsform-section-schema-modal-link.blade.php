<div>
    There are {{ $getRecord()->schema->count() }} variables in the {{ $getRecord()->structure_item === 'root' ? 'main' : $getRecord()->structure_item }} section.
    <br/><br/>
    {{ $getAction('viewSchema') }}

</div>
