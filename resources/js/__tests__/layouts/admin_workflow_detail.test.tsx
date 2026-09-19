import { describe, expect, it } from 'vitest';
import { createLayoutTest } from '@core/template-engine/__tests__/utils/layoutTestUtils';
import type { LayoutJson } from '@core/template-engine/__tests__/utils/layoutTestUtils';
import layoutJson from '../../../layouts/admin/admin_workflow_detail.json';
import moduleJson from '../../../../module.json';
import { assertFoundationStructure, registry, translated, translations } from './foundationLayoutSupport';

const layout = layoutJson as LayoutJson;

const parameterizedDetailKeys = [
    'name',
    'version',
    'created_at',
    'updated_at',
    'creator',
    'updater',
    'predicate_order',
    'predicate_field',
    'predicate_operator',
    'predicate_value',
    'action_index',
    'delay_seconds',
    'webhook_connection',
    'webhook_bindings',
] as const;

function detailTranslation(key: (typeof parameterizedDetailKeys)[number], locale: 'en' | 'ko'): string {
    return translations[locale].admin.workflows.detail[key];
}

describe('admin_workflow_detail', () => {
    it('preserves the admin inheritance and component structure', () => {
        assertFoundationStructure(layout, 'admin_workflow_detail');
    });

    it.each(['en', 'ko'] as const)('renders the Foundation shell in %s', async (locale) => {
        const test = createLayoutTest(layout, {
            componentRegistry: registry(),
            translations: { 'glitter-workflow': translations[locale] },
            locale,
        });
        try {
            await test.render();
            test.assertNoValidationErrors();
            expect(test.screen.getByRole('heading', {
                name: translated(layout.meta!.title!, locale),
            })).toBeTruthy();
            expect(test.screen.getByText(translated(layout.meta!.description, locale))).toBeTruthy();
        } finally {
            test.cleanup();
        }
    });
});

it('declares permission-gated CRUD and activation lifecycle actions', () => {
    const json = JSON.stringify(layout);
    expect(json).toContain('can_update');
    expect(json).toContain('can_delete');
    expect(json).toContain('can_activate');
    expect(json).toContain('/activate');
    expect(json).toContain('/deactivate');
    expect(json).toContain('"method":"DELETE"');
    expect(json).toContain('/admin/glitter-workflow/workflows/{{route?.uuid}}/edit');
    expect(json).toContain('refetchDataSource');
    expect(json).toContain('delete_confirm');
    expect(json).toContain('activate_confirm');
    expect(json).toContain('deactivate_confirm');
    expect(json).toContain('condition_group.children');
    expect(json).toContain('action_summary ?? []');
    expect(json).not.toContain('JSON.stringify');
    expect(json).toContain('condition_summary');
    expect(json).toContain('action_summary');
});

it('preserves supported Detail value bindings and localized module identity', () => {
    const json = JSON.stringify(layout);

    expect(parameterizedDetailKeys).toHaveLength(14);
    for (const key of parameterizedDetailKeys) {
        expect(json).toContain(`$t:glitter-workflow.admin.workflows.detail.${key}|value={{`);
        for (const locale of ['en', 'ko'] as const) {
            expect(detailTranslation(key, locale)).toContain('{value}');
            expect(detailTranslation(key, locale)).not.toContain(':value');
        }
    }

    expect(json).toContain('/api/modules/glitter-workflow/admin/workflows/{{route?.uuid}}');
    expect(json).toContain('workflow?.data?.name');
    expect(json).toContain('workflow?.data?.metadata?.version');
    expect(json).toContain('workflow?.data?.created_at');
    expect(json).toContain('workflow?.data?.updated_at');
    expect(json).toContain('workflow?.data?.creator?.name');
    expect(json).toContain('workflow?.data?.updater?.name');
    expect(json).toContain('can_update');
    expect(json).toContain('can_activate');
    expect(json).toContain('can_delete');

    expect(moduleJson.identifier).toBe('glitter-workflow');
    expect(moduleJson.name).toEqual({ ko: 'Glitter Workflow', en: 'Glitter Workflow' });
    expect(moduleJson.description).toEqual({
        ko: '이벤트와 작업을 자동화하는 워크플로',
        en: 'Workflows that automate events and actions',
    });
});

it('preserves the static trigger label and user.updated trigger translation path', () => {
    const json = JSON.stringify(layout);
    const triggerKey = 'glitter-workflow.admin.workflows.detail.trigger';
    const userUpdatedKey = 'glitter-workflow.admin.workflows.triggers.user_updated';

    expect(json).toContain('workflow_trigger_label');
    expect(json).toContain(`$t:${triggerKey}`);
    expect(json).toContain("workflow?.data?.trigger_key === 'user.updated'");
    expect(json).toContain(`$t:${userUpdatedKey}`);

    expect(translations.en.admin.workflows.detail.trigger).toBe('Trigger:');
    expect(translations.ko.admin.workflows.detail.trigger).toBe('트리거:');
    expect(translations.en.admin.workflows.detail.trigger).not.toContain(':value');
    expect(translations.ko.admin.workflows.detail.trigger).not.toContain(':value');
    expect(translations.en.admin.workflows.triggers.user_updated).toBe('User updated');
    expect(translations.ko.admin.workflows.triggers.user_updated).toBe('회원 정보 수정');
    expect(translations.en.admin.workflows.detail.action_count).toBe('Actions: :value');
    expect(translations.ko.admin.workflows.detail.action_count).toBe('액션 수: :value');
});
