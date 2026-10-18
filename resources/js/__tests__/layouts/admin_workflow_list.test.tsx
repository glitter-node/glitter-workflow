import { describe, expect, it } from 'vitest';
import { createLayoutTest } from '@core/template-engine/__tests__/utils/layoutTestUtils';
import type { LayoutJson } from '@core/template-engine/__tests__/utils/layoutTestUtils';
import layoutJson from '../../../layouts/admin/admin_workflow_list.json';
import { assertFoundationStructure, registry, translated, translations } from './foundationLayoutSupport';

const layout = layoutJson as LayoutJson;

describe('admin_workflow_list', () => {
    it('preserves the admin inheritance and component structure', () => {
        assertFoundationStructure(layout, 'admin_workflow_list', ['workflows']);
    });

    it('binds the workflow API, permission-gated create actions, and server pagination', () => {
        const source = layout.data_sources?.[0] as any;
        expect(source).toMatchObject({
            id: 'workflows',
            endpoint: '/api/modules/glitter-workflow/admin/workflows',
            method: 'GET',
            auto_fetch: true,
            auth_required: true,
            refetchOnMount: true,
            loading_strategy: 'progressive',
        });
        expect(source.errorHandling['403'].handler).toBe('showErrorPage');
        expect(source.errorHandling.default.handler).toBe('showErrorPage');

        const content = layout.slots!.content![0] as any;
        const header = content.children[0];
        const headerCreate = header.children[0];
        expect(headerCreate.if).toBe('{{workflows?.data?.abilities?.can_create === true}}');
        expect(headerCreate.actions[0].params.path).toBe('/admin/glitter-workflow/workflows/create');

        const filters = content.children[1];
        expect(filters.id).toBe('workflow_filters');
        const statusSelect = filters.children[0].children[1];
        const triggerSelect = filters.children[1].children[1];
        expect(statusSelect.name).toBe('Select');
        expect(statusSelect.props.value).toBe("{{query.status ?? ''}}");
        expect(statusSelect.props.options.map((option: any) => option.value)).toEqual(['', 'active', 'inactive']);
        expect(triggerSelect.name).toBe('Select');
        expect(triggerSelect.props.value).toBe("{{query.trigger_key ?? ''}}");
        expect(triggerSelect.props.options.map((option: any) => option.value)).toEqual([
            '',
            'user.registered',
            'user.updated',
            'board.post.created',
            'board.comment.created',
        ]);
        expect(statusSelect.actions[0]).toMatchObject({
            handler: 'navigate',
            params: { replace: true, query: { page: '1' } },
        });
        expect(triggerSelect.actions[0]).toMatchObject({
            handler: 'navigate',
            params: { replace: true, query: { page: '1' } },
        });
        expect(statusSelect.actions[0].params.query.trigger_key).toContain('query.trigger_key');
        expect(triggerSelect.actions[0].params.query.status).toContain('query.status');

        const empty = content.children[2];
        expect(empty.if).toBe('{{(workflows?.data?.data ?? []).length === 0}}');
        expect(empty.children[1].if).toBe('{{workflows?.data?.abilities?.can_create === true}}');

        const grid = content.children[3];
        expect(grid.if).toBe('{{(workflows?.data?.data ?? []).length > 0}}');
        expect(grid.props.columns.map((column: any) => column.field)).toEqual([
            'name',
            'trigger_key',
            'is_active',
        ]);
        expect(grid.props.serverSidePagination).toBe(true);
        expect(grid.props.serverCurrentPage).toContain('pagination?.current_page');
        expect(grid.props.serverTotalPages).toContain('pagination?.last_page');
        expect(grid.props.serverHasMorePages).toContain('pagination?.has_more_pages');
        expect(grid.actions[0].params.path).toBe('/admin/glitter-workflow/workflows/{{$args[0].uuid}}');
        expect(grid.actions[1].params).toMatchObject({
            path: '/admin/glitter-workflow/workflows',
            mergeQuery: true,
            replace: true,
        });
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
