import { describe, expect, it } from 'vitest';
import { createLayoutTest } from '@core/template-engine/__tests__/utils/layoutTestUtils';
import type { LayoutJson } from '@core/template-engine/__tests__/utils/layoutTestUtils';
import layoutJson from '../../../layouts/admin/admin_execution_detail.json';
import { assertFoundationStructure, registry, translated, translations } from './foundationLayoutSupport';

const layout = layoutJson as LayoutJson;

describe('admin_execution_detail', () => {
    it('preserves the admin inheritance and component structure', () => {
        assertFoundationStructure(layout, 'admin_execution_detail', ['execution']);
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

it('renders safe execution, action, and acceptance observability without mutations', () => {
    const json = JSON.stringify(layout);
    expect(json).toContain('/api/modules/glitter-workflow/admin/executions/{{route?.uuid}}');
    expect(json).toContain('action_executions');
    expect(json).toContain('acceptance');
    expect(json).toContain('status_code');
    expect(json).toContain('response_bytes');
    expect(json).toContain("row.action_type === 'webhook'");
    expect(json).not.toContain('delivery_status');
    expect(json).toContain('EmptyState');
    expect(json).not.toContain('retry');
    expect(json).not.toContain('cancel');
    expect(json).not.toContain('JSON.stringify');
    expect(json).not.toContain('"method":"PUT"');
    expect(json).not.toContain('"method":"DELETE"');
});
