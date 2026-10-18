<?php
namespace Modules\Glitter\Workflow\Console\Commands;
use Illuminate\Console\Command;use Modules\Glitter\Workflow\Services\WorkflowRecoveryService;
final class RecoverWorkflowRuntime extends Command {protected $signature='glitter-workflow:recover {--dry-run : Inspect without dispatching} {--limit=50 : Maximum records per category}';protected $description='Replay recoverable Glitter Workflow queue handoffs.';public function handle(WorkflowRecoveryService $service):int{$result=$service->recover((int)$this->option('limit'),(bool)$this->option('dry-run'));foreach($result as $kind=>$ids)$this->line($kind.': '.count($ids));return self::SUCCESS;}}
