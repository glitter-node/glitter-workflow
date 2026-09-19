import { describe, expect, it } from 'vitest';
import { createLayoutTest } from '@core/template-engine/__tests__/utils/layoutTestUtils';
import type { LayoutJson } from '@core/template-engine/__tests__/utils/layoutTestUtils';
import layoutJson from '../../../layouts/admin/admin_execution_list.json';
import { assertFoundationStructure, registry, translated, translations } from './foundationLayoutSupport';

const layout = layoutJson as LayoutJson;

describe('admin_execution_list', () => {
    it('preserves the admin inheritance and component structure', () => {
        assertFoundationStructure(layout, 'admin_execution_list', ['executions']);
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

it('uses the filtered read-only paginated execution datasource and empty state', () => {
    const json = JSON.stringify(layout);
    expect(json).toContain('/api/modules/glitter-workflow/admin/executions');
    expect(json).toContain('execution_status_select');
    expect(json).toContain('execution_trigger_select');
    expect(json).toContain('"status": "{{$event.target.value}}"');
    expect(json).toContain('"trigger_key": "{{$event.target.value}}"');
    expect(json).toContain('"page": "1"');
    expect(json).toContain('"mergeQuery": true');
    expect(json).toContain('serverSidePagination');
    expect(json).toContain('EmptyState');
    expect(json).toContain('onRowClick');
    const fallback = (layout.data_sources?.[0] as any).fallback.data;
    expect(fallback.data).toEqual([]);
    expect(fallback.pagination).toMatchObject({ current_page: 1, per_page: 20, has_more_pages: false });
    expect(json).not.toContain('"method":"POST"');
    expect(json).not.toContain('retry');
    expect(json).not.toContain('cancel');
});

it('renders UUID and created-at traceability columns without changing existing columns', () => {
    const content = (layout as any).slots.content[0];
    const grid = content.children.find((child: any) => child.id === 'executions_data_grid');

    expect(grid.props.columns.map((column: any) => column.field)).toEqual([
        'uuid',
        'workflow_name',
        'trigger_key',
        'status',
        'created_at',
        'started_at',
        'finished_at',
    ]);
    expect(grid.props.columns[0].header).toBe('$t:glitter-workflow.admin.executions.columns.uuid');
    expect(grid.props.columns[4].header).toBe('$t:glitter-workflow.admin.executions.columns.created');
    expect(grid.props.idField).toBe('uuid');
});
