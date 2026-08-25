<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchParameter;
use App\Models\ParameterCategories;
use App\Models\Parameters;
use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class DefaultPaymentMethodParameterSeeder extends Seeder
{
    public function run(): void
    {
        $category = ParameterCategories::query()
            ->where(function ($query) {
                $query->whereRaw('LOWER(description) LIKE ?', ['%sistema%'])
                    ->orWhereRaw('LOWER(description) LIKE ?', ['%config%']);
            })
            ->orderBy('id')
            ->first();

        if (! $category) {
            $category = ParameterCategories::query()->first();
        }

        if (! $category) {
            $category = ParameterCategories::create([
                'description' => 'Configuracion de Sistema',
            ]);
        }

        $defaultMethodId = PaymentMethod::query()
            ->where('status', true)
            ->whereRaw('LOWER(description) LIKE ?', ['%efectivo%'])
            ->orderBy('order_num')
            ->value('id');

        if (! $defaultMethodId) {
            $defaultMethodId = PaymentMethod::query()
                ->where('status', true)
                ->orderBy('order_num')
                ->value('id');
        }

        $parameter = Parameters::query()->updateOrCreate(
            ['description' => 'Medio de pago por defecto'],
            [
                'value' => $defaultMethodId ? (string) $defaultMethodId : '',
                'parameter_category_id' => $category->id,
                'status' => 1,
            ]
        );

        $now = now();
        Branch::query()->pluck('id')->each(function ($branchId) use ($parameter, $defaultMethodId, $now) {
            BranchParameter::query()->updateOrCreate(
                [
                    'branch_id' => (int) $branchId,
                    'parameter_id' => (int) $parameter->id,
                ],
                [
                    'value' => $defaultMethodId ? (string) $defaultMethodId : '',
                    'updated_at' => $now,
                ]
            );
        });
    }
}
