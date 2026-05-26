<script setup>
import { ref, nextTick, onUnmounted, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import moment from 'moment';
import axios from 'axios';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

defineProps({
    deliveries: Object,
    drivers: Array,
});

// Tab state for filtering deliveries
const activeTab = ref('active'); // 'active' or 'completed'

const showModal = ref(false);

const form = useForm({
    driver_id: '',
    destination_address: '',
    destination_lat: '',
    destination_lng: '',
    items: '',
});

// Detail Modal State
const showDetailModal = ref(false);
const detailData = ref(null);
const isLoadingDetail = ref(false);
let detailMap = null;

// Map & Geocoding State
let map = null;
let marker = null;
const searchResults = ref([]);
const isSearching = ref(false);
let searchTimeout = null;

// Driver tracking on map
const driverMarkers = ref({});
let trackingPollInterval = null;

// Custom Map Marker Icon for destination
const createIcon = () => {
    return L.divIcon({
        className: 'custom-div-icon',
        html: `<div style="background-color: #059669; width: 24px; height: 24px; border-radius: 50%; border: 3px solid white; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);"></div>`,
        iconSize: [24, 24],
        iconAnchor: [12, 12]
    });
};

// Driver tracking icons (green for free, orange for busy)
const createFreeIcon = () => L.divIcon({
    className: 'custom-leaflet-icon',
    html: `<div style="background-color: #10b981; width: 16px; height: 16px; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 6px rgba(0,0,0,0.5); animation: pulse 2s infinite;"></div>
           <style>
           @keyframes pulse {
               0%, 100% { transform: scale(1); opacity: 1; }
               50% { transform: scale(1.1); opacity: 0.8; }
           }
           </style>`,
    iconSize: [20, 20],
    iconAnchor: [10, 10]
});

const createBusyIcon = () => L.divIcon({
    className: 'custom-leaflet-icon',
    html: `<div style="background-color: #f97316; width: 16px; height: 16px; border-radius: 50%; border: 3px solid white; box-shadow: 0 0 6px rgba(0,0,0,0.5); animation: pulse 2s infinite;"></div>
           <style>
           @keyframes pulse {
               0%, 100% { transform: scale(1); opacity: 1; }
               50% { transform: scale(1.1); opacity: 0.8; }
           }
           </style>`,
    iconSize: [20, 20],
    iconAnchor: [10, 10]
});

const initMap = async () => {
    await nextTick(); 
    
    if (map) {
        map.remove();
    }
    
    const defaultLat = form.destination_lat || -7.2504;
    const defaultLng = form.destination_lng || 112.7688;

    map = L.map('modal-map', { zoomControl: false }).setView([defaultLat, defaultLng], 13);
    L.control.zoom({ position: 'bottomright' }).addTo(map);
    
    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);

    if (form.destination_lat && form.destination_lng) {
        marker = L.marker([form.destination_lat, form.destination_lng], { icon: createIcon() }).addTo(map);
    }

    // MAP CLICK LOGIC (Manual Pin Drop)
    map.on('click', async (e) => {
        const { lat, lng } = e.latlng;
        
        // 1. Update Marker & Form State instantly
        updateMarker(lat, lng, true); 
        
        // 2. Clear old text and show loading state
        form.destination_address = "Loading exact address...";
        searchResults.value = [];
        
        try {
            // Reverse Geocoding with fallback to Nominatim (More reliable for reverse)
            const response = await axios.get(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`);
            
            if (response.data && response.data.display_name) {
                // Formatting to make it cleaner (removing overly verbose country info if possible)
                let cleanAddress = response.data.display_name;
                const addressParts = cleanAddress.split(', ');
                if(addressParts.length > 5) {
                     cleanAddress = addressParts.slice(0, 5).join(', ');
                }
                form.destination_address = cleanAddress;
            } else {
                 form.destination_address = "Address not found at this location";
            }
        } catch (error) {
            console.error('Reverse geocoding failed:', error);
            form.destination_address = "Network error fetching address";
        }
    });

    setTimeout(() => {
        map.invalidateSize();
    }, 200);
    
    // Fetch and display driver tracking
    await fetchDriverTracking();
    trackingPollInterval = setInterval(fetchDriverTracking, 10000);
};

const fetchDriverTracking = async () => {
    try {
        const response = await axios.get('/tracking/latest');
        const drivers = response.data;
        
        drivers.forEach(d => {
            if (d.tracking_logs && d.tracking_logs.length > 0) {
                const log = d.tracking_logs[0];
                const latLng = [log.latitude, log.longitude];
                
                // Check if driver has active delivery (busy) or not (free)
                const isBusy = d.active_deliveries_count > 0;
                const icon = isBusy ? createBusyIcon() : createFreeIcon();
                
                // Create popup content based on driver status
                let popupContent = `<div style="min-width: 200px;">
                    <strong class="text-gray-800" style="font-size: 14px;">${d.name}</strong><br>`;
                
                if (isBusy) {
                    // Busy driver - orange badge
                    popupContent += `<span class="text-xs font-semibold px-2 py-1 bg-orange-100 text-orange-700 rounded-full mt-1 inline-block">🚚 ON DELIVERY</span>`;
                    
                    // Show active delivery count
                    popupContent += `<div style="margin-top: 8px; padding: 8px; background-color: #fff7ed; border-left: 3px solid #f97316; border-radius: 4px;">
                        <p style="font-size: 11px; color: #9a3412; margin: 0;">
                            📦 Active deliveries: <strong>${d.active_deliveries_count}</strong>
                        </p>
                    </div>`;
                } else {
                    // Free driver - green badge
                    popupContent += `<span class="text-xs font-semibold px-2 py-1 bg-green-100 text-green-700 rounded-full mt-1 inline-block">✓ AVAILABLE</span>`;
                    
                    popupContent += `<div style="margin-top: 8px; font-size: 11px; color: #6b7280;">
                        <p style="margin: 0;">Ready to accept new delivery</p>
                    </div>`;
                }
                
                popupContent += `</div>`;
                
                if (driverMarkers.value[d.id]) {
                    // Marker exists, update position and icon
                    driverMarkers.value[d.id].setLatLng(latLng);
                    driverMarkers.value[d.id].setIcon(icon);
                    driverMarkers.value[d.id].setPopupContent(popupContent);
                } else {
                    // Create new marker
                    const driverMarker = L.marker(latLng, { icon: icon })
                        .bindPopup(popupContent)
                        .addTo(map);
                    driverMarkers.value[d.id] = driverMarker;
                }
            }
        });
    } catch (error) {
        console.error('Error fetching driver tracking data:', error);
    }
};

// Modified updateMarker to handle smooth flying
const updateMarker = (lat, lng, smoothFly = false) => {
    form.destination_lat = lat.toFixed(6);
    form.destination_lng = lng.toFixed(6);

    if (marker) {
        marker.setLatLng([lat, lng]);
    } else {
        marker = L.marker([lat, lng], { icon: createIcon() }).addTo(map);
    }
    
    if(smoothFly && map) {
        // FlyTo provides a smooth zooming animation to the new point
        map.flyTo([lat, lng], 17, { duration: 1.5 });
    } else if (map) {
        map.panTo([lat, lng]);
    }
};

// Pelias/Geocode Earth Autocomplete (Significantly better than standard Nominatim for text search)
const onAddressInput = (event) => {
    const query = event.target.value;
    form.destination_address = query;
    
    if (searchTimeout) clearTimeout(searchTimeout);
    
    if (query.length < 4) {
        searchResults.value = [];
        return;
    }

    searchTimeout = setTimeout(async () => {
        isSearching.value = true;
        try {
            // Using Photon API by Komoot (Built on ElasticSearch & OSM). 
            // Much faster, no strict rate limits, and supports better fuzzy search than basic Nominatim
            const response = await axios.get(`https://photon.komoot.io/api/?q=${encodeURIComponent(query)}&limit=5`);
            
            if(response.data && response.data.features) {
                searchResults.value = response.data.features.map(f => {
                    // Reconstruct a readable address from the properties
                    const p = f.properties;
                    let nameArr = [];
                    if(p.name) nameArr.push(p.name);
                    if(p.street) nameArr.push(p.street);
                    if(p.city || p.town) nameArr.push(p.city || p.town);
                    if(p.state) nameArr.push(p.state);
                    
                    return {
                        id: p.osm_id,
                        display_name: nameArr.join(', '),
                        lat: f.geometry.coordinates[1],
                        lon: f.geometry.coordinates[0],
                        category: p.osm_value
                    };
                }).filter(r => r.display_name.length > 0);
            }
        } catch (error) {
            console.error('Forward geocoding failed:', error);
        } finally {
            isSearching.value = false;
        }
    }, 600); 
};

// Handle Selection from Dropdown
const selectResult = (result) => {
    form.destination_address = result.display_name;
    // Trigger map update AND smooth fly animation
    updateMarker(parseFloat(result.lat), parseFloat(result.lon), true);
    searchResults.value = [];
};

// Close dropdown when clicking outside
window.addEventListener('click', (e) => {
    if (!e.target.closest('.search-container')) {
        searchResults.value = [];
    }
});

const openModal = () => {
    showModal.value = true;
    initMap();
};

const closeModal = () => {
    showModal.value = false;
    form.reset();
    form.clearErrors();
    searchResults.value = [];
    
    // Clear tracking interval
    if (trackingPollInterval) {
        clearInterval(trackingPollInterval);
        trackingPollInterval = null;
    }
    
    // Clear driver markers
    Object.values(driverMarkers.value).forEach(marker => marker.remove());
    driverMarkers.value = {};
    
    if (map) {
        map.remove();
        map = null;
        marker = null;
    }
};

const submit = () => {
    form.post(route('deliveries.store'), {
        onSuccess: () => {
            closeModal();
            alert('Delivery created successfully!');
        },
    });
};

onUnmounted(() => {
    if (map) map.remove();
    if (detailMap) detailMap.remove();
});

const openDetail = async (deliveryId) => {
    showDetailModal.value = true;
    isLoadingDetail.value = true;
    detailData.value = null;
    
    try {
        const response = await axios.get(route('deliveries.show', deliveryId));
        detailData.value = response.data;
        isLoadingDetail.value = false; // Mark loading as false so DOM updates
        await nextTick(); // Wait for DOM to render detail-map
        initDetailMap();
    } catch (error) {
        console.error("Failed to fetch delivery detail", error);
        alert("Failed to load delivery details: " + (error.response?.data?.message || error.message));
        showDetailModal.value = false;
        isLoadingDetail.value = false;
    }
};

const closeDetail = () => {
    showDetailModal.value = false;
    if (detailMap) {
        detailMap.remove();
        detailMap = null;
    }
};

const initDetailMap = () => {
    if (!detailData.value || !detailData.value.delivery) return;
    
    const delivery = detailData.value.delivery;
    const dest = [parseFloat(delivery.destination_lat), parseFloat(delivery.destination_lng)];
    
    if (detailMap) {
        detailMap.remove();
    }

    detailMap = L.map('detail-map', { zoomControl: false }).setView(dest, 14);
    L.control.zoom({ position: 'bottomright' }).addTo(detailMap);
    
    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(detailMap);

    // Destination Marker
    const destIcon = L.divIcon({
        className: 'dest-icon',
        html: `<div style="background-color: #ef4444; width: 22px; height: 22px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.3);"></div>`,
        iconSize: [22, 22],
        iconAnchor: [11, 11]
    });
    L.marker(dest, { icon: destIcon }).addTo(detailMap).bindPopup("<b>Destination</b><br>" + delivery.destination_address);

    // Tracking Route
    const logs = delivery.tracking_logs || [];
    if (logs.length > 0) {
        const path = logs.map(log => [parseFloat(log.latitude), parseFloat(log.longitude)]);
        const polyline = L.polyline(path, { color: '#6366f1', weight: 4, opacity: 0.8 }).addTo(detailMap);
        
        // Start Marker (First Log)
        const startIcon = L.divIcon({
            className: 'start-icon',
            html: `<div style="background-color: #3b82f6; width: 14px; height: 14px; border-radius: 50%; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"></div>`,
            iconSize: [14, 14],
            iconAnchor: [7, 7]
        });
        L.marker(path[0], { icon: startIcon }).addTo(detailMap).bindPopup("Start Location");

        // Fit map to route
        detailMap.fitBounds(polyline.getBounds(), { padding: [40, 40] });
    }

    // Proof of Delivery Location Marker
    const pod = delivery.attendances?.find(a => a.type === 'proof_of_delivery');
    if (pod) {
        const podIcon = L.divIcon({
            className: 'pod-icon',
            html: `<div style="background-color: #10b981; width: 26px; height: 26px; border-radius: 50%; border: 3px solid white; box-shadow: 0 4px 6px rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center;"><svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg></div>`,
            iconSize: [26, 26],
            iconAnchor: [13, 13]
        });
        L.marker([parseFloat(pod.latitude), parseFloat(pod.longitude)], { icon: podIcon }).addTo(detailMap).bindPopup("<b>Verified Delivery</b><br>" + (pod.address || 'Location reached')).openPopup();
    }
};

const getTimelineStatus = (delivery) => {
    const steps = [];
    steps.push({ label: 'Created', date: delivery.created_at, icon: 'plus', color: 'bg-emerald-100 text-emerald-600' });
    
    if (delivery.started_at) {
        steps.push({ label: 'Started', date: delivery.started_at, icon: 'truck', color: 'bg-blue-100 text-blue-600' });
    }
    
    if (delivery.completed_at) {
        steps.push({ label: 'Completed', date: delivery.completed_at, icon: 'check', color: 'bg-emerald-600 text-white' });
    }
    
    return steps;
};
</script>

<template>
    <Head title="Manage Deliveries" />

    <AuthenticatedLayout>
        <div class="py-10 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
            <div class="sm:flex sm:items-center">
                <div class="sm:flex-auto">
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Deliveries</h1>
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-400">A complete list of all logistical deliveries across the system.</p>
                </div>
                <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
                    <button
                        @click="openModal"
                        type="button"
                        class="block rounded-md bg-emerald-600 px-4 py-2 text-center text-sm font-semibold text-white shadow-sm hover:bg-emerald-500 transition"
                    >
                        Create Delivery Task
                    </button>
                </div>
            </div>

            <!-- Tabs for Active / Completed -->
            <div class="mt-6 border-b border-gray-200 dark:border-slate-700">
                <nav class="-mb-px flex space-x-8">
                    <button
                        @click="activeTab = 'active'"
                        :class="[
                            activeTab === 'active'
                                ? 'border-emerald-500 text-emerald-600 dark:text-emerald-400'
                                : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300',
                            'whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium transition'
                        ]"
                    >
                        Active Deliveries
                        <span :class="[
                            activeTab === 'active' ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-gray-400',
                            'ml-2 py-0.5 px-2.5 rounded-full text-xs font-medium'
                        ]">
                            {{ deliveries.data.filter(d => d.status !== 'completed').length }}
                        </span>
                    </button>
                    <button
                        @click="activeTab = 'completed'"
                        :class="[
                            activeTab === 'completed'
                                ? 'border-emerald-500 text-emerald-600 dark:text-emerald-400'
                                : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300',
                            'whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium transition'
                        ]"
                    >
                        Completed
                        <span :class="[
                            activeTab === 'completed' ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-gray-400',
                            'ml-2 py-0.5 px-2.5 rounded-full text-xs font-medium'
                        ]">
                            {{ deliveries.data.filter(d => d.status === 'completed').length }}
                        </span>
                    </button>
                </nav>
            </div>

            <div class="mt-8 flow-root">
                <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                    <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                        <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 sm:rounded-lg">
                            <table class="min-w-full divide-y divide-gray-300 dark:divide-slate-700 bg-white dark:bg-slate-800">
                                <thead class="bg-gray-50 dark:bg-slate-900">
                                    <tr>
                                        <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 dark:text-white sm:pl-6">ID</th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Created At</th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Assigned Driver</th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Destination</th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">PoD Location</th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Status</th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900 dark:text-white">Proof Photo</th>
                                        <th scope="col" class="relative py-3.5 pl-3 pr-4 sm:pr-6">
                                            <span class="sr-only">Actions</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                                    <tr v-for="delivery in deliveries.data.filter(d => activeTab === 'active' ? d.status !== 'completed' : d.status === 'completed')" :key="delivery.id">
                                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 dark:text-white sm:pl-6">#{{ delivery.id }}</td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 dark:text-gray-400">{{ moment(delivery.created_at).format('DD MMM YYYY, HH:mm') }}</td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm font-medium text-indigo-600 dark:text-indigo-400">{{ delivery.driver?.name || 'Unassigned' }}</td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 dark:text-gray-400 max-w-[200px] truncate" :title="delivery.destination_address">{{ delivery.destination_address }}</td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            <div v-if="delivery.status === 'completed' && delivery.attendances && delivery.attendances.length > 0" class="truncate max-w-[200px]" :title="delivery.attendances[0].address || 'No Address available'">
                                                {{ delivery.attendances[0].address || 'No Address available' }}
                                            </div>
                                            <div v-else class="text-gray-400 dark:text-gray-500 italic">
                                                Pending Verification
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm">
                                            <span class="px-2 py-1 text-xs font-bold rounded-full uppercase"
                                                :class="{
                                                    'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400': delivery.status === 'pending',
                                                    'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400': delivery.status === 'on_way',
                                                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400': delivery.status === 'completed'
                                                }">
                                                {{ delivery.status.replace('_', ' ') }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm">
                                            <a v-if="delivery.status === 'completed' && delivery.attendances && delivery.attendances.length > 0 && delivery.attendances[0]?.photo_path"
                                               :href="'/storage/' + delivery.attendances[0].photo_path" 
                                               target="_blank" 
                                               class="text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 font-medium text-xs flex items-center gap-1">
                                               <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                               View Photo
                                            </a>
                                            <span v-else class="text-gray-400 dark:text-gray-500 italic text-xs">N/A</span>
                                        </td>
                                        <td class="whitespace-nowrap py-4 pl-3 pr-4 text-right text-sm font-medium sm:pr-6">
                                            <button @click="openDetail(delivery.id)" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 flex items-center gap-1 ml-auto">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                                Details
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="deliveries.data.filter(d => activeTab === 'active' ? d.status !== 'completed' : d.status === 'completed').length === 0">
                                        <td colspan="8" class="text-center py-8 text-gray-500 dark:text-gray-400">
                                            <div class="flex flex-col items-center gap-2">
                                                <svg class="w-12 h-12 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                                </svg>
                                                <p>No {{ activeTab === 'active' ? 'active' : 'completed' }} deliveries yet.</p>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-6 flex justify-end w-full" v-if="deliveries.links">
                            <Pagination :links="deliveries.links" />
                        </div>
                    </div>
                </div>
            </div>

            <Teleport to="body">
                <!-- Create Delivery Modal -->
                <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center">
                    <div class="absolute inset-0 bg-gray-900/70 backdrop-blur-sm" @click="closeModal"></div>
                    <!-- ... existing modal content ... -->
                    <div class="relative z-10 w-full max-w-2xl mx-4 bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-gray-200 dark:border-slate-700 overflow-hidden flex flex-col max-h-[90vh]">
                        <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-slate-700 shrink-0">
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white">Dispatch Logistics</h3>
                            <button type="button" @click="closeModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>
                        <div class="p-6 overflow-y-auto grow custom-scrollbar">
                            <form @submit.prevent="submit" id="deliveryForm" class="space-y-5">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Assign Driver</label>
                                    <select v-model="form.driver_id" class="block w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition">
                                        <option value="" disabled>Select a driver...</option>
                                        <option v-for="driver in drivers" :key="driver.id" :value="driver.id" :disabled="!driver.is_online">
                                            {{ driver.name }} {{ driver.is_online ? '(🟢 Online)' : '(⚪ Offline)' }}
                                        </option>
                                    </select>
                                    <p v-if="form.errors.driver_id" class="text-red-500 text-xs mt-1">{{ form.errors.driver_id }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Items / Package Description</label>
                                    <textarea
                                        v-model="form.items"
                                        rows="3"
                                        placeholder="e.g., 2x Laptop Dell, 1x Monitor 24 inch, 5x Office Chairs..."
                                        class="block w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition resize-none"
                                    ></textarea>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Optional: Describe what items will be delivered</p>
                                    <p v-if="form.errors.items" class="text-red-500 text-xs mt-1">{{ form.errors.items }}</p>
                                </div>
                                <div class="relative search-container">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Destination Location</label>
                                    <div class="relative flex items-center">
                                        <input :value="form.destination_address" @input="onAddressInput" type="text" placeholder="Type an address or drop a pin on the map..." autocomplete="off" class="block w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white pl-10 pr-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition">
                                        <svg class="w-4 h-4 text-gray-400 absolute left-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                    </div>
                                    <p v-if="form.errors.destination_address" class="text-red-500 text-xs mt-1">{{ form.errors.destination_address }}</p>
                                    <div v-if="searchResults.length > 0" class="absolute left-0 right-0 z-50 mt-1 bg-white dark:bg-slate-800 rounded-md shadow-lg border border-gray-200 dark:border-slate-700 overflow-hidden max-h-60 overflow-y-auto">
                                        <ul class="py-1 text-sm text-gray-700 dark:text-gray-300 divide-y divide-gray-100 dark:divide-slate-700">
                                            <li v-for="result in searchResults" :key="result.id" @click="selectResult(result)" class="px-4 py-3 hover:bg-emerald-50 dark:hover:bg-slate-700 cursor-pointer transition flex items-start gap-2">
                                                <svg class="w-4 h-4 text-emerald-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                                <div><span class="block text-gray-900 dark:text-white font-medium">{{ result.display_name.split(',')[0] }}</span><span class="block text-xs text-gray-500 truncate">{{ result.display_name }}</span></div>
                                            </li>
                                        </ul>
                                    </div>
                                    <div v-if="isSearching" class="absolute left-0 right-0 z-50 mt-1 bg-white dark:bg-slate-800 rounded-md shadow-lg border border-gray-200 dark:border-slate-700 p-3 text-center text-sm text-gray-500">
                                        <div class="flex items-center justify-center gap-2">
                                            <svg class="animate-spin h-4 w-4 text-emerald-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                            <span>Searching Location...</span>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <div id="modal-map" class="h-[280px] w-full rounded-xl border border-gray-300 dark:border-slate-600 z-0 overflow-hidden ring-1 ring-black/5 shadow-inner"></div>
                                    <p class="text-xs text-gray-500 mt-2 flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        Tip: You can manually click on the map to adjust the exact delivery pin.
                                    </p>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Latitude</label>
                                        <input v-model="form.destination_lat" type="number" step="any" readonly class="block w-full rounded-lg border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-900 text-gray-500 dark:text-gray-400 px-3 py-2 text-sm shadow-sm outline-none cursor-not-allowed">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Longitude</label>
                                        <input v-model="form.destination_lng" type="number" step="any" readonly class="block w-full rounded-lg border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-900 text-gray-500 dark:text-gray-400 px-3 py-2 text-sm shadow-sm outline-none cursor-not-allowed">
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="p-6 border-t border-gray-200 dark:border-slate-700 flex justify-end gap-3 shrink-0 bg-gray-50 dark:bg-slate-800/80">
                            <button type="button" @click="closeModal" class="rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition">Cancel</button>
                            <button type="submit" form="deliveryForm" :disabled="form.processing" class="rounded-lg bg-emerald-600 px-6 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50 transition shadow-sm hover:shadow-md">{{ form.processing ? 'Dispatching Delivery...' : 'Create Delivery Task' }}</button>
                        </div>
                    </div>
                </div>

                <!-- Delivery Detail Modal -->
                <div v-if="showDetailModal" class="fixed inset-0 z-[100] flex items-center justify-center">
                    <div class="absolute inset-0 bg-gray-900/70 backdrop-blur-sm" @click="closeDetail"></div>

                    <div class="relative z-10 w-full max-w-4xl mx-4 bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-gray-200 dark:border-slate-700 overflow-hidden flex flex-col max-h-[90vh]">
                        
                        <div class="flex items-center justify-between p-6 border-b border-gray-200 dark:border-slate-700 shrink-0 bg-white dark:bg-slate-800">
                            <div class="flex items-center gap-3">
                                <h3 class="text-xl font-bold text-gray-900 dark:text-white">Delivery Details</h3>
                                <span v-if="detailData" class="px-2 py-0.5 text-[10px] font-bold rounded-full uppercase bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-slate-300">#{{ detailData.delivery.id }}</span>
                            </div>
                            <button type="button" @click="closeDetail" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            </button>
                        </div>

                        <div class="p-0 overflow-y-auto grow custom-scrollbar bg-gray-50 dark:bg-slate-900">
                            <!-- Loading State -->
                            <div v-if="isLoadingDetail" class="flex flex-col items-center justify-center py-20">
                                <svg class="animate-spin h-10 w-10 text-emerald-600 mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                <p class="text-gray-500 font-medium">Fetching delivery analysis...</p>
                            </div>

                            <div v-else-if="detailData" class="grid grid-cols-1 lg:grid-cols-3 gap-0">
                                <!-- Left Column: Stats & Map -->
                                <div class="lg:col-span-2 p-6 space-y-6">
                                    <!-- Header Stats -->
                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                                        <div class="bg-white dark:bg-slate-800 p-4 rounded-xl border border-gray-100 dark:border-slate-700 shadow-sm">
                                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-bold tracking-wider mb-1">Status</p>
                                            <span class="text-sm font-bold capitalize" :class="{
                                                'text-amber-500': detailData.delivery.status === 'pending',
                                                'text-blue-500': detailData.delivery.status === 'on_way',
                                                'text-emerald-500': detailData.delivery.status === 'completed'
                                            }">{{ detailData.delivery.status.replace('_', ' ') }}</span>
                                        </div>
                                        <div class="bg-white dark:bg-slate-800 p-4 rounded-xl border border-gray-100 dark:border-slate-700 shadow-sm">
                                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-bold tracking-wider mb-1">Duration</p>
                                            <p class="text-sm font-bold text-gray-900 dark:text-white">{{ detailData.duration || '--' }}</p>
                                        </div>
                                        <div class="bg-white dark:bg-slate-800 p-4 rounded-xl border border-gray-100 dark:border-slate-700 shadow-sm">
                                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-bold tracking-wider mb-1">Distance</p>
                                            <p class="text-sm font-bold text-gray-900 dark:text-white">{{ detailData.distance }} km</p>
                                        </div>
                                        <div class="bg-white dark:bg-slate-800 p-4 rounded-xl border border-gray-100 dark:border-slate-700 shadow-sm">
                                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase font-bold tracking-wider mb-1">Driver</p>
                                            <p class="text-sm font-bold text-indigo-600 dark:text-indigo-400 truncate">{{ detailData.delivery.driver.name }}</p>
                                        </div>
                                    </div>

                                    <!-- Map Visualization -->
                                    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm overflow-hidden">
                                        <div class="px-5 py-3 border-b border-gray-100 dark:border-slate-700 flex justify-between items-center bg-gray-50/50 dark:bg-slate-800/50">
                                            <h4 class="text-xs font-bold text-gray-700 dark:text-gray-300 uppercase tracking-widest flex items-center gap-2">
                                                <svg class="w-4 h-4 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7l5-2.5 5.553 2.776a1 1 0 01.447.894v10.764a1 1 0 01-1.447.894L15 17l-6 3z" /></svg>
                                                Delivery Journey Route
                                            </h4>
                                        </div>
                                        <div id="detail-map" class="h-[350px] w-full z-0"></div>
                                    </div>

                                    <!-- Logistics Info -->
                                    <div class="bg-white dark:bg-slate-800 p-5 rounded-2xl border border-gray-100 dark:border-slate-700 shadow-sm space-y-4">
                                         <div>
                                            <h4 class="text-xs font-bold text-gray-400 uppercase mb-2">Destination Address</h4>
                                            <p class="text-gray-700 dark:text-gray-200 text-sm flex items-start gap-2">
                                                <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                                {{ detailData.delivery.destination_address }}
                                            </p>
                                         </div>
                                         <div v-if="detailData.delivery.attendances?.find(a => a.type === 'proof_of_delivery')">
                                            <h4 class="text-xs font-bold text-gray-400 uppercase mb-2">Final Drop-off Location</h4>
                                            <p class="text-gray-700 dark:text-gray-200 text-sm flex items-start gap-2">
                                                <svg class="w-5 h-5 text-emerald-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                {{ detailData.delivery.attendances.find(a => a.type === 'proof_of_delivery').address || 'Lat: ' + detailData.delivery.attendances.find(a => a.type === 'proof_of_delivery').latitude }}
                                            </p>
                                         </div>
                                    </div>
                                </div>

                                <!-- Right Column: Verification & Timeline -->
                                <div class="p-6 border-l border-gray-200 dark:border-slate-700 space-y-6">
                                    <!-- Proof of Delivery Section -->
                                    <div v-if="detailData.delivery.attendances?.find(a => a.type === 'proof_of_delivery')" class="space-y-4">
                                        <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em]">Verification Asset</h4>
                                        <div class="relative group">
                                            <img :src="'/storage/' + detailData.delivery.attendances.find(a => a.type === 'proof_of_delivery').photo_path" 
                                                 class="w-full aspect-[4/3] object-cover rounded-xl shadow-lg border-2 border-white dark:border-slate-800 ring-1 ring-gray-200 dark:ring-slate-700">
                                        </div>
                                        
                                        <!-- Face Recognition Accuracy Display -->
                                        <div class="bg-gradient-to-br from-emerald-50 to-teal-50 dark:from-emerald-900/20 dark:to-teal-900/20 rounded-xl p-4 border border-emerald-200 dark:border-emerald-800 space-y-3">
                                            <div class="flex items-center justify-between">
                                                <span class="text-xs font-bold text-emerald-800 dark:text-emerald-400">Face Recognition Match</span>
                                                <span class="text-lg font-bold text-emerald-600 dark:text-emerald-400">
                                                    {{ (detailData.delivery.attendances.find(a => a.type === 'proof_of_delivery').face_similarity_score * 100).toFixed(1) }}%
                                                </span>
                                            </div>
                                            
                                            <!-- Progress Bar -->
                                            <div class="w-full bg-emerald-200 dark:bg-emerald-900/40 rounded-full h-2 overflow-hidden">
                                                <div class="h-full bg-emerald-500 rounded-full transition-all duration-500"
                                                     :style="{ width: (detailData.delivery.attendances.find(a => a.type === 'proof_of_delivery').face_similarity_score * 100) + '%' }">
                                                </div>
                                            </div>
                                            
                                            <!-- Validation Status -->
                                            <div class="flex items-center gap-2">
                                                <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                <span class="text-xs font-medium text-emerald-700 dark:text-emerald-400">
                                                    {{ detailData.delivery.attendances.find(a => a.type === 'proof_of_delivery').validation_status === 'valid' ? 'Verified' : 'Unverified' }}
                                                </span>
                                            </div>
                                            
                                            <!-- Research Note -->
                                            <div class="pt-2 border-t border-emerald-200 dark:border-emerald-800">
                                                <p class="text-[10px] text-emerald-700 dark:text-emerald-400 flex items-start gap-1.5">
                                                    <svg class="w-3 h-3 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    <span><strong>Research:</strong> Accuracy metric for evaluation purposes</span>
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Timeline Visualization -->
                                    <div class="space-y-4">
                                        <h4 class="text-[10px] font-bold text-gray-400 uppercase tracking-[0.2em]">Audit Timeline</h4>
                                        <div class="flow-root">
                                            <ul role="list" class="-mb-8">
                                                <li v-for="(step, stepIdx) in getTimelineStatus(detailData.delivery)" :key="step.label">
                                                    <div class="relative pb-8">
                                                        <span v-if="stepIdx !== getTimelineStatus(detailData.delivery).length - 1" class="absolute left-4 top-4 -ml-px h-full w-0.5 bg-gray-200 dark:bg-slate-700" aria-hidden="true"></span>
                                                        <div class="relative flex space-x-3">
                                                            <div>
                                                                <span :class="[step.color, 'h-8 w-8 rounded-full flex items-center justify-center ring-4 ring-gray-50 dark:ring-slate-900 shadow-sm transition-all hover:scale-110']">
                                                                    <svg v-if="step.icon === 'plus'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" /></svg>
                                                                    <svg v-else-if="step.icon === 'truck'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" /></svg>
                                                                    <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>
                                                                </span>
                                                            </div>
                                                            <div class="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                                                                <div>
                                                                    <p class="text-xs font-bold text-gray-900 dark:text-white">{{ step.label }}</p>
                                                                </div>
                                                                <div class="whitespace-nowrap text-right text-[10px] text-gray-500">
                                                                    <time>{{ moment(step.date).format('HH:mm') }}<br>{{ moment(step.date).format('DD MMM') }}</time>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="p-6 border-t border-gray-200 dark:border-slate-700 flex justify-end gap-3 shrink-0 bg-white dark:bg-slate-800">
                            <button type="button" @click="closeDetail" class="rounded-lg bg-emerald-600 px-8 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 transition shadow-lg shadow-emerald-500/20 active:scale-95">
                                Close Analysis
                            </button>
                        </div>
                    </div>
                </div>
            </Teleport>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Custom Scrollbar for better UX inside modal */
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #cbd5e1;
  border-radius: 20px;
}
.dark .custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #475569;
}
</style>