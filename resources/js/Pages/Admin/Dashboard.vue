<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
export default { layout: AuthenticatedLayout };
</script>

<script setup>
import { Head } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, nextTick, computed } from 'vue';
import axios from 'axios';

// Import Leaflet directly for Admin mapping
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

import { UsersIcon, CheckCircleIcon, ExclamationCircleIcon, TruckIcon, ClockIcon, CalendarIcon } from '@heroicons/vue/24/outline';

const props = defineProps({
    analytics: Object
});

// Period selector for analytics
const selectedPeriod = ref('today');

// Computed stats based on selected period
const stats = computed(() => [
    { title: 'Total Drivers', value: props.analytics.overview.total_drivers, icon: UsersIcon, color: 'text-blue-500', bg: 'bg-blue-100' },
    { title: 'Active Today', value: props.analytics.overview.active_today, icon: CheckCircleIcon, color: 'text-emerald-500', bg: 'bg-emerald-100' },
    { title: 'Pending Verification', value: props.analytics.overview.pending_verification, icon: ExclamationCircleIcon, color: 'text-amber-500', bg: 'bg-amber-100' },
]);

const deliveryStats = computed(() => {
    const period = selectedPeriod.value;
    return {
        total: props.analytics.deliveries[period],
        completed: props.analytics.completed[period],
        completion_rate: props.analytics.deliveries[period] > 0 
            ? Math.round((props.analytics.completed[period] / props.analytics.deliveries[period]) * 100) 
            : 0
    };
});

