<?php

namespace App\View\Components\ecommerce;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class CustomerDemographic extends Component
{
    /**
     * Create a new component instance.
     */
    public $topProducts;

    public function __construct($topProducts = [])
    {
        $this->topProducts = $topProducts;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.ecommerce.customer-demographic');
    }
}
