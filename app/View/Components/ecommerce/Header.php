<?php

namespace App\View\Components\ecommerce;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Header extends Component
{
    public $userName;
    public $companyName = '';

    /**
     * Create a new component instance.
     */
    public function __construct($userName = 'Administrador')
    {
        $this->userName = $userName;
        $this->companyName = config('app.name', 'Sistema');
        
        // Fetch dynamic company/branch name
        $branchId = session('branch_id');
        if ($branchId) {
            $branch = \App\Models\Branch::find($branchId);
            $this->companyName = $branch ? $branch->legal_name : config('app.name');
        } else {
            $company = \App\Models\Company::first();
            $this->companyName = $company ? $company->legal_name : config('app.name');
        }
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.ecommerce.header');
    }
}
