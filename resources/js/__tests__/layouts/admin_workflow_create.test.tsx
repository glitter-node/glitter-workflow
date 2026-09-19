import { describe, expect, it } from 'vitest';
import { createLayoutTest } from '@core/template-engine/__tests__/utils/layoutTestUtils';
import type { LayoutJson } from '@core/template-engine/__tests__/utils/layoutTestUtils';
import layoutJson from '../../../layouts/admin/admin_workflow_create.json';
import { assertFoundationStructure, registry, translated, translations } from './foundationLayoutSupport';

const layout = layoutJson as LayoutJson;

describe('admin_workflow_create', () => {
    it('preserves the admin inheritance and component structure', () => {
        assertFoundationStructure(layout, 'admin_workflow_create');
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

it('declares the bounded WHEN IF THEN editor controls', () => {
    const json = JSON.stringify(layout);
    expect(json).toContain('trigger_key');
    expect(json).toContain('condition_tree.group');
    expect(json).toContain('action_plan.0.type');
    expect(json).toContain('action_plan.0.config.delay_seconds');
});

it('declares the create CRUD endpoint and post-success navigation', () => {
    const json = JSON.stringify(layout);
    const save = (layout.slots!.content![0] as any).children.find((child: any) => child.id === 'workflow_form').children.find((child: any) => child.id === 'footer_buttons');
    const button = save.children.find((child: any) => child.id === 'save');
    const apiCall = button.actions.find((action: any) => action.handler === 'apiCall');
    const successNavigation = apiCall.onSuccess.find((action: any) => action.handler === 'navigate');

    expect(apiCall.target).toBe('/api/modules/glitter-workflow/admin/workflows');
    expect(apiCall.params.method).toBe('POST');
    expect(successNavigation.params.path).toBe('/admin/glitter-workflow/workflows/{{response.data.uuid}}');
    expect(successNavigation.params).not.toHaveProperty('replace', true);
    expect(json).toContain('"condition_tree"');
    expect(json).toContain('"action_plan"');
    expect(json).not.toContain('is_active');
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
