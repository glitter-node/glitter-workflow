import { describe, expect, it } from 'vitest';
import { createLayoutTest } from '@core/template-engine/__tests__/utils/layoutTestUtils';
import type { LayoutJson } from '@core/template-engine/__tests__/utils/layoutTestUtils';
import layoutJson from '../../../layouts/admin/admin_workflow_settings.json';
import { assertFoundationStructure, registry, translated, translations } from './foundationLayoutSupport';

const layout = layoutJson as LayoutJson;

describe('admin_workflow_settings', () => {
    it('preserves the admin inheritance and component structure', () => {
        assertFoundationStructure(layout, 'admin_workflow_settings', ['settings']);
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

it('uses the safe settings datasource and exposes only permission-gated scalar updates', () => {
    const json = JSON.stringify(layout);
    expect(json).toContain('/api/modules/glitter-workflow/admin/settings');
    expect(json).toContain('"method":"GET"');
    expect(json).toContain('"method":"PUT"');
    expect(json).toContain('max_actions_per_workflow');
    expect(json).toContain('max_condition_depth');
    expect(json).toContain('"name":"Input"');
    expect(json).toContain('"name":"Button"');
    expect(json).toContain('settings?.data?.abilities?.can_update === true');
    expect(json).toContain('max_workflow_recursion_depth');
    expect(json).toContain('connections?.count');
    expect(json).not.toContain('max_workflow_recursion_depth": "{{_local.form');
    expect(json).not.toContain('connections": "{{_local.form');
    expect(json).not.toContain('PATCH');
});
