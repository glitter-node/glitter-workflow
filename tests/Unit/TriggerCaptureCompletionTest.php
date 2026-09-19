<?php

namespace Modules\Glitter\Workflow\Tests\Unit;

use App\Models\User;
use DateTimeImmutable;
use InvalidArgumentException;
use Modules\Glitter\Workflow\DTO\Triggers\TriggerContext;
use Modules\Glitter\Workflow\Enums\TriggerKey;
use Modules\Glitter\Workflow\Listeners\BoardCommentWorkflowCaptureListener;
use Modules\Glitter\Workflow\Listeners\BoardPostWorkflowCaptureListener;
use Modules\Glitter\Workflow\Listeners\UserRegisteredWorkflowCaptureListener;
use Modules\Glitter\Workflow\Listeners\UserUpdatedWorkflowCaptureListener;
use Modules\Glitter\Workflow\Repositories\WorkflowRepositoryInterface;
use Modules\Glitter\Workflow\Services\Triggers\Adapters\BoardCommentCreatedTriggerAdapter;
use Modules\Glitter\Workflow\Services\Triggers\Adapters\BoardPostCreatedTriggerAdapter;
use Modules\Glitter\Workflow\Services\Triggers\Adapters\UserRegisteredTriggerAdapter;
use Modules\Glitter\Workflow\Services\Triggers\Adapters\UserUpdatedTriggerAdapter;
use Modules\Glitter\Workflow\Services\Triggers\BuiltInTriggerDefinitionRegistry;
use Modules\Glitter\Workflow\Services\WorkflowAcceptanceService;
use Modules\Sirsoft\Board\Models\Comment;
use Modules\Sirsoft\Board\Models\Post;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class TriggerCaptureCompletionTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        $root = dirname(__DIR__, 2);
        spl_autoload_register(static function (string $class) use ($root): void {
            $prefix = 'Modules\\Glitter\\Workflow\\';
            if (! str_starts_with($class, $prefix)) {
                return;
            }

            $path = $root.'/src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
            if (is_file($path)) {
                require_once $path;
            }
        }, true, true);

        require_once $root.'/module.php';
    }

    #[Test]
    public function only_four_approved_source_hooks_are_registered_synchronously(): void
    {
        $listeners = [
            UserRegisteredWorkflowCaptureListener::class => 'core.auth.after_register',
            UserUpdatedWorkflowCaptureListener::class => 'core.user.after_update',
            BoardPostWorkflowCaptureListener::class => 'sirsoft-board.post.after_create',
            BoardCommentWorkflowCaptureListener::class => 'sirsoft-board.comment.after_create',
        ];
        $registered = (new \Modules\Glitter\Workflow\Module)->getHookListeners();

        foreach ($listeners as $class => $hook) {
            self::assertContains($class, $registered);
            self::assertSame([
                $hook => ['method' => 'capture', 'priority' => 30, 'sync' => true],
            ], $class::getSubscribedHooks());
            $source = file_get_contents((new \ReflectionClass($class))->getFileName());
            self::assertStringNotContainsString('DispatchHookListenerJob', $source);
        }

        self::assertCount(4, array_filter($registered, static fn (string $class): bool => str_contains($class, 'WorkflowCaptureListener')));
        self::assertCount(4, TriggerKey::cases());

        $registrar = file_get_contents(dirname(__DIR__, 5).'/app/Extension/HookListenerRegistrar.php');
        self::assertStringContainsString('elseif ($forceSync)', $registrar);
        self::assertStringContainsString('HookManager::addAction($hookName', $registrar);
    }

    #[Test]
    public function four_adapters_emit_exact_versioned_allowlisted_contexts(): void
    {
        $contexts = $this->contexts();
        $registry = new BuiltInTriggerDefinitionRegistry;

        foreach ($contexts as $key => $context) {
            $definition = $registry->find($key);
            self::assertNotNull($definition);
            self::assertSame($definition->sourceHook, match ($key) {
                'user.registered' => 'core.auth.after_register',
                'user.updated' => 'core.user.after_update',
                'board.post.created' => 'sirsoft-board.post.after_create',
                'board.comment.created' => 'sirsoft-board.comment.after_create',
            });
            self::assertSame($key.'@1', $context->schemaIdentifier());
            self::assertSame(
                array_map(static fn ($field): string => $field->key, $definition->fields),
                array_keys($context->data),
            );
            foreach ($context->data as $value) {
                self::assertTrue($value === null || is_scalar($value));
            }
        }

        self::assertSame('core.user', $contexts['user.registered']->subjectType);
        self::assertSame('core.user', $contexts['user.updated']->subjectType);
        self::assertSame('sirsoft-board.post', $contexts['board.post.created']->subjectType);
        self::assertSame('sirsoft-board.comment', $contexts['board.comment.created']->subjectType);
        self::assertSame(34, $contexts['board.comment.created']->data['comment.post_id']);
        self::assertSame(12, $contexts['board.post.created']->data['post.id']);
    }

    #[Test]
    public function source_secrets_and_raw_arguments_never_enter_persistable_contexts(): void
    {
        foreach ($this->contexts() as $context) {
            $json = json_encode($context->toArray(), JSON_THROW_ON_ERROR);
            foreach ([
                'registration-secret',
                'update-secret',
                'board-secret',
                'comment-secret',
                'verification_token',
                'password',
                'password_confirmation',
                'remember_token',
                'registration_data',
                'original_request',
                'previous_snapshot',
                'content',
                'options',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $json);
            }
        }
    }

    #[Test]
    public function acceptance_validation_matches_each_adapter_schema_before_db(): void
    {
        [$service, $validate] = $this->validator();
        $contexts = $this->contexts();

        foreach ($contexts as $context) {
            $this->assertAccepted($validate, $service, $context);
        }

        foreach ([
            ['user.registered', 'user.uuid'],
            ['board.post.created', 'post.id'],
            ['board.post.created', 'post.board_id'],
            ['board.post.created', 'post.board_slug'],
            ['board.post.created', 'post.is_notice'],
            ['board.post.created', 'post.is_secret'],
            ['board.post.created', 'post.depth'],
            ['board.comment.created', 'comment.id'],
            ['board.comment.created', 'comment.board_id'],
            ['board.comment.created', 'comment.board_slug'],
            ['board.comment.created', 'comment.post_id'],
            ['board.comment.created', 'comment.is_secret'],
            ['board.comment.created', 'comment.depth'],
        ] as [$key, $field]) {
            $data = $contexts[$key]->data;
            $data[$field] = null;
            $this->assertRejected($validate, $service, $this->copy($contexts[$key], data: $data));
        }

        foreach ([
            ['user.registered', 'user.status'],
            ['user.updated', 'user.country'],
            ['board.post.created', 'post.actor_user_id'],
            ['board.post.created', 'post.category'],
            ['board.post.created', 'post.parent_id'],
            ['board.post.created', 'post.created_at'],
            ['board.comment.created', 'comment.actor_user_id'],
            ['board.comment.created', 'comment.parent_id'],
            ['board.comment.created', 'comment.status'],
            ['board.comment.created', 'comment.created_at'],
        ] as [$key, $field]) {
            $data = $contexts[$key]->data;
            $data[$field] = null;
            $this->assertAccepted($validate, $service, $this->copy($contexts[$key], data: $data));
        }

        $wrongInteger = $contexts['board.comment.created']->data;
        $wrongInteger['comment.post_id'] = '34';
        $this->assertRejected($validate, $service, $this->copy($contexts['board.comment.created'], data: $wrongInteger));

        $wrongBoolean = $contexts['board.post.created']->data;
        $wrongBoolean['post.is_secret'] = 1;
        $this->assertRejected($validate, $service, $this->copy($contexts['board.post.created'], data: $wrongBoolean));

        $array = $contexts['user.registered']->data;
        $array['user.status'] = ['password' => 'registration-secret'];
        $this->assertRejected($validate, $service, $this->copy($contexts['user.registered'], data: $array));

        $object = $contexts['board.comment.created']->data;
        $object['comment.status'] = new \stdClass;
        $this->assertRejected($validate, $service, $this->copy($contexts['board.comment.created'], data: $object));

        $invalidDate = $contexts['user.updated']->data;
        $invalidDate['user.updated_at'] = '2026-09-18T10:01:00Z';
        $this->assertRejected($validate, $service, $this->copy($contexts['user.updated'], data: $invalidDate));

        $zeroId = $contexts['board.post.created']->data;
        $zeroId['post.id'] = 0;
        $this->assertRejected($validate, $service, $this->copy($contexts['board.post.created'], data: $zeroId));

        $negativeDepth = $contexts['board.comment.created']->data;
        $negativeDepth['comment.depth'] = -1;
        $this->assertRejected($validate, $service, $this->copy($contexts['board.comment.created'], data: $negativeDepth));
    }

    #[Test]
    public function acceptance_validation_rejects_missing_extra_and_identity_mismatches_before_db(): void
    {
        [$service, $validate] = $this->validator();
        $contexts = $this->contexts();

        $extra = $contexts['user.registered']->data + ['verification_token' => 'registration-secret'];
        $this->assertRejected($validate, $service, $this->copy($contexts['user.registered'], data: $extra));

        $missing = $contexts['user.registered']->data;
        unset($missing['user.country']);
        $this->assertRejected($validate, $service, $this->copy($contexts['user.registered'], data: $missing));

        $userMismatch = $contexts['user.registered']->data;
        $userMismatch['user.uuid'] = '01995600-1234-7000-8000-000000000000';
        $this->assertRejected($validate, $service, $this->copy($contexts['user.registered'], data: $userMismatch));

        $postMismatch = $contexts['board.post.created']->data;
        $postMismatch['post.id'] = 13;
        $this->assertRejected($validate, $service, $this->copy($contexts['board.post.created'], data: $postMismatch));

        $commentMismatch = $contexts['board.comment.created']->data;
        $commentMismatch['comment.id'] = 57;
        $this->assertRejected($validate, $service, $this->copy($contexts['board.comment.created'], data: $commentMismatch));

        $this->assertRejected($validate, $service, $this->copy($contexts['user.registered'], schemaVersion: 2));
        $this->assertRejected($validate, $service, $this->copy($contexts['user.registered'], subjectId: 'other'));
    }

    #[Test]
    public function acceptance_remains_transactional_after_commit_and_does_not_dedupe_source_events(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/src/Services/WorkflowAcceptanceService.php');
        self::assertStringContainsString('$this->assertSafeContext($context);', $source);
        self::assertStringContainsString('$this->workflows->hasEnabledForTrigger($context->key)', $source);
        self::assertStringContainsString('DB::transaction(function () use ($context)', $source);
        self::assertStringContainsString('WorkflowAcceptance::query()->create(', $source);
        self::assertStringContainsString("'source_event_id' => null", $source);
        self::assertStringContainsString('ProcessWorkflowAcceptanceJob::dispatch($acceptance->uuid)->afterCommit()', $source);
        self::assertStringNotContainsString('firstOrCreate(', $source);
        self::assertStringNotContainsString('ProcessWorkflowExecutionJob', $source);
        self::assertLessThan(
            strpos($source, 'WorkflowAcceptance::query()->create('),
            strpos($source, '$this->assertSafeContext($context);'),
        );
    }

    #[Test]
    public function zero_enabled_workflows_returns_before_acceptance_or_job_dispatch(): void
    {
        $workflows = $this->createMock(WorkflowRepositoryInterface::class);
        $workflows->expects(self::once())->method('hasEnabledForTrigger')
            ->with(TriggerKey::UserRegistered)->willReturn(false);
        $service = new WorkflowAcceptanceService(new BuiltInTriggerDefinitionRegistry, $workflows);

        self::assertNull($service->accept($this->contexts()['user.registered']));
    }

    #[Test]
    public function interest_lookup_is_one_stable_trigger_exists_query_and_processing_rechecks_enabled_state(): void
    {
        $repository = file_get_contents(dirname(__DIR__, 2).'/src/Repositories/WorkflowRepository.php');
        $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_09_18_000001_create_glitter_workflows_table.php');
        $acceptance = file_get_contents(dirname(__DIR__, 2).'/src/Services/WorkflowAcceptanceService.php');
        $processing = file_get_contents(dirname(__DIR__, 2).'/src/Jobs/ProcessWorkflowAcceptanceJob.php');

        self::assertStringContainsString('hasEnabledForTrigger(TriggerKey $triggerKey)', $repository);
        self::assertStringContainsString("where('trigger_key',\$triggerKey->value)->where('is_active',true)->exists()", $repository);
        self::assertStringContainsString("\$table->index(['trigger_key', 'is_active'], 'gw_trigger_active_idx')", $migration);
        self::assertSame(1, substr_count($acceptance, 'WorkflowAcceptance::query()->create('));
        self::assertSame(1, substr_count($acceptance, 'ProcessWorkflowAcceptanceJob::dispatch('));
        self::assertStringContainsString("where('trigger_key',\$a->source_key)->where('is_active',true)", $processing);
        self::assertStringNotContainsString('ConditionEvaluator', substr($acceptance, 0, strpos($acceptance, 'DB::transaction')));
        self::assertStringNotContainsString('SafeOutbound', $acceptance);
    }

    /** @return array{WorkflowAcceptanceService, ReflectionMethod} */
    private function validator(): array
    {
        $service = new WorkflowAcceptanceService(
            new BuiltInTriggerDefinitionRegistry,
            $this->createMock(WorkflowRepositoryInterface::class),
        );

        return [$service, new ReflectionMethod($service, 'assertSafeContext')];
    }

    private function assertAccepted(ReflectionMethod $validate, WorkflowAcceptanceService $service, TriggerContext $context): void
    {
        $validate->invoke($service, $context);
        self::assertTrue(true);
    }

    private function assertRejected(ReflectionMethod $validate, WorkflowAcceptanceService $service, TriggerContext $context): void
    {
        try {
            $validate->invoke($service, $context);
            self::fail('Unsafe context was accepted.');
        } catch (InvalidArgumentException) {
            self::assertTrue(true);
        }
    }

    private function copy(TriggerContext $context, ?int $schemaVersion = null, ?string $subjectId = null, ?array $data = null): TriggerContext
    {
        return new TriggerContext(
            $context->key,
            $schemaVersion ?? $context->schemaVersion,
            $context->subjectType,
            $subjectId ?? $context->subjectId,
            $data ?? $context->data,
        );
    }

    /** @return array<string, TriggerContext> */
    private function contexts(): array
    {
        $user = $this->getMockBuilder(User::class)->disableOriginalConstructor()
            ->onlyMethods(['getAttribute', 'getKey'])->getMock();
        $user->expects($this->never())->method('getKey');
        $userAttributes = [
            'uuid' => '01995600-1234-7000-8000-123456789abc',
            'status' => 'active',
            'language' => 'ko',
            'country' => null,
            'created_at' => new DateTimeImmutable('2026-09-18T10:00:00+00:00'),
            'updated_at' => new DateTimeImmutable('2026-09-18T10:01:00+00:00'),
            'password' => 'registration-secret',
            'remember_token' => 'update-secret',
        ];
        $user->method('getAttribute')->willReturnCallback(static fn (string $key) => $userAttributes[$key] ?? null);

        $post = $this->getMockBuilder(Post::class)->disableOriginalConstructor()
            ->onlyMethods(['getAttribute', 'getKey'])->getMock();
        $post->method('getKey')->willReturn(12);
        $postAttributes = [
            'board_id' => 2,
            'user_id' => '5',
            'category' => 'general',
            'is_notice' => false,
            'is_secret' => true,
            'status' => 'published',
            'parent_id' => null,
            'depth' => 0,
            'created_at' => new DateTimeImmutable('2026-09-18T10:02:00+00:00'),
            'content' => 'board-secret',
        ];
        $post->method('getAttribute')->willReturnCallback(static fn (string $key) => $postAttributes[$key] ?? null);

        $comment = $this->getMockBuilder(Comment::class)->disableOriginalConstructor()
            ->onlyMethods(['getAttribute', 'getKey'])->getMock();
        $comment->method('getKey')->willReturn(56);
        $commentAttributes = [
            'board_id' => 2,
            'post_id' => '34',
            'user_id' => null,
            'parent_id' => null,
            'is_secret' => false,
            'status' => 'published',
            'depth' => 0,
            'created_at' => new DateTimeImmutable('2026-09-18T10:03:00+00:00'),
            'content' => 'comment-secret',
        ];
        $comment->method('getAttribute')->willReturnCallback(static fn (string $key) => $commentAttributes[$key] ?? null);

        return [
            'user.registered' => (new UserRegisteredTriggerAdapter)->map($user, [
                'verification_token' => 'registration-secret',
                'registration_data' => ['password' => 'registration-secret', 'nested' => ['secret' => 'registration-secret']],
            ]),
            'user.updated' => (new UserUpdatedTriggerAdapter)->map($user, [
                'password' => 'update-secret',
                'original_request' => ['remember_token' => 'update-secret'],
            ], ['password' => 'update-secret']),
            'board.post.created' => (new BoardPostCreatedTriggerAdapter)->map($post, 'news', [
                'options' => ['secret' => 'board-secret'],
            ]),
            'board.comment.created' => (new BoardCommentCreatedTriggerAdapter)->map($comment, 'news'),
        ];
    }
}
