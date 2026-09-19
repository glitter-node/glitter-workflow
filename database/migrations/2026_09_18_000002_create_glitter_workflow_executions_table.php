<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Glitter\Workflow\Enums\WorkflowExecutionStatus;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('glitter_workflow_executions')) {
            return;
        }

        Schema::create('glitter_workflow_executions', function (Blueprint $table): void {
            $table->id()->comment('워크플로 실행 ID');
            $table->uuid('uuid')->unique()->comment('외부 노출 및 상관관계용 실행 UUID');
            $table->foreignId('workflow_id')->nullable()->comment('원본 워크플로 ID')->constrained('glitter_workflows')->nullOnDelete();
            $table->unsignedInteger('workflow_version')->comment('실행 시점 워크플로 버전');
            $table->json('workflow_snapshot')->comment('실행 시점 워크플로 정의 스냅샷');
            $table->string('trigger_key', 100)->comment('모듈 소유 안정 트리거 키');
            $table->string('trigger_subject_type', 100)->nullable()->comment('트리거 주체의 공개 타입 식별자');
            $table->string('trigger_subject_id', 191)->nullable()->comment('트리거 주체의 스칼라 식별자');
            $table->char('deduplication_key', 64)->nullable()->comment('동일 워크플로 이벤트 중복 방지 SHA-256 키');
            $table->json('trigger_context')->comment('버전되고 비밀값이 제거된 스칼라 트리거 컨텍스트');
            $table->string('status', 32)->default(WorkflowExecutionStatus::Pending->value)
                ->comment('실행 상태 (pending, scheduled, running, succeeded, failed, cancelled, skipped)');
            $table->timestamp('scheduled_at')->nullable()->comment('예약 실행 시각');
            $table->timestamp('started_at')->nullable()->comment('실행 시작 시각');
            $table->timestamp('finished_at')->nullable()->comment('실행 종료 시각');
            $table->unsignedInteger('attempts')->default(0)->comment('실행 시도 횟수');
            $table->uuid('correlation_id')->comment('연관 실행 묶음 UUID');
            $table->uuid('causation_id')->nullable()->comment('직접 원인이 된 실행 UUID');
            $table->unsignedSmallInteger('depth')->default(0)->comment('워크플로 인과 깊이 (v0.1 외부 트리거는 0)');
            $table->string('error_code', 100)->nullable()->comment('비밀값을 포함하지 않는 오류 코드');
            $table->string('error_message', 1000)->nullable()->comment('비밀값 제거 및 길이 제한된 오류 메시지');
            $table->timestamps();

            $table->unique(['workflow_id', 'deduplication_key'], 'gw_exec_workflow_dedup_uniq');
            $table->index(['workflow_id', 'created_at'], 'gw_exec_workflow_created_idx');
            $table->index(['trigger_key', 'status', 'scheduled_at'], 'gw_exec_trigger_status_sched_idx');
            $table->index('correlation_id', 'gw_exec_correlation_idx');
            $table->index('causation_id', 'gw_exec_causation_idx');
            $table->index(['status', 'created_at'], 'gw_exec_status_created_idx');
        });

        if (DB::getDriverName() === 'mysql') {
            Schema::table('glitter_workflow_executions', function (Blueprint $table): void {
                $table->comment('Glitter Workflow 기계 실행 원장');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('glitter_workflow_executions');
    }
};
