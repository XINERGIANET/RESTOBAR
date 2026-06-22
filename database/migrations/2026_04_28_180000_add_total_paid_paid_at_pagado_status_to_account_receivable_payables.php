<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('account_receivable_payables')) {
            return;
        }

        Schema::table('account_receivable_payables', function (Blueprint $table) {
            if (! Schema::hasColumn('account_receivable_payables', 'total_paid')) {
                $table->decimal('total_paid', 15, 2)->default(0)->after('balance');
            }
            if (! Schema::hasColumn('account_receivable_payables', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('status');
            }
        });

        DB::table('account_receivable_payables')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $tp = round(max(0, (float) $row->total - (float) $row->balance), 2);
                    DB::table('account_receivable_payables')->where('id', $row->id)->update(['total_paid' => $tp]);
                }
            });

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE account_receivable_payables MODIFY COLUMN status ENUM('NUEVO','PAGANDO','PAGADO','CANCELADO') NOT NULL DEFAULT 'NUEVO'");
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE account_receivable_payables DROP CONSTRAINT IF EXISTS account_receivable_payables_status_check');
            DB::statement(
                "ALTER TABLE account_receivable_payables ADD CONSTRAINT account_receivable_payables_status_check "
                . "CHECK ((status)::text = ANY (ARRAY['NUEVO'::character varying, 'PAGANDO'::character varying, 'PAGADO'::character varying, 'CANCELADO'::character varying]::text[]))"
            );
        }

        DB::table('account_receivable_payables')
            ->whereNull('deleted_at')
            ->where('status', 'CANCELADO')
            ->where('balance', '<=', 0)
            ->update([
                'status' => 'PAGADO',
                'paid_at' => DB::raw('COALESCE(paid_at, updated_at)'),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('account_receivable_payables')) {
            return;
        }

        DB::table('account_receivable_payables')
            ->where('status', 'PAGADO')
            ->update(['status' => 'CANCELADO', 'paid_at' => null]);

        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE account_receivable_payables MODIFY COLUMN status ENUM('NUEVO','PAGANDO','CANCELADO') NOT NULL DEFAULT 'NUEVO'");
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE account_receivable_payables DROP CONSTRAINT IF EXISTS account_receivable_payables_status_check');
            DB::statement(
                "ALTER TABLE account_receivable_payables ADD CONSTRAINT account_receivable_payables_status_check "
                . "CHECK ((status)::text = ANY (ARRAY['NUEVO'::character varying, 'PAGANDO'::character varying, 'CANCELADO'::character varying]::text[]))"
            );
        }

        Schema::table('account_receivable_payables', function (Blueprint $table) {
            if (Schema::hasColumn('account_receivable_payables', 'paid_at')) {
                $table->dropColumn('paid_at');
            }
            if (Schema::hasColumn('account_receivable_payables', 'total_paid')) {
                $table->dropColumn('total_paid');
            }
        });
    }
};
