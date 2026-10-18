import { describe, expect, it } from 'vitest';
import { createLayoutTest } from '@core/template-engine/__tests__/utils/layoutTestUtils';
import type { LayoutJson } from '@core/template-engine/__tests__/utils/layoutTestUtils';
import layoutJson from '../../../layouts/admin/admin_workflow_edit.json';
import { assertFoundationStructure, registry, translated, translations } from './foundationLayoutSupport';

const layout = layoutJson as LayoutJson;

describe('admin_workflow_edit', () => {
    it('preserves the admin inheritance and component structure', () => {
        assertFoundationStructure(layout, 'admin_workflow_edit');
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

it('loads the UUID definition and submits an optimistic-lock version through PUT', () => {
    const source = (layout.data_sources ?? [])[0] as any;
    expect(source.endpoint).toBe('/api/modules/glitter-workflow/admin/workflows/{{route?.uuid}}');
    expect(source.method).toBe('GET');
    expect(source.initLocal).toBe('form');

    const json = JSON.stringify(layout);
    expect(json).toContain('"method":"PUT"');
    expect(json).toContain('"version"');
    expect(json).toContain('"condition_tree"');
    expect(json).toContain('"action_plan"');
    expect(json).toContain('error.status === 409');
    expect(json).not.toContain('"is_active"');
    expect(json).toContain('onSuccess');
});

it('renders the translated save action through supported Button children', () => {
    const save = (layout.slots!.content![0] as any).children.find((child: any) => child.id === 'workflow_form').children.find((child: any) => child.id === 'footer_buttons');
    const button = save.children.find((child: any) => child.id === 'save');
    const label = button.children.find((child: any) => child.id === 'save_label');

    expect(save.props.className).toBe('sticky-footer-buttons');
    expect(button.name).toBe('Button');
    expect(button.props).not.toHaveProperty('label');
    expect(label).toMatchObject({ name: 'Span', text: '$t:glitter-workflow.admin.workflows.save' });
});
