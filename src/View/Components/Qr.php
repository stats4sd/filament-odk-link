<?php

namespace src\View\Components;

use Illuminate\Database\Eloquent\Model;
use Illuminate\View\Component;

class Qr extends Component
{
    // $entry should be a model that uses the "HasXlsForms" trait
    public function __construct(public Model $entry)
    {

    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.Qr');
    }
}
