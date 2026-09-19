<?php

namespace Modules\Glitter\Workflow\Enums;

/** 워크플로 실행 상태. */
enum WorkflowExecutionStatus: string
{
    case Pending = 'pending';
    case Scheduled = 'scheduled';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Skipped = 'skipped';
}
