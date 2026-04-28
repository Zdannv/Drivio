<script setup>
import { ref, nextTick } from 'vue';
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

const showModal = ref(false);

const form = useForm({
    driver_id: '',
    destination_address: '',
    destination_lat: '',
    destination_lng: '',
});

// Map & Geocoding State
let map = null;
let marker = null;
const searchResults = ref([]);
const isSearching = ref(false);
let searchTimeout = null;

// Custom Map Marker Icon
const createIcon = () => {
    return L.divIcon({
        className: 'custom-div-icon',
        html: `<div style="background-color: #059669; width: 24px; height: 24px; border-radius: 50%; border: 3px solid white; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);"></div>`,
        iconSize: [24, 24],
        iconAnchor: [12, 12]
    });
};

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
</script>

<template>
    <Head title="Manage Deliveries" />

    <AuthenticatedLayout>
        <div class="py-10 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
            <div class="sm:flex sm:items-center">
                <div class="sm:flex-auto">
                    <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Active Deliveries</h1>
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
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-slate-700">
                                    <tr v-for="delivery in deliveries.data" :key="delivery.id">
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
                                               View
                                            </a>
                                            <span v-else class="text-gray-400 dark:text-gray-500 italic text-xs">N/A</span>
                                        </td>
                                    </tr>
                                    <tr v-if="deliveries.data.length === 0">
                                        <td colspan="7" class="text-center py-8 text-gray-500 dark:text-gray-400">No deliveries created yet.</td>
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
                <div v-if="showModal" class="fixed inset-0 z-[100] flex items-center justify-center">
                    <div class="absolute inset-0 bg-gray-900/70 backdrop-blur-sm" @click="closeModal"></div>

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
                                        <option v-for="driver in drivers" :key="driver.id" :value="driver.id">
                                            {{ driver.name }} {{ driver.is_online ? '(🟢 Online)' : '(⚪ Offline)' }}
                                        </option>
                                    </select>
                                    <p v-if="form.errors.driver_id" class="text-red-500 text-xs mt-1">{{ form.errors.driver_id }}</p>
                                </div>

                                <div class="relative search-container">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Destination Location</label>
                                    <div class="relative flex items-center">
                                        <input 
                                            :value="form.destination_address"
                                            @input="onAddressInput"
                                            type="text" 
                                            placeholder="Type an address or drop a pin on the map..." 
                                            autocomplete="off"
                                            class="block w-full rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white pl-10 pr-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition"
                                        >
                                        <svg class="w-4 h-4 text-gray-400 absolute left-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                    </div>
                                    <p v-if="form.errors.destination_address" class="text-red-500 text-xs mt-1">{{ form.errors.destination_address }}</p>

                                    <div v-if="searchResults.length > 0" class="absolute left-0 right-0 z-50 mt-1 bg-white dark:bg-slate-800 rounded-md shadow-lg border border-gray-200 dark:border-slate-700 overflow-hidden max-h-60 overflow-y-auto">
                                        <ul class="py-1 text-sm text-gray-700 dark:text-gray-300 divide-y divide-gray-100 dark:divide-slate-700">
                                            <li v-for="result in searchResults" :key="result.id" 
                                                @click="selectResult(result)"
                                                class="px-4 py-3 hover:bg-emerald-50 dark:hover:bg-slate-700 cursor-pointer transition flex items-start gap-2"
                                            >
                                                <svg class="w-4 h-4 text-emerald-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                                <div>
                                                    <span class="block text-gray-900 dark:text-white font-medium">{{ result.display_name.split(',')[0] }}</span>
                                                    <span class="block text-xs text-gray-500 truncate">{{ result.display_name }}</span>
                                                </div>
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
                                        <p v-if="form.errors.destination_lat" class="text-red-500 text-xs mt-1">{{ form.errors.destination_lat }}</p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Longitude</label>
                                        <input v-model="form.destination_lng" type="number" step="any" readonly class="block w-full rounded-lg border border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-900 text-gray-500 dark:text-gray-400 px-3 py-2 text-sm shadow-sm outline-none cursor-not-allowed">
                                        <p v-if="form.errors.destination_lng" class="text-red-500 text-xs mt-1">{{ form.errors.destination_lng }}</p>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <div class="p-6 border-t border-gray-200 dark:border-slate-700 flex justify-end gap-3 shrink-0 bg-gray-50 dark:bg-slate-800/80">
                            <button type="button" @click="closeModal" class="rounded-lg border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition">
                                Cancel
                            </button>
                            <button
                                type="submit"
                                form="deliveryForm"
                                :disabled="form.processing"
                                class="rounded-lg bg-emerald-600 px-6 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50 transition shadow-sm hover:shadow-md"
                            >
                                {{ form.processing ? 'Dispatching Delivery...' : 'Create Delivery Task' }}
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