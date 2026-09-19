<?php

namespace Modules\Glitter\Workflow;

use App\Extension\AbstractModule;
use Modules\Glitter\Workflow\Services\WorkflowSettingsService;

/**
 * Glitter Workflow 모듈 진입점.
 */
class Module extends AbstractModule
{
    /**
     * Preserve the JSON-object identity of the private connections registry
     * before the generic module settings initializer writes category files.
     */
    public function install(): bool
    {
        return (new WorkflowSettingsService($this->getStorage()))->initializeDefaults();
    }

    /**
     * 모듈 메타데이터를 반환합니다.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return [
            'author' => 'Glitter.kr',
            'license' => 'MIT',
            'keywords' => ['workflow', 'automation', 'orchestration'],
        ];
    }

    /**
     * 모듈 권한 목록을 반환합니다.
     *
     * @return array<string, mixed>
     */
    public function getPermissions(): array
    {
        return [
            'name' => [
                'ko' => 'Glitter Workflow',
                'en' => 'Glitter Workflow',
            ],
            'description' => [
                'ko' => '워크플로 및 자동화 관리 권한',
                'en' => 'Workflow and automation management permissions',
            ],
            'categories' => [
                $this->permissionCategory(
                    'workflows',
                    ['ko' => '워크플로', 'en' => 'Workflows'],
                    [
                        ['read', ['ko' => '워크플로 조회', 'en' => 'View Workflows'], ['admin', 'manager']],
                        ['create', ['ko' => '워크플로 생성', 'en' => 'Create Workflows'], ['admin', 'manager']],
                        ['update', ['ko' => '워크플로 수정', 'en' => 'Update Workflows'], ['admin', 'manager']],
                        ['delete', ['ko' => '워크플로 삭제', 'en' => 'Delete Workflows'], ['admin']],
                        ['activate', ['ko' => '워크플로 활성화', 'en' => 'Activate Workflows'], ['admin']],
                    ],
                    'workflow',
                    'created_by',
                ),
                $this->permissionCategory(
                    'executions',
                    ['ko' => '실행 이력', 'en' => 'Executions'],
                    [
                        ['read', ['ko' => '실행 이력 조회', 'en' => 'View Executions'], ['admin', 'manager']],
                        ['retry', ['ko' => '실행 재시도', 'en' => 'Retry Executions'], ['admin']],
                        ['cancel', ['ko' => '실행 취소', 'en' => 'Cancel Executions'], ['admin']],
                    ],
                    'execution',
                    null,
                ),
                $this->permissionCategory(
                    'settings',
                    ['ko' => '환경설정', 'en' => 'Settings'],
                    [
                        ['read', ['ko' => '환경설정 조회', 'en' => 'View Settings'], ['admin']],
                        ['update', ['ko' => '환경설정 수정', 'en' => 'Update Settings'], ['admin']],
                    ],
                    null,
                    null,
                ),
            ],
        ];
    }

    /**
     * 관리자 메뉴를 반환합니다.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAdminMenus(): array
    {
        return [
            [
                'name' => ['ko' => '워크플로 관리', 'en' => 'Glitter Workflow'],
                'slug' => 'glitter-workflow',
                'url' => null,
                'icon' => 'fas fa-diagram-project',
                'order' => 40,
                'children' => [
                    [
                        'name' => ['ko' => '워크플로 관리', 'en' => 'Workflow Management'],
                        'slug' => 'glitter-workflow-workflows',
                        'url' => '/admin/glitter-workflow/workflows',
                        'icon' => 'fas fa-list-check',
                        'order' => 1,
                        'permission' => 'glitter-workflow.workflows.read',
                    ],
                    [
                        'name' => ['ko' => '실행 이력', 'en' => 'Executions'],
                        'slug' => 'glitter-workflow-executions',
                        'url' => '/admin/glitter-workflow/executions',
                        'icon' => 'fas fa-clock-rotate-left',
                        'order' => 2,
                        'permission' => 'glitter-workflow.executions.read',
                    ],
                    [
                        'name' => ['ko' => '환경설정', 'en' => 'Settings'],
                        'slug' => 'glitter-workflow-settings',
                        'url' => '/admin/glitter-workflow/settings',
                        'icon' => 'fas fa-gear',
                        'order' => 3,
                        'permission' => 'glitter-workflow.settings.read',
                    ],
                ],
            ],
        ];
    }


    public function getHookListeners(): array
    {
        return [
            \Modules\Glitter\Workflow\Listeners\WorkflowActivityLogListener::class,
            \Modules\Glitter\Workflow\Listeners\UserRegisteredWorkflowCaptureListener::class,
            \Modules\Glitter\Workflow\Listeners\UserUpdatedWorkflowCaptureListener::class,
            \Modules\Glitter\Workflow\Listeners\BoardPostWorkflowCaptureListener::class,
            \Modules\Glitter\Workflow\Listeners\BoardCommentWorkflowCaptureListener::class,
        ];
    }

    /**
     * 권한 카테고리를 현재 G7 계층 구조로 만듭니다.
     *
     * @param  array{ko: string, en: string}  $name
     * @param  array<int, array{0: string, 1: array{ko: string, en: string}, 2: array<int, string>}>  $actions
     * @return array<string, mixed>
     */
    private function permissionCategory(
        string $identifier,
        array $name,
        array $actions,
        ?string $resourceRouteKey,
        ?string $ownerKey,
    ): array {
        return [
            'identifier' => $identifier,
            'resource_route_key' => $resourceRouteKey,
            'owner_key' => $ownerKey,
            'name' => $name,
            'description' => $name,
            'permissions' => array_map(
                fn (array $action): array => [
                    'action' => $action[0],
                    'name' => $action[1],
                    'description' => $action[1],
                    'type' => 'admin',
                    'roles' => $action[2],
                ],
                $actions,
            ),
        ];
    }
}
