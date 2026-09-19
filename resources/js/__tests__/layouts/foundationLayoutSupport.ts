import { expect } from 'vitest';
import { createMockComponentRegistry } from '@core/template-engine/__tests__/utils/layoutTestUtils';
import { ComponentRegistry } from '@core/template-engine/ComponentRegistry';
import type { LayoutJson } from '@core/template-engine/__tests__/utils/layoutTestUtils';
import { Div } from '@admin/components/basic/Div';
import { Button } from '@admin/components/basic/Button';
import { Icon } from '@admin/components/basic/Icon';
import { Span } from '@admin/components/basic/Span';
import { DataGrid } from '@admin/components/composite/DataGrid';
import { EmptyState } from '@admin/components/composite/EmptyState';
import { PageHeader } from '@admin/components/composite/PageHeader';
import baseLayout from '@adminLayouts/_admin_base.json';
import en from '../../../lang/en.json';
import ko from '../../../lang/ko.json';

export const translations = { en, ko };

export function registry(): ComponentRegistry {
    const components = createMockComponentRegistry();
    components.register('basic', 'Div', Div);
    components.register('basic', 'Button', Button);
    components.register('basic', 'Icon', Icon);
    components.register('basic', 'Span', Span);
    components.register('composite', 'DataGrid', DataGrid);
    components.register('composite', 'EmptyState', EmptyState);
    components.register('composite', 'PageHeader', PageHeader);
    return components as unknown as ComponentRegistry;
}

export function translated(key: string, locale: 'en' | 'ko'): string {
    expect(key).toMatch(/^\$t:glitter-workflow\./);
    const path = key.slice('$t:glitter-workflow.'.length).split('.');
    const value = path.reduce<any>((current, part) => current?.[part], translations[locale]);
    expect(typeof value).toBe('string');
    expect(value.length).toBeGreaterThan(0);
    return value;
}

export function assertFoundationStructure(
    layout: LayoutJson,
    name: string,
    expectedDataSourceIds: string[] = [],
): void {
    expect(layout.layout_name).toBe(name);
    expect(layout.version).toBe('1.0.0');
    expect(layout.extends).toBe(baseLayout.layout_name);
    expect((layout.data_sources ?? []).map((source: any) => source.id)).toEqual(expectedDataSourceIds);
    expect(Object.keys(layout.slots ?? {})).toEqual(['content']);
    expect(layout.slots?.content).toHaveLength(1);

    function hasContentSlot(node: any): boolean {
        if (Array.isArray(node)) return node.some(hasContentSlot);
        if (!node || typeof node !== 'object') return false;
        return node.slot === 'content' || Object.values(node).some(hasContentSlot);
    }
    expect(hasContentSlot(baseLayout.components)).toBe(true);

    const components = registry();
    function validate(nodes: any[]): void {
        for (const node of nodes) {
            expect(typeof node.id).toBe('string');
            expect(components.hasComponent(node.name)).toBe(true);
            expect(components.getMetadata(node.name)?.type).toBe(node.type);
            if (node.children) {
                expect(Array.isArray(node.children)).toBe(true);
                validate(node.children);
            }
        }
    }
    validate(layout.slots!.content!);
    const header = layout.slots!.content![0].children![0] as any;
    expect(header.name).toBe('PageHeader');
    expect(header.props.title).toBe(layout.meta?.title);
    expect(header.props.description).toBe(layout.meta?.description);
}
