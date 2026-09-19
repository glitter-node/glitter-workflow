<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('glitter_workflow_action_executions') || Schema::hasColumn('glitter_workflow_action_executions', 'claim_token')) return;
        Schema::table('glitter_workflow_action_executions', function (Blueprint $table): void {
            $table->uuid('claim_token')->nullable()->after('attempt_count')->comment('원격 부수효과 시도의 서버 생성 소유 토큰');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('glitter_workflow_action_executions') || ! Schema::hasColumn('glitter_workflow_action_executions', 'claim_token')) return;
        Schema::table('glitter_workflow_action_executions', function (Blueprint $table): void {
            $table->dropColumn('claim_token');
        });
    }
};
