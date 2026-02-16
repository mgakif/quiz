<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('leaderboards')) {
            return;
        }

        Schema::create('leaderboards_tmp', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('class_id')->nullable()->index();
            $table->enum('period', ['weekly', 'monthly', 'all_time'])->index();
            $table->date('start_date')->nullable()->index();
            $table->date('end_date')->nullable()->index();
            $table->timestamp('computed_at')->index();
            $table->json('payload');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['class_id', 'period', 'start_date', 'end_date'], 'leaderboards_scope_unique');
        });

        $oldRows = DB::table('leaderboards')->get();

        foreach ($oldRows as $row) {
            $computedAt = CarbonImmutable::parse((string) $row->computed_at);
            $startDate = null;
            $endDate = null;

            if ((string) $row->period === 'weekly') {
                $startDate = $computedAt->startOfWeek()->toDateString();
                $endDate = $computedAt->endOfWeek()->toDateString();
            }

            if ((string) $row->period === 'monthly') {
                $startDate = $computedAt->startOfMonth()->toDateString();
                $endDate = $computedAt->endOfMonth()->toDateString();
            }

            DB::table('leaderboards_tmp')->insert([
                'id' => (string) Str::uuid(),
                'class_id' => $row->class_id !== null ? (int) $row->class_id : null,
                'period' => (string) $row->period,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'computed_at' => $computedAt,
                'payload' => $row->payload,
                'created_at' => $row->created_at ?? now(),
            ]);
        }

        Schema::drop('leaderboards');
        Schema::rename('leaderboards_tmp', 'leaderboards');
    }

    public function down(): void
    {
        if (! Schema::hasTable('leaderboards')) {
            return;
        }

        Schema::create('leaderboards_tmp', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('class_id')->index();
            $table->enum('period', ['weekly', 'monthly', 'all_time'])->index();
            $table->timestamp('computed_at')->index();
            $table->json('payload');
            $table->timestamps();

            $table->unique(['class_id', 'period']);
        });

        $rows = DB::table('leaderboards')
            ->whereNotNull('class_id')
            ->orderByDesc('computed_at')
            ->get();

        $seen = [];

        foreach ($rows as $row) {
            $key = "{$row->class_id}:{$row->period}";

            if (array_key_exists($key, $seen)) {
                continue;
            }

            DB::table('leaderboards_tmp')->insert([
                'class_id' => (int) $row->class_id,
                'period' => (string) $row->period,
                'computed_at' => $row->computed_at,
                'payload' => $row->payload,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->created_at ?? now(),
            ]);

            $seen[$key] = true;
        }

        Schema::drop('leaderboards');
        Schema::rename('leaderboards_tmp', 'leaderboards');
    }
};