const getStatusBadge = (status) => {
    const badges = {
        available: { text: '✓ Available', class: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' },
        busy: { text: '🚚 On Delivery', class: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400' },
        offline: { text: '⚪ Offline', class: 'bg-gray-100 text-gray-600 dark:bg-gray-900/30 dark:text-gray-400' }
    };
    return badges[status] || badges.offline;
};

/* ----------------------------------
   ADMIN LIVE MAP STATE & LOGIC
----------------------------------- */
const map = ref(null);
const markers = ref({});
let pollInterval = null;

// Dynamic icons for active and idle drivers
const createActiveIcon = () => L.divIcon({
    className: 'custom-leaflet-icon',
    html: `<div style="background-color: #3b82f6; width: 16px; height: 16px; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 6px rgba(0,0,0,0.5); animation: pulse 2s infinite;"></div>
           <style>
           @keyframes pulse {
               0%, 100% { transform: scale(1); opacity: 1; }
               50% { transform: scale(1.1); opacity: 0.8; }
           }
           </style>`,
    iconSize: [20, 20],
    iconAnchor: [10, 10]
});

const createIdleIcon = () => L.divIcon({
    className: 'custom-leaflet-icon',
    html: `<div style="background-color: #ef4444; width: 16px; height: 16px; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 6px rgba(0,0,0,0.5); animation: pulse 2s infinite;"></div>
           <style>
           @keyframes pulse {
               0%, 100% { transform: scale(1); opacity: 1; }
               50% { transform: scale(1.1); opacity: 0.8; }
           }
           </style>`,
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
                
                // Determine icon based on idle status
                const icon = d.is_idle ? createIdleIcon() : createActiveIcon();
                
                // Create popup content based on idle status
                let popupContent = `<div style="min-width: 200px;">
                    <strong class="text-gray-800" style="font-size: 14px;">${d.name}</strong><br>`;
                
                if (d.is_idle) {
                    // Idle driver - red badge with warning
                    popupContent += `<span class="text-xs font-semibold px-2 py-1 bg-red-100 text-red-700 rounded-full mt-1 inline-block">🔴 IDLE</span>`;
                    
                    // Add metadata warning
                    if (d.idle_distance_meters !== null && d.minutes_since_last_log !== null) {
                        const distance = d.idle_distance_meters.toFixed(1);
                        const minutes = Math.round(d.minutes_since_last_log);
                        
                        popupContent += `<div style="margin-top: 8px; padding: 8px; background-color: #fef2f2; border-left: 3px solid #ef4444; border-radius: 4px;">
                            <p style="font-size: 11px; color: #991b1b; margin: 0;">
                                ⚠️ Only moved <strong>${distance}m</strong> in the last <strong>${minutes} min</strong>
                            </p>
                        </div>`;
                    }
                } else {
                    // Active driver - green badge
                    popupContent += `<span class="text-xs font-semibold px-2 py-1 bg-green-100 text-green-700 rounded-full mt-1 inline-block">🟢 ACTIVE</span>`;
                    
                    // Show movement info if available
                    if (d.idle_distance_meters !== null && d.minutes_since_last_log !== null) {
                        const distance = d.idle_distance_meters.toFixed(1);
                        const minutes = Math.round(d.minutes_since_last_log);
                        
                        popupContent += `<div style="margin-top: 8px; font-size: 11px; color: #6b7280;">
                            <p style="margin: 0;">Moved: ${distance}m</p>
                            <p style="margin: 0;">Last update: ${minutes} min ago</p>
                        </div>`;
                    }
                }
                
                popupContent += `</div>`;
                
                if (markers.value[d.id]) {
                    // Marker exists, update position and icon
                    markers.value[d.id].setLatLng(latLng);
                    markers.value[d.id].setIcon(icon);
                    markers.value[d.id].setPopupContent(popupContent);
                } else {
                    // Create new marker
                    const marker = L.marker(latLng, { icon: icon })
                        .bindPopup(popupContent)
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

                <!-- Analytics Section -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Delivery Analytics -->
                    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 p-6">
                        <div class="flex items-center justify-between mb-4">
                            <h3 class="font-bold text-gray-800 dark:text-white text-lg">Delivery Analytics</h3>
                            <select v-model="selectedPeriod" class="text-sm border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white rounded-lg px-3 py-1.5 focus:ring-2 focus:ring-blue-500 outline-none">
                                <option value="today">Today</option>
                                <option value="this_week">This Week</option>
                                <option value="this_month">This Month</option>
                            </select>
                        </div>
                        
                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-4 bg-blue-50 dark:bg-blue-900/20 rounded-xl">
                                <div class="flex items-center gap-3">
                                    <div class="p-3 bg-blue-100 dark:bg-blue-900/40 rounded-lg">
                                        <TruckIcon class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Total Deliveries</p>
                                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ deliveryStats.total }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between p-4 bg-green-50 dark:bg-green-900/20 rounded-xl">
                                <div class="flex items-center gap-3">
                                    <div class="p-3 bg-green-100 dark:bg-green-900/40 rounded-lg">
                                        <CheckCircleIcon class="w-6 h-6 text-green-600 dark:text-green-400" />
                                    </div>
                                    <div>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Completed</p>
                                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ deliveryStats.completed }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="p-4 bg-gradient-to-r from-purple-50 to-pink-50 dark:from-purple-900/20 dark:to-pink-900/20 rounded-xl">
                                <div class="flex items-center justify-between mb-2">
                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Completion Rate</p>
                                    <p class="text-xl font-bold text-purple-600 dark:text-purple-400">{{ deliveryStats.completion_rate }}%</p>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2.5">
                                    <div class="bg-gradient-to-r from-purple-500 to-pink-500 h-2.5 rounded-full transition-all duration-500" :style="{ width: deliveryStats.completion_rate + '%' }"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Top Performers Today -->
                    <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 p-6">
                        <div class="flex items-center gap-2 mb-4">
                            <h3 class="font-bold text-gray-800 dark:text-white text-lg">🏆 Top Performers Today</h3>
                        </div>
                        
                        <div v-if="analytics.top_performers_today.length > 0" class="space-y-3">
                            <div v-for="(driver, index) in analytics.top_performers_today" :key="driver.id" 
                                class="flex items-center justify-between p-3 bg-gray-50 dark:bg-slate-700/50 rounded-xl hover:bg-gray-100 dark:hover:bg-slate-700 transition">
                                <div class="flex items-center gap-3">
                                    <div class="relative">
                                        <div v-if="index === 0" class="absolute -top-1 -right-1 w-5 h-5 bg-yellow-400 rounded-full flex items-center justify-center text-xs">🥇</div>
                                        <div v-else-if="index === 1" class="absolute -top-1 -right-1 w-5 h-5 bg-gray-300 rounded-full flex items-center justify-center text-xs">🥈</div>
                                        <div v-else-if="index === 2" class="absolute -top-1 -right-1 w-5 h-5 bg-orange-400 rounded-full flex items-center justify-center text-xs">🥉</div>
                                        <img v-if="driver.avatar" :src="driver.avatar" :alt="driver.name" class="w-10 h-10 rounded-full object-cover border-2 border-white dark:border-slate-600">
                                        <div v-else class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-400 to-purple-500 flex items-center justify-center text-white font-bold border-2 border-white dark:border-slate-600">
                                            {{ driver.name.charAt(0).toUpperCase() }}
                                        </div>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-900 dark:text-white">{{ driver.name }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Driver</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ driver.completed_count }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">deliveries</p>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-8 text-gray-500 dark:text-gray-400">
                            <p class="text-sm">No completed deliveries today yet</p>
                        </div>
                    </div>
                </div>

                <!-- Driver Status Table -->
                <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 mb-8 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 dark:border-slate-700 bg-gray-50/50 dark:bg-slate-800/50">
                        <h3 class="font-bold text-gray-800 dark:text-white">Driver Status Overview</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Real-time status of all drivers</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-slate-700">
                            <thead class="bg-gray-50 dark:bg-slate-900">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Driver</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Active Deliveries</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">GPS Tracking</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-slate-800 divide-y divide-gray-200 dark:divide-slate-700">
                                <tr v-for="driver in analytics.driver_status" :key="driver.id" class="hover:bg-gray-50 dark:hover:bg-slate-700/50 transition">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-3">
                                            <img v-if="driver.avatar" :src="driver.avatar" :alt="driver.name" class="w-10 h-10 rounded-full object-cover">
                                            <div v-else class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-400 to-purple-500 flex items-center justify-center text-white font-bold">
                                                {{ driver.name.charAt(0).toUpperCase() }}
                                            </div>
                                            <div>
                                                <p class="font-medium text-gray-900 dark:text-white">{{ driver.name }}</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">ID: {{ driver.id }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-3 py-1 text-xs font-semibold rounded-full" :class="getStatusBadge(driver.status).class">
                                            {{ getStatusBadge(driver.status).text }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span v-if="driver.active_deliveries > 0" class="text-orange-600 dark:text-orange-400 font-semibold">
                                            {{ driver.active_deliveries }} active
                                        </span>
                                        <span v-else class="text-gray-400 dark:text-gray-500">-</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span v-if="driver.has_tracking" class="text-green-600 dark:text-green-400 text-sm">● Active</span>
                                        <span v-else class="text-gray-400 dark:text-gray-500 text-sm">○ No data</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Live Map Tracking -->
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
