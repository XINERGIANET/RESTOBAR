<?php

namespace App\Http\Controllers;

use App\Models\AccountReceivablePayable;
use App\Models\Bank;
use App\Models\Card;
use App\Models\DigitalWallet;
use App\Models\PaymentGateways;
use App\Models\PaymentMethod;
use App\Services\AccountReceivablePayableService;
use Illuminate\Http\Request;

class AccountsReceivableController extends Controller
{
    public function index(Request $request)
    {
        $branchId = (int) ($request->session()->get('branch_id') ?? 0);
        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status', 'pendiente');
        if (! in_array($status, ['pendiente', 'todos', 'NUEVO', 'PAGANDO', 'PAGADO', 'CANCELADO'], true)) {
            $status = 'pendiente';
        }

        $query = AccountReceivablePayable::query()
            ->with(['person', 'movement'])
            ->where('type', 'RECEIVABLE')
            ->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')
            ->orderByDesc('id');

        if ($branchId > 0) {
            $query->where('branch_id', $branchId);
        }

        if ($status === 'pendiente') {
            $query->where('balance', '>', 0)
                ->whereIn('status', ['NUEVO', 'PAGANDO']);
        } elseif ($status !== 'todos') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->whereHas('person', function ($pq) use ($like) {
                    $pq->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhere('document_number', 'like', $like);
                })->orWhereHas('movement', function ($mq) use ($like) {
                    $mq->where('number', 'like', $like);
                });
            });
        }

        $accounts = $query->paginate((int) $request->input('per_page', 20))->withQueryString();

        return view('accounts_payable.index', [
            'accounts' => $accounts,
            'search' => $search,
            'status' => $status,
        ]);
    }

    public function collect(Request $request, AccountReceivablePayable $account_receivable_payable)
    {
        $branchId = (int) ($request->session()->get('branch_id') ?? 0);
        if ($branchId <= 0 || (int) $account_receivable_payable->branch_id !== $branchId) {
            abort(403);
        }
        if ($account_receivable_payable->type !== 'RECEIVABLE' || (float) $account_receivable_payable->balance <= 0) {
            return redirect()
                ->route('accounts-receivable.index', array_filter(['view_id' => $request->input('view_id')]))
                ->with('error', 'Esta cuenta no tiene saldo pendiente o no es por cobrar.');
        }

        $paymentMethods = PaymentMethod::query()
            ->where('status', true)
            ->restrictedToBranch($branchId)
            ->orderBy('order_num')
            ->get();

        $account = $account_receivable_payable->load([
            'person',
            'movement.orderMovement.details' => static fn ($q) => $q->orderBy('id'),
            'movement.orderMovement.details.product',
            'movement.salesMovement.details.product',
        ]);

        $lineItems = $this->buildReceivableCollectLineItems($account);

        $paymentMethodKinds = $paymentMethods
            ->mapWithKeys(fn (PaymentMethod $pm) => [
                (string) $pm->id => self::resolvePaymentMethodKind($pm->description ?? ''),
            ])
            ->all();

        $cards = Card::query()->where('status', true)->orderBy('order_num')->get(['id', 'description', 'type']);
        $paymentGateways = PaymentGateways::query()->where('status', true)->orderBy('order_num')->get(['id', 'description']);
        $digitalWallets = DigitalWallet::query()->where('status', true)->orderBy('order_num')->get(['id', 'description']);
        $banks = Bank::query()->where('status', true)->orderBy('order_num')->get(['id', 'description']);

        return view('accounts_payable.collect', [
            'account' => $account,
            'lineItems' => $lineItems,
            'paymentMethods' => $paymentMethods,
            'paymentMethodKinds' => $paymentMethodKinds,
            'cards' => $cards,
            'paymentGateways' => $paymentGateways,
            'digitalWallets' => $digitalWallets,
            'banks' => $banks,
        ]);
    }

    public function storeCollection(
        Request $request,
        AccountReceivablePayable $account_receivable_payable,
        AccountReceivablePayableService $accountReceivablePayableService
    ) {
        $branchId = (int) ($request->session()->get('branch_id') ?? 0);
        if ($branchId <= 0 || (int) $account_receivable_payable->branch_id !== $branchId) {
            abort(403);
        }

        $paymentMethod = PaymentMethod::query()
            ->where('id', (int) $request->input('payment_method_id'))
            ->where('status', true)
            ->first();

        $kind = self::resolvePaymentMethodKind($paymentMethod?->description ?? '');

        $rules = [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'payment_gateway_id' => ['nullable', 'integer', 'exists:payment_gateways,id'],
            'card_id' => ['nullable', 'integer', 'exists:cards,id'],
            'digital_wallet_id' => ['nullable', 'integer', 'exists:digital_wallets,id'],
            'bank_id' => ['nullable', 'integer', 'exists:banks,id'],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];

        if ($kind === 'tarjeta') {
            $rules['card_id'] = ['required', 'integer', 'exists:cards,id'];
        }
        if ($kind === 'wallet') {
            $rules['digital_wallet_id'] = ['required', 'integer', 'exists:digital_wallets,id'];
        }
        if ($kind === 'transfer') {
            $rules['bank_id'] = ['required', 'integer', 'exists:banks,id'];
        }

        $validated = $request->validate($rules);

        $restrictedPmIds = PaymentMethod::paymentMethodIdsForBranchOrNull($branchId);
        if ($restrictedPmIds !== null && ! in_array((int) $validated['payment_method_id'], $restrictedPmIds, true)) {
            return back()->withInput()->with('error', 'Método de pago no permitido para esta sucursal.');
        }

        $account_receivable_payable->refresh();
        $pending = round(
            max(0, (float) $account_receivable_payable->total - (float) ($account_receivable_payable->total_paid ?? 0)),
            2
        );
        if ((float) $validated['amount'] > $pending + 0.0001) {
            return back()
                ->withInput()
                ->with('error', 'El importe no puede superar el saldo pendiente (S/ ' . number_format($pending, 2) . ').');
        }

        try {
            $accountReceivablePayableService->recordReceivableCollection(
                $account_receivable_payable,
                (float) $validated['amount'],
                (int) $validated['payment_method_id'],
                isset($validated['payment_gateway_id']) ? (int) $validated['payment_gateway_id'] : null,
                isset($validated['card_id']) ? (int) $validated['card_id'] : null,
                isset($validated['digital_wallet_id']) ? (int) $validated['digital_wallet_id'] : null,
                isset($validated['bank_id']) ? (int) $validated['bank_id'] : null,
                $branchId,
                $request->user(),
                isset($validated['notes']) ? (string) $validated['notes'] : null,
                isset($validated['payment_reference']) ? (string) $validated['payment_reference'] : null
            );
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('accounts-receivable.index', array_filter(['view_id' => $request->input('view_id')]))
            ->with('status', 'Cobro registrado correctamente.');
    }

    /**
     * @return list<array{name: string, qty: float, unit: float, line_total: float, comment: ?string}>
     */
    private function buildReceivableCollectLineItems(AccountReceivablePayable $account): array
    {
        $movement = $account->movement;
        if (! $movement) {
            return [];
        }

        $orderMovement = $movement->orderMovement;
        if ($orderMovement && $orderMovement->details->isNotEmpty()) {
            return $orderMovement->details->map(function ($detail) {
                $qty = (float) $detail->quantity;
                $courtesyQty = max(0, min((float) ($detail->courtesy_quantity ?? 0), $qty));
                $paidQty = max(0, $qty - $courtesyQty);
                $amount = (float) $detail->amount;
                $unit = $paidQty > 0 ? ($amount / $paidQty) : 0.0;
                $snapshot = is_array($detail->product_snapshot ?? null) ? $detail->product_snapshot : [];
                $name = $detail->description
                    ?? $detail->product?->description
                    ?? ($snapshot['description'] ?? null)
                    ?? ('Producto #' . (string) $detail->product_id);

                return [
                    'name' => (string) $name,
                    'qty' => $qty,
                    'unit' => round($unit, 4),
                    'line_total' => round($amount, 2),
                    'comment' => $detail->comment ? (string) $detail->comment : null,
                ];
            })->values()->all();
        }

        $salesMovement = $movement->salesMovement;
        if ($salesMovement && $salesMovement->details->isNotEmpty()) {
            return $salesMovement->details->map(function ($detail) {
                $qty = (float) $detail->quantity;
                $orig = (float) $detail->original_amount;
                $unit = $qty > 0 ? ($orig / $qty) : 0.0;
                $name = $detail->product?->description
                    ?? $detail->description
                    ?? ('Producto #' . (string) $detail->product_id);

                return [
                    'name' => (string) $name,
                    'qty' => $qty,
                    'unit' => round($unit, 4),
                    'line_total' => round($orig, 2),
                    'comment' => $detail->comment ? (string) $detail->comment : null,
                ];
            })->values()->all();
        }

        return [];
    }

    /**
     * Alineado con la heurística de cobro en ventas (tarjeta / billetera / transferencia).
     */
    private static function resolvePaymentMethodKind(string $description): string
    {
        $d = mb_strtolower(trim($description));

        if ($d === '') {
            return 'otro';
        }

        if (str_contains($d, 'tarjeta') || str_contains($d, 'card') || str_contains($d, 'débito') || str_contains($d, 'debito') || str_contains($d, 'crédito') || str_contains($d, 'credito')) {
            return 'tarjeta';
        }

        if (str_contains($d, 'billetera') || str_contains($d, 'wallet') || str_contains($d, 'yape') || str_contains($d, 'plin')) {
            return 'wallet';
        }

        if (str_contains($d, 'transfer') || str_contains($d, 'depósito') || str_contains($d, 'deposito')) {
            return 'transfer';
        }

        if (str_contains($d, 'efectivo') || str_contains($d, 'cash')) {
            return 'efectivo';
        }

        return 'otro';
    }
}
