<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Glitter\Workflow\Enums\ActionExecutionStatus;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('glitter_workflow_action_executions')) {
            return;
        }

        Schema::create('glitter_workflow_action_executions', function (Blueprint $table): void {
            $table->id()->comment('액션 실행 ID');
            $table->uuid('uuid')->unique()->comment('외부 노출용 액션 실행 UUID');
            $table->foreignId('execution_id')->comment('상위 워크플로 실행 ID')
                ->constrained('glitter_workflow_executions')->restrictOnDelete();
            $table->unsignedInteger('action_index')->comment('순차 액션 인덱스');
            $table->string('action_type', 32)->comment('액션 유형 (notification, webhook, delay)');
            $table->json('action_snapshot')->comment('실행 시점 액션 설정 스냅샷');
            $table->char('config_hash', 64)->comment('액션 설정 SHA-256 해시');
            $table->string('status', 32)->default(ActionExecutionStatus::Pending->value)
                ->comment('액션 상태 (pending, scheduled, running, succeeded, failed, cancelled, skipped)');
            $table->unsignedInteger('attempt_count')->default(0)->comment('액션 시도 횟수');
            $table->timestamp('scheduled_at')->nullable()->comment('액션 예약 시각');
            $table->timestamp('started_at')->nullable()->comment('액션 시작 시각');
            $table->timestamp('finished_at')->nullable()->comment('액션 종료 시각');
            $table->char('idempotency_key', 64)->nullable()->unique()->comment('외부 부수효과 멱등성 SHA-256 키');
            $table->json('result_metadata')->nullable()->comment('비밀값이 제거된 결과 메타데이터');
            $table->string('error_code', 100)->nullable()->comment('비밀값을 포함하지 않는 오류 코드');
            $table->string('error_message', 1000)->nullable()->comment('비밀값 제거 및 길이 제한된 오류 메시지');
            $table->timestamps();

            $table->unique(['execution_id', 'action_index'], 'gw_action_execution_index_uniq');
            $table->index(['status', 'scheduled_at'], 'gw_action_status_sched_idx');
            $table->index(['execution_id', 'status'], 'gw_action_execution_status_idx');
        });

        if (DB::getDriverName() === 'mysql') {
            Schema::table('glitter_workflow_action_executions', function (Blueprint $table): void {
                $table->comment('Glitter Workflow 개별 액션 실행 원장');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('glitter_workflow_action_executions');
    }
};
