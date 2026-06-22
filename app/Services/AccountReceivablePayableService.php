<?php

namespace App\Services;

use App\Models\AccountReceivablePayable;
use App\Models\AccountReceivablePayableDetail;
use App\Models\Bank;
use App\Models\Card;
use App\Models\CashMovementDetail;
use App\Models\CashMovements;
use App\Models\CashRegister;
use App\Models\CashShiftRelation;
use App\Models\DigitalWallet;
use App\Models\DocumentType;
use App\Models\Movement;
use App\Models\MovementType;
use App\Models\OrderMovement;
use App\Models\PaymentConcept;
use App\Models\PaymentGateways;
use App\Models\PaymentMethod;
use App\Models\Person;
use App\Models\SalesMovement;
use App\Models\Shift;
use App\Models\User;
use App\Support\InsensitiveSearch;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class AccountReceivablePayableService
{
    public const TYPE_RECEIVABLE = 'RECEIVABLE';

    public const TYPE_PAYABLE = 'PAYABLE';

    /**
     * Registra la cuenta por cobrar, vincula el pedido de restaurante y el detalle DEUDA en caja (saldo pendiente).
     * Los abonos inmediatos se reflejan en account_receivable_payable_details.
     */
    public function syncDebtAccount(
        int $branchId,
        int $personId,
        int $baseMovementId,
        float $orderTotal,
        float $totalPaid,
        CarbonInterface $dueAt,
        int $cashMovementId,
        Movement $cashEntryMovement,
        OrderMovement $orderMovement,
        int $creditDays,
        ?string $notes = null
    ): AccountReceivablePayable {
        $initialTotalPaid = round((float) $totalPaid, 2);
        $balance = round((float) $orderTotal - $initialTotalPaid, 2);
        $status = $balance <= 0 ? 'PAGADO' : ($initialTotalPaid > 0 ? 'PAGANDO' : 'NUEVO');
        $paidAt = $balance <= 0 ? now() : null;

        $arp = AccountReceivablePayable::create([
            'type' => self::TYPE_RECEIVABLE,
            'person_id' => $personId,
            'movement_id' => $baseMovementId,
            'total' => $orderTotal,
            'balance' => max(0, $balance),
            'total_paid' => $initialTotalPaid,
            'due_at' => $dueAt,
            'status' => $status,
            'paid_at' => $paidAt,
            'branch_id' => $branchId,
        ]);

        $orderMovement->account_receivable_payable_id = $arp->id;
        $orderMovement->credit_days = $creditDays > 0 ? $creditDays : null;
        $orderMovement->debt_due_at = $dueAt;
        $orderMovement->save();

        if ($balance > 0) {
            CashMovementDetail::create([
                'cash_movement_id' => $cashMovementId,
                'type' => 'DEUDA',
                'due_at' => $dueAt,
                'paid_at' => null,
                'payment_method_id' => null,
                'payment_method' => 'DEUDA PENDIENTE',
                'number' => $cashEntryMovement->number,
                'card_id' => null,
                'card' => null,
                'bank_id' => null,
                'bank' => null,
                'digital_wallet_id' => null,
                'digital_wallet' => null,
                'payment_gateway_id' => null,
                'payment_gateway' => null,
                'amount' => $balance,
                'comment' => ($notes !== null && $notes !== '') ? $notes : 'Saldo pendiente por venta a crédito',
                'status' => 'A',
                'branch_id' => $branchId,
            ]);
        }

        if ($totalPaid > 0) {
            AccountReceivablePayableDetail::create([
                'account_receivable_payable_id' => $arp->id,
                'movement_id' => $cashEntryMovement->id,
                'amount' => $totalPaid,
                'branch_id' => $branchId,
            ]);
        }

        return $arp;
    }

    /**
     * Igual que {@see syncDebtAccount} pero para venta directa (POS): vincula {@see SalesMovement}.
     */
    public function syncDebtAccountForDirectSale(
        int $branchId,
        int $personId,
        int $baseMovementId,
        float $saleTotal,
        float $totalPaid,
        CarbonInterface $dueAt,
        int $cashMovementId,
        Movement $cashEntryMovement,
        SalesMovement $salesMovement,
        int $creditDays,
        ?string $notes = null
    ): AccountReceivablePayable {
        $initialTotalPaid = round((float) $totalPaid, 2);
        $balance = round((float) $saleTotal - $initialTotalPaid, 2);
        $status = $balance <= 0 ? 'PAGADO' : ($initialTotalPaid > 0 ? 'PAGANDO' : 'NUEVO');
        $paidAt = $balance <= 0 ? now() : null;

        $arp = AccountReceivablePayable::create([
            'type' => self::TYPE_RECEIVABLE,
            'person_id' => $personId,
            'movement_id' => $baseMovementId,
            'total' => $saleTotal,
            'balance' => max(0, $balance),
            'total_paid' => $initialTotalPaid,
            'due_at' => $dueAt,
            'status' => $status,
            'paid_at' => $paidAt,
            'branch_id' => $branchId,
        ]);

        $salesMovement->account_receivable_payable_id = $arp->id;
        $salesMovement->credit_days = $creditDays > 0 ? $creditDays : null;
        $salesMovement->debt_due_at = $dueAt;
        $salesMovement->save();

        if ($balance > 0) {
            CashMovementDetail::create([
                'cash_movement_id' => $cashMovementId,
                'type' => 'DEUDA',
                'due_at' => $dueAt,
                'paid_at' => null,
                'payment_method_id' => null,
                'payment_method' => 'DEUDA PENDIENTE',
                'number' => $cashEntryMovement->number,
                'card_id' => null,
                'card' => null,
                'bank_id' => null,
                'bank' => null,
                'digital_wallet_id' => null,
                'digital_wallet' => null,
                'payment_gateway_id' => null,
                'payment_gateway' => null,
                'amount' => $balance,
                'comment' => ($notes !== null && $notes !== '') ? $notes : 'Saldo pendiente por venta a crédito (POS)',
                'status' => 'A',
                'branch_id' => $branchId,
            ]);
        }

        if ($totalPaid > 0) {
            AccountReceivablePayableDetail::create([
                'account_receivable_payable_id' => $arp->id,
                'movement_id' => $cashEntryMovement->id,
                'amount' => $totalPaid,
                'branch_id' => $branchId,
            ]);
        }

        return $arp;
    }

    /**
     * Abono / cobro total sobre una cuenta (RECEIVABLE = ingreso en caja). Todo en una transacción con rollback automático.
     */
    public function recordReceivableCollection(
        AccountReceivablePayable $arp,
        float $amount,
        int $paymentMethodId,
        ?int $paymentGatewayId,
        ?int $cardId,
        ?int $digitalWalletId,
        ?int $bankId,
        int $branchId,
        ?User $user,
        ?string $notes = null,
        ?string $paymentReference = null
    ): AccountReceivablePayable {
        $cashRegisterId = effective_cash_register_id($branchId);
        if (! $cashRegisterId) {
            throw new \InvalidArgumentException('Seleccione una caja de trabajo antes de registrar el cobro.');
        }

        return DB::transaction(function () use (
            $arp,
            $amount,
            $paymentMethodId,
            $paymentGatewayId,
            $cardId,
            $digitalWalletId,
            $bankId,
            $branchId,
            $user,
            $notes,
            $paymentReference,
            $cashRegisterId
        ) {
            /** @var AccountReceivablePayable $arp */
            $arp = AccountReceivablePayable::whereKey($arp->id)->lockForUpdate()->firstOrFail();
            $arp->loadMissing('movement');

            if ($arp->type === self::TYPE_PAYABLE) {
                throw new \InvalidArgumentException(
                    'Las cuentas por pagar (PAYABLE) requieren un flujo de egreso distinto; use el módulo de compras o caja chica según corresponda.'
                );
            }

            if ($arp->type !== self::TYPE_RECEIVABLE) {
                throw new \InvalidArgumentException('Tipo de cuenta no soportado para este cobro.');
            }

            $total = round((float) $arp->total, 2);
            $totalPaidBefore = round((float) ($arp->total_paid ?? 0), 2);
            $pending = round($total - $totalPaidBefore, 2);

            if ($pending <= 0) {
                throw new \InvalidArgumentException('La cuenta no tiene saldo pendiente (total − abonado = 0).');
            }

            $payAmount = round($amount, 2);
            if ($payAmount <= 0) {
                throw new \InvalidArgumentException('El importe del abono debe ser mayor a cero.');
            }

            if ($payAmount > $pending + 0.02) {
                throw new \InvalidArgumentException(
                    'El abono (S/ ' . number_format($payAmount, 2) . ') no puede superar el saldo pendiente (S/ ' . number_format($pending, 2) . ').'
                );
            }

            $this->assertCashRegisterIsOpen((int) $cashRegisterId, $branchId);

            $paymentConcept = $this->resolveCollectionPaymentConceptForAccount($arp);
            $cashMovementTypeId = $this->resolveCashMovementTypeId();
            $cashDocumentTypeId = $this->resolveCashIncomeDocumentTypeId($cashMovementTypeId);
            $shift = $this->resolveActiveShiftForCashRegister($branchId, (int) $cashRegisterId);
            $cashRegister = CashRegister::find($cashRegisterId);

            $paymentMethod = PaymentMethod::findOrFail($paymentMethodId);
            $paymentGateway = $paymentGatewayId ? PaymentGateways::find($paymentGatewayId) : null;
            $card = $cardId ? Card::find($cardId) : null;
            $digitalWallet = $digitalWalletId ? DigitalWallet::find($digitalWalletId) : null;
            $bank = $bankId ? Bank::find($bankId) : null;

            $person = Person::find($arp->person_id);
            $personName = $person
                ? trim(($person->first_name ?? '') . ' ' . ($person->last_name ?? ''))
                : 'Cliente';

            $parentMovementId = $arp->movement_id;

            $cashEntryMovement = Movement::create([
                'number' => $this->generateCashMovementNumber($branchId, (int) $cashRegisterId, (int) $paymentConcept->id),
                'moved_at' => now(),
                'user_id' => $user?->id,
                'user_name' => $user?->name ?? 'Sistema',
                'person_id' => $arp->person_id,
                'person_name' => $personName !== '' ? $personName : 'Cliente',
                'responsible_id' => $user?->id,
                'responsible_name' => $user?->person
                    ? trim(($user->person->first_name ?? '') . ' ' . ($user->person->last_name ?? ''))
                    : ($user?->name ?? 'Sistema'),
                'comment' => 'Abono cartera #' . $arp->id . ' — Doc. ' . ($arp->movement?->number ?? ''),
                'status' => '1',
                'movement_type_id' => $cashMovementTypeId,
                'document_type_id' => $cashDocumentTypeId,
                'branch_id' => $branchId,
                'parent_movement_id' => $parentMovementId,
            ]);

            $cashMovement = CashMovements::create([
                'payment_concept_id' => $paymentConcept->id,
                'currency' => 'PEN',
                'exchange_rate' => 1.000,
                'total' => $payAmount,
                'cash_register_id' => $cashRegisterId,
                'cash_register' => $cashRegister?->number ?? 'Caja Principal',
                'shift_id' => $shift->id,
                'shift_snapshot' => [
                    'name' => $shift->name,
                    'start_time' => $shift->start_time,
                    'end_time' => $shift->end_time,
                ],
                'movement_id' => $cashEntryMovement->id,
                'branch_id' => $branchId,
            ]);

            $detailCommentParts = [];
            if ($notes !== null && $notes !== '') {
                $detailCommentParts[] = $notes;
            }
            if ($paymentReference !== null && trim($paymentReference) !== '') {
                $detailCommentParts[] = 'Ref: ' . trim($paymentReference);
            }
            if ($detailCommentParts === []) {
                $detailCommentParts[] = 'Abono cuenta por cobrar #' . $arp->id;
            }

            CashMovementDetail::create([
                'cash_movement_id' => $cashMovement->id,
                'type' => 'PAGADO',
                'paid_at' => now(),
                'payment_method_id' => $paymentMethod->id,
                'payment_method' => $paymentMethod->description ?? '',
                'number' => $cashEntryMovement->number,
                'card_id' => $card?->id,
                'card' => $card?->description,
                'bank_id' => $bank?->id,
                'bank' => $bank?->description ?? '',
                'digital_wallet_id' => $digitalWallet?->id,
                'digital_wallet' => $digitalWallet?->description,
                'payment_gateway_id' => $paymentGateway?->id,
                'payment_gateway' => $paymentGateway?->description,
                'amount' => $payAmount,
                'comment' => implode(' — ', $detailCommentParts),
                'status' => 'A',
                'branch_id' => $branchId,
            ]);

            AccountReceivablePayableDetail::create([
                'account_receivable_payable_id' => $arp->id,
                'movement_id' => $cashEntryMovement->id,
                'amount' => $payAmount,
                'branch_id' => $branchId,
            ]);

            $totalPaidAfter = round($totalPaidBefore + $payAmount, 2);
            $newBalance = round($total - $totalPaidAfter, 2);

            $arp->total_paid = $totalPaidAfter;
            $arp->balance = max(0, $newBalance);

            if ($newBalance <= 0.02) {
                $arp->status = 'PAGADO';
                $arp->paid_at = now();
            } else {
                $arp->status = 'PAGANDO';
            }

            $arp->save();

            $this->reduceLinkedDeudaDetailAmount($arp, $payAmount, $branchId);

            return $arp->fresh();
        });
    }

    /**
     * Turno operativo vinculado a la apertura de caja activa (CashShiftRelation → movimiento de apertura → shift).
     */
    private function resolveActiveShiftForCashRegister(int $branchId, int $cashRegisterId): Shift
    {
        $relation = CashShiftRelation::query()
            ->where('branch_id', $branchId)
            ->where('status', '1')
            ->whereNull('ended_at')
            ->whereNull('cash_movement_end_id')
            ->whereHas('cashMovementStart', function ($query) use ($cashRegisterId) {
                $query->where('cash_register_id', $cashRegisterId);
            })
            ->with(['cashMovementStart.shift'])
            ->latest('id')
            ->first();

        $shift = $relation?->cashMovementStart?->shift;
        if (! $shift) {
            throw new \InvalidArgumentException(
                'No se pudo resolver el turno activo de la caja. Verifique la apertura de turno y la caja seleccionada.'
            );
        }

        return $shift;
    }

    /**
     * Concepto de pago según tipo de cuenta (cobrar = ingreso, pagar = egreso).
     */
    private function resolveCollectionPaymentConceptForAccount(AccountReceivablePayable $arp): PaymentConcept
    {
        if ($arp->type === self::TYPE_RECEIVABLE) {
            return $this->resolveReceivableCollectionIncomeConcept();
        }

        return $this->resolvePayableCollectionExpenseConcept();
    }

    private function resolveReceivableCollectionIncomeConcept(): PaymentConcept
    {
        $paymentConcept = PaymentConcept::query()
            ->where('type', 'I')
            ->where(function ($query) {
                InsensitiveSearch::whereInsensitiveLikePattern($query, 'description', '%cobranza%');
                InsensitiveSearch::whereInsensitiveLikePattern($query, 'description', '%cartera%', 'or');
                InsensitiveSearch::whereInsensitiveLikePattern($query, 'description', '%cobrar%', 'or');
                InsensitiveSearch::whereInsensitiveLikePattern($query, 'description', '%pago%cliente%', 'or');
                InsensitiveSearch::whereInsensitiveLikePattern($query, 'description', '%venta%', 'or');
            })
            ->orderBy('id')
            ->first();

        if (! $paymentConcept) {
            $paymentConcept = PaymentConcept::query()
                ->where('type', 'I')
                ->orderBy('id')
                ->first();
        }

        if (! $paymentConcept) {
            throw new \RuntimeException('No se encontró concepto de ingreso para registrar el cobro de cartera.');
        }

        return $paymentConcept;
    }

    private function resolvePayableCollectionExpenseConcept(): PaymentConcept
    {
        $paymentConcept = PaymentConcept::query()
            ->where('type', 'E')
            ->where(function ($query) {
                InsensitiveSearch::whereInsensitiveLikePattern($query, 'description', '%proveedor%');
                InsensitiveSearch::whereInsensitiveLikePattern($query, 'description', '%pago%proveedor%', 'or');
                InsensitiveSearch::whereInsensitiveLikePattern($query, 'description', '%cuenta%por%pagar%', 'or');
            })
            ->orderBy('id')
            ->first();

        if (! $paymentConcept) {
            $paymentConcept = PaymentConcept::query()
                ->where('type', 'E')
                ->orderBy('id')
                ->first();
        }

        if (! $paymentConcept) {
            throw new \RuntimeException('No se encontró concepto de egreso para registrar el pago de cartera.');
        }

        return $paymentConcept;
    }

    /**
     * Tipo de documento de egreso para movimientos de caja (PAYABLE / futuro).
     */
    private function resolveCashExpenseDocumentTypeId(int $cashMovementTypeId): int
    {
        $documentTypeId = DocumentType::query()
            ->where('movement_type_id', $cashMovementTypeId)
            ->tap(fn ($q) => InsensitiveSearch::whereInsensitiveLikePattern($q, 'name', '%egreso%'))
            ->orderBy('id')
            ->value('id');

        if (! $documentTypeId) {
            $documentTypeId = DocumentType::query()
                ->where('movement_type_id', $cashMovementTypeId)
                ->orderBy('id')
                ->value('id');
        }

        if (! $documentTypeId) {
            throw new \RuntimeException('No se encontró tipo de documento de egreso para movimiento de caja.');
        }

        return (int) $documentTypeId;
    }

    private function reduceLinkedDeudaDetailAmount(AccountReceivablePayable $arp, float $payAmount, int $branchId): void
    {
        if (! $arp->movement_id) {
            return;
        }

        $childMovementIds = Movement::query()
            ->where('parent_movement_id', $arp->movement_id)
            ->pluck('id');

        if ($childMovementIds->isEmpty()) {
            return;
        }

        $cashSheetIds = CashMovements::query()
            ->whereIn('movement_id', $childMovementIds)
            ->pluck('id');

        if ($cashSheetIds->isEmpty()) {
            return;
        }

        $deudaDetail = CashMovementDetail::query()
            ->whereIn('cash_movement_id', $cashSheetIds)
            ->where('type', 'DEUDA')
            ->where('status', 'A')
            ->orderBy('id')
            ->first();

        if (! $deudaDetail || (float) $deudaDetail->amount <= 0) {
            return;
        }

        $newDeuda = round((float) $deudaDetail->amount - $payAmount, 2);
        if ($newDeuda <= 0.02) {
            $deudaDetail->update([
                'amount' => 0,
                'paid_at' => now(),
            ]);
        } else {
            $deudaDetail->update(['amount' => $newDeuda]);
        }
    }

    private function assertCashRegisterIsOpen(int $cashRegisterId, int $branchId): void
    {
        $cashRegister = CashRegister::query()
            ->where('id', $cashRegisterId)
            ->where('status', true)
            ->first();

        if (! $cashRegister) {
            throw new \InvalidArgumentException('La caja seleccionada no está habilitada.');
        }

        $activeShift = CashShiftRelation::query()
            ->where('branch_id', $branchId)
            ->where('status', '1')
            ->whereNull('ended_at')
            ->whereNull('cash_movement_end_id')
            ->whereHas('cashMovementStart', function ($query) use ($cashRegisterId) {
                $query->where('cash_register_id', $cashRegisterId);
            })
            ->latest('id')
            ->first();

        if (! $activeShift) {
            throw new \InvalidArgumentException(
                'La caja "' . $cashRegister->number . '" no tiene un turno abierto. Realice una apertura de caja primero.'
            );
        }
    }

    private function resolveCashMovementTypeId(): int
    {
        $movementTypeId = MovementType::query()
            ->where(function ($query) {
                InsensitiveSearch::whereInsensitiveLikePattern($query, 'description', '%caja%');
                InsensitiveSearch::whereInsensitiveLikePattern($query, 'description', '%cash%', 'or');
            })
            ->orderBy('id')
            ->value('id');

        if (! $movementTypeId) {
            $movementTypeId = MovementType::find(4)?->id;
        }
        if (! $movementTypeId) {
            $movementTypeId = MovementType::query()->orderBy('id')->value('id');
        }
        if (! $movementTypeId) {
            throw new \RuntimeException('No se encontró tipo de movimiento para caja.');
        }

        return (int) $movementTypeId;
    }

    private function resolveCashIncomeDocumentTypeId(int $cashMovementTypeId): int
    {
        $documentTypeId = DocumentType::query()
            ->where('movement_type_id', $cashMovementTypeId)
            ->tap(fn ($q) => InsensitiveSearch::whereInsensitiveLikePattern($q, 'name', '%ingreso%'))
            ->orderBy('id')
            ->value('id');

        if (! $documentTypeId) {
            $documentTypeId = DocumentType::query()
                ->where('movement_type_id', $cashMovementTypeId)
                ->orderBy('id')
                ->value('id');
        }

        if (! $documentTypeId) {
            throw new \RuntimeException('No se encontró tipo de documento para movimiento de caja.');
        }

        return (int) $documentTypeId;
    }

    private function generateCashMovementNumber(int $branchId, int $cashRegisterId, ?int $paymentConceptId = null): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $lockName = "cash_num_{$branchId}_{$cashRegisterId}";
            DB::statement('SELECT GET_LOCK(?, 10)', [$lockName]);
        }

        try {
            $lastRecord = Movement::query()
                ->select('movements.number')
                ->join('cash_movements', 'cash_movements.movement_id', '=', 'movements.id')
                ->where('movements.branch_id', $branchId)
                ->where('cash_movements.cash_register_id', $cashRegisterId)
                ->when($paymentConceptId !== null, function ($query) use ($paymentConceptId) {
                    $query->where('cash_movements.payment_concept_id', $paymentConceptId);
                })
                ->lockForUpdate()
                ->orderByDesc('movements.number')
                ->first();

            $lastNumber = $lastRecord?->number;
            $nextSequence = $lastNumber ? ((int) $lastNumber + 1) : 1;

            return str_pad((string) $nextSequence, 8, '0', STR_PAD_LEFT);
        } finally {
            if ($driver === 'mysql' || $driver === 'mariadb') {
                $lockName = "cash_num_{$branchId}_{$cashRegisterId}";
                DB::statement('SELECT RELEASE_LOCK(?)', [$lockName]);
            }
        }
    }
}
