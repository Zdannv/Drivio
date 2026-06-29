import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import Datepicker from '@vuepic/vue-datepicker'
import '@vuepic/vue-datepicker/dist/main.css';
import { registerSW } from 'virtual:pwa-register';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

if ('serviceWorker' in navigator) {
    registerSW({ immediate: true });
}

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .component('Datepicker', Datepicker);

        const link = document.createElement('link');
        link.rel = 'icon';
        link.href = '/icon_kndi.svg';
        link.type = 'image/svg';
        document.head.appendChild(link);

        return app.mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
