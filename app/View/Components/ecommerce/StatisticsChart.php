<?php

namespace App\View\Components\ecommerce;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class StatisticsChart extends Component
{
    public $sales;
    public $profit;
    public $startDate;
    public $endDate;

    /**
     * Create a new component instance.
     */
    public function __construct($sales = [], $profit = [], $startDate = null, $endDate = null)
    {
        $this->sales = $sales;
        $this->profit = $profit;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.ecommerce.statistics-chart');
    }
}
