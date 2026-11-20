import { createInertiaApp } from '@inertiajs/vue3';
import assert from 'node:assert/strict';
import { createSSRApp, defineComponent, h } from 'vue';
import { renderToString } from 'vue/server-renderer';
import FeatureAction from '../../resources/js/components/FeatureAction.vue';
import { useFeatures } from '../../resources/js/composables/useFeatures';

export async function run() {
    for (const state of ['enabled', 'maintenance', 'hidden', 'invalid', undefined]) {
        const Page = defineComponent({
            setup() {
                const { filterNavigation } = useFeatures();
                return () =>
                    h('main', [
                        h(
                            'nav',
                            filterNavigation([{ title: 'Plan', href: '/trips/create', feature: 'plan' }]).map((item) =>
                                h('a', { href: item.href }, item.title),
                            ),
                        ),
                        h(FeatureAction, { feature: 'plan', href: '/trips/create' }, () => 'Create'),
                        h(FeatureAction, { feature: 'plan', disabled: true, href: '/trips/create' }, () => 'Busy action'),
                        h(FeatureAction, { feature: 'plan', type: 'submit' }, () => 'Save'),
                    ]);
            },
        });
        const result = await createInertiaApp({
            page: { component: 'Test', url: '/', version: null, props: { features: { plan: state } } },
            render: renderToString,
            resolve: () => Page,
            setup: ({ App, props, plugin }) => createSSRApp({ render: () => h(App, props) }).use(plugin),
        });
        const html = result.body.replace(/<!--[\s\S]*?-->/g, '');
        assert.match(html, /Create/);
        assert.match(html, /<button[^>]*disabled[^>]*>Busy action<\/button>/);
        if (state === 'enabled') {
            assert.match(html, /<a[^>]*href="\/trips\/create"[^>]*>Create<\/a>/);
            assert.match(html, /<button[^>]*type="submit"[^>]*>Save<\/button>/);
        } else {
            assert.match(html, /<button[^>]*disabled[^>]*>Create<\/button>/);
            assert.match(html, /<button[^>]*disabled[^>]*>Save<\/button>/);
            assert.match(html, /Temporarily unavailable/);
            assert.doesNotMatch(html, /<button[^>]*href=/);
        }
        if (state === 'enabled' || state === 'maintenance') {
            assert.match(html, /<nav><a href="\/trips\/create">Plan<\/a><\/nav>/);
        } else {
            assert.match(html, /<nav><\/nav>/);
        }
    }
    console.log('Feature actions and navigation: 5 states passed (SSR component rendering).');
}
