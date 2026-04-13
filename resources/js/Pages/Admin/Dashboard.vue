<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
export default { layout: AuthenticatedLayout };
</script>

<script setup>
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import axios from 'axios';

// Import Leaflet directly for Admin mapping
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

import { UsersIcon, CheckCircleIcon, ExclamationCircleIcon } from '@heroicons/vue/24/outline';

const stats = ref([
    { title: 'Total Drivers', value: '-', icon: UsersIcon, color: 'text-blue-500', bg: 'bg-blue-100' },
    { title: 'Active Today', value: '-', icon: CheckCircleIcon, color: 'text-emerald-500', bg: 'bg-emerald-100' },
    { title: 'Pending Verification', value: '-', icon: ExclamationCircleIcon, color: 'text-amber-500', bg: 'bg-amber-100' },
]);

/* ----------------------------------
   ADMIN LIVE MAP STATE & LOGIC
----------------------------------- */
const map = ref(null);
const markers = ref({});
let pollInterval = null;

// Custom icon avoids Vite 404 image resolving issues
const customIcon = L.divIcon({
    className: 'custom-leaflet-icon',
    html: `<div style="background-color: #3b82f6; width: 16px; height: 16px; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 6px rgba(0,0,0,0.5);"></div>`,
    iconSize: [20, 20],
    iconAnchor: [10, 10]
});

const initializeMapAndStartTracking = async () => {
    // 1. Init Leaflet
    map.value = L.map('map').setView([-7.2504, 112.7688], 12);
    
    // 2. Attach Map Tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map.value);

    // 3. Kick off loop
    await fetchTracking();
    pollInterval = setInterval(fetchTracking, 10000);
};

const fetchTracking = async () => {
    try {
        const response = await axios.get('/tracking/latest');
        const drivers = response.data;
        
        drivers.forEach(d => {
            if (d.tracking_logs && d.tracking_logs.length > 0) {
                const log = d.tracking_logs[0];
                const latLng = [log.latitude, log.longitude];
                
                if (markers.value[d.id]) {
                    // Marker exists, smoothly update position
                    markers.value[d.id].setLatLng(latLng);
                } else {
                    // Create new marker
                    const marker = L.marker(latLng, { icon: customIcon })
                        .bindPopup(`<strong class="text-gray-800">${d.name}</strong><br><span class="text-xs text-gray-500 font-semibold px-2 py-0.5 bg-gray-100 rounded-full mt-1 inline-block">DRIVER</span>`)
                        .addTo(map.value);
                    markers.value[d.id] = marker;
                }
            }
        });
    } catch (error) {
        console.error('Error fetching tracking overlay data:', error);
    }
};

onMounted(() => {
    nextTick(() => {
        initializeMapAndStartTracking();
    });
});

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
    if (map.value) map.value.remove();
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="w-full min-h-[calc(100vh-4rem)] bg-gray-50 dark:bg-slate-900 py-6 sm:py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 text-gray-800 dark:text-gray-200">
            
            <div class="flex flex-col h-auto">
                <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-white mb-2">Admin Dashboard</h1>
                <p class="text-gray-500 mb-6 font-medium">Overview of driver activities and management.</p>
                
                <!-- Overview Cards -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div 
                        v-for="(stat, index) in stats" :key="index" 
                        class="bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-sm border border-slate-100 dark:border-slate-700 flex items-center justify-between"
                    >
                        <div>
                            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ stat.title }}</p>
                            <h3 class="text-3xl font-extrabold text-slate-800 dark:text-white mt-2">{{ stat.value }}</h3>
                        </div>
                        <div :class="[stat.bg, stat.color]" class="p-4 rounded-xl">
                            <component :is="stat.icon" class="w-8 h-8" />
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl overflow-hidden border border-gray-200 dark:border-slate-700">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-700 flex justify-between items-center bg-gray-50/50 dark:bg-slate-800/50">
                        <h3 class="font-bold text-gray-800 dark:text-white">Live Map Tracking</h3>
                        <span class="bg-primary-100 text-primary-700 dark:bg-primary-900/50 dark:text-primary-400 py-1 px-3 rounded-full text-xs font-bold">Auto-updates every 10s</span>
                    </div>
                    <!-- The Leaflet Hook -->
                    <div id="map" class="h-[600px] w-full z-0 relative isolate"></div>
                </div>
            </div>

        </div>
    </div>
</template>
