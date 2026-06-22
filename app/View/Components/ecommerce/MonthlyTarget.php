<?php

namespace App\View\Components\ecommerce;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class MonthlyTarget extends Component
{
    public $incomeTrend;
    public $expenseTrend;

    public function __construct($incomeTrend = [], $expenseTrend = [])
    {
        $this->incomeTrend = $incomeTrend;
        $this->expenseTrend = $expenseTrend;
    }

    public function render(): View|Closure|string
    {
        return view('components.ecommerce.monthly-target');
    }
}




