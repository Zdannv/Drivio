<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
export default { layout: AuthenticatedLayout };
</script>

<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted, nextTick } from 'vue';
import axios from 'axios';
import CameraCapture from '@/Components/CameraCapture.vue';

// Import Leaflet directly for Admin mapping
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const user = usePage().props.auth.user;

/* ----------------------------------
   DRIVER STATE & LOGIC
----------------------------------- */
const deliveries = ref([]);
const showCameraModal = ref(false);
const currentAction = ref(null);
const selectedDeliveryId = ref(null);

const fetchDeliveries = async () => {
    try {
        const response = await axios.get('/deliveries');
        deliveries.value = response.data;
    } catch (error) {
        console.error("Failed fetching deliveries", error);
    }
};

const openCamera = (actionType, deliveryId = null) => {
    currentAction.value = actionType;
    selectedDeliveryId.value = deliveryId;
    showCameraModal.value = true;
};

const handleCapture = async (payload) => {
    try {
        const response = await axios.post('/attendance/store', {
            type: currentAction.value,
            latitude: payload.latitude,
            longitude: payload.longitude,
            image: payload.image,
            delivery_id: selectedDeliveryId.value
        });

        if (response.data.status === 'success') {
            const similarity = (response.data.similarity_score * 100).toFixed(1);
            alert(`✅ ${response.data.message}\nSimilarity: ${similarity}%`);
            
            if (currentAction.value === 'proof_of_delivery' && selectedDeliveryId.value) {
                await updateDeliveryStatus(selectedDeliveryId.value, 'completed');
            }
        } else {
            alert(`❌ Error: ${response.data.message}`);
        }
    } catch (error) {
        alert(error.response?.data?.message || 'Verification failed');
    } finally {
        showCameraModal.value = false;
        fetchDeliveries();
    }
};

const updateDeliveryStatus = async (id, status) => {
    try {
        await axios.patch(`/deliveries/${id}/status`, { status });
        alert(`Delivery status updated to ${status.replace('_', ' ')}!`);
        fetchDeliveries();
    } catch (error) {
        alert("Failed to update status.");
    }
};

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


/* ----------------------------------
   LIFECYCLE HOOKS
----------------------------------- */
onMounted(() => {
    if (user.role === 'driver') {
        fetchDeliveries();
    } else if (user.role === 'admin') {
        nextTick(() => {
            initializeMapAndStartTracking();
        });
    }
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
      
      <!-- ADMIN VIEW -->
      <div v-if="user.role === 'admin'" class="flex flex-col h-auto">
         <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-white mb-2">Live Map Dashboard</h1>
         <p class="text-gray-500 mb-6 font-medium">Real-time driver positioning overview.</p>
         
         <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl overflow-hidden border border-gray-200 dark:border-slate-700">
             <!-- The Leaflet Hook -->
             <div id="map" class="h-[600px] w-full z-0 relative isolate"></div>
         </div>
      </div>

      <!-- DRIVER VIEW -->
      <div v-else>
        <!-- Header Profile Mobile -->
        <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-white mb-8">
            Hello, {{ user.name }}
        </h1>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
            
            <!-- Left/Top: General Actions -->
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 p-6">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-6">Daily Attendance</h3>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <button 
                            @click="openCamera('check_in')"
                            class="flex flex-col items-center justify-center p-5 rounded-xl bg-primary-50 hover:bg-primary-100 dark:bg-primary-900/20 dark:hover:bg-primary-900/40 border border-primary-100 dark:border-primary-800 transition shadow-sm group">
                            <div class="bg-primary-500 text-white p-3 rounded-full mb-3 shadow-md group-active:scale-95 transition-transform">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                                </svg>
                            </div>
                            <span class="font-bold text-primary-700 dark:text-primary-400 text-sm">Check In</span>
                        </button>

                        <button 
                            @click="openCamera('check_out')"
                            class="flex flex-col items-center justify-center p-5 rounded-xl bg-rose-50 hover:bg-rose-100 dark:bg-rose-900/20 dark:hover:bg-rose-900/40 border border-rose-100 dark:border-rose-800 transition shadow-sm group">
                            <div class="bg-rose-500 text-white p-3 rounded-full mb-3 shadow-md group-active:scale-95 transition-transform">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                            </div>
                            <span class="font-bold text-rose-700 dark:text-rose-400 text-sm">Check Out</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right/Bottom: Task List -->
            <div class="lg:col-span-2">
                <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden">
                    <div class="px-6 py-5 border-b border-gray-100 dark:border-slate-700 flex justify-between items-center bg-gray-50/50 dark:bg-slate-800/50">
                        <h3 class="text-lg font-bold text-gray-800 dark:text-white">Active Deliveries</h3>
                        <span class="bg-primary-100 text-primary-700 dark:bg-primary-900/50 dark:text-primary-400 py-1 px-3 rounded-full text-xs font-bold">{{ deliveries.length }} tasks</span>
                    </div>
                    
                    <div class="divide-y divide-gray-100 dark:divide-slate-700 max-h-[600px] overflow-y-auto">
                        <div v-if="deliveries.length === 0" class="p-8 text-center text-gray-500 dark:text-gray-400">
                            No active deliveries assigned to you at the moment.
                        </div>

                        <div v-for="delivery in deliveries" :key="delivery.id" class="p-6 transition hover:bg-gray-50 dark:hover:bg-slate-700/30 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            
                            <!-- Delivery Info -->
                            <div class="flex items-start gap-4">
                                <div class="mt-1">
                                    <div v-if="delivery.status === 'pending'" class="w-3 h-3 rounded-full bg-amber-400 ring-4 ring-amber-100 dark:ring-amber-900/30"></div>
                                    <div v-else-if="delivery.status === 'on_way'" class="w-3 h-3 rounded-full bg-blue-500 ring-4 ring-blue-100 dark:ring-blue-900/30"></div>
                                    <div v-else class="w-3 h-3 rounded-full bg-emerald-500 ring-4 ring-emerald-100 dark:ring-emerald-900/30"></div>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-gray-800 dark:text-slate-200 uppercase tracking-wide">Delivery #{{ delivery.id }}</p>
                                    <h4 class="text-gray-900 dark:text-white font-medium text-lg leading-tight mt-1">{{ delivery.destination_address }}</h4>
                                    <div class="mt-2 flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        {{ delivery.destination_lat }}, {{ delivery.destination_lng }}
                                    </div>
                                    <div class="mt-2 text-xs font-semibold px-2 py-1 inline-block rounded-md uppercase"
                                         :class="{
                                            'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400': delivery.status === 'pending',
                                            'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400': delivery.status === 'on_way',
                                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400': delivery.status === 'completed'
                                         }">
                                        {{ delivery.status.replace('_', ' ') }}
                                    </div>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="shrink-0 mt-4 sm:mt-0 flex">
                                <button v-if="delivery.status === 'pending'" @click="updateDeliveryStatus(delivery.id, 'on_way')" class="w-full sm:w-auto px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-semibold rounded-lg shadow-sm transition active:scale-95 text-sm">
                                    Start Delivery
                                </button>
                                
                                <button v-else-if="delivery.status === 'on_way'" @click="openCamera('proof_of_delivery', delivery.id)" class="w-full sm:w-auto px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold rounded-lg shadow-sm transition active:scale-95 text-sm flex items-center justify-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                      <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    Finish & Verify
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
      </div>
    </div>
  </div>

  <CameraCapture 
    v-if="showCameraModal" 
    @close="showCameraModal = false" 
    @capture="handleCapture"
  />

</template>