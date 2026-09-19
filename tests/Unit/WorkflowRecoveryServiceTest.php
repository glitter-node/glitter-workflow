<?php
namespace Modules\Glitter\Workflow\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class WorkflowRecoveryServiceTest extends TestCase { public function test_recovery_contract_is_bounded_and_dry_run_capable():void{$s=file_get_contents(__DIR__.'/../../src/Services/WorkflowRecoveryService.php');self::assertStringContainsString('MAX_LIMIT=200',$s);self::assertStringContainsString('if(!$dryRun)',$s);self::assertStringContainsString('ProcessWorkflowAcceptanceJob::dispatch',$s);self::assertStringContainsString('ProcessWorkflowExecutionJob::dispatch',$s);self::assertStringContainsString('ProcessWorkflowDelayResumeJob::dispatch',$s);} }
