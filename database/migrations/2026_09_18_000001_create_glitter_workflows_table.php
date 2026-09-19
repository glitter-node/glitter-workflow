<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('glitter_workflows')) {
            return;
        }

        Schema::create('glitter_workflows', function (Blueprint $table): void {
            $table->id()->comment('워크플로 ID');
            $table->uuid('uuid')->unique()->comment('외부 노출용 워크플로 UUID');
            $table->string('name', 200)->comment('워크플로 이름');
            $table->string('trigger_key', 100)->comment('모듈 소유 안정 트리거 키');
            $table->json('trigger_config')->nullable()->comment('트리거별 비실행 설정 JSON');
            $table->json('condition_tree')->nullable()->comment('all/any 기반 타입 조건 트리 JSON');
            $table->json('action_plan')->comment('순차 액션 계획 JSON');
            $table->unsignedInteger('version')->default(1)->comment('워크플로 정의 버전');
            $table->boolean('is_active')->default(false)->comment('활성화 여부 (1: 활성, 0: 비활성)');
            $table->foreignId('created_by')->nullable()->comment('생성자 ID')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->comment('최종 수정자 ID')->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['trigger_key', 'is_active'], 'gw_trigger_active_idx');
            $table->index(['created_at', 'id'], 'gw_created_idx');
        });

        if (DB::getDriverName() === 'mysql') {
            Schema::table('glitter_workflows', function (Blueprint $table): void {
                $table->comment('Glitter Workflow 정의');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('glitter_workflows');
    }
};
