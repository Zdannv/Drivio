<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
export default { layout: AuthenticatedLayout };
</script>

<script setup>
import { Head, usePage, router } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import axios from 'axios';
import CameraCapture from '@/Components/CameraCapture.vue';

const user = usePage().props.auth.user;

const props = defineProps({
    isCheckedIn: Boolean,
    hasCheckedOut: Boolean
});

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
        router.reload({ only: ['isCheckedIn', 'hasCheckedOut'] });
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

onMounted(() => {
    fetchDeliveries();
});
</script>

<template>
  <Head title="Driver Dashboard" />

  <div class="w-full min-h-[calc(100vh-4rem)] bg-gray-50 dark:bg-slate-900 py-6 sm:py-10">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 text-gray-800 dark:text-gray-200">
      
        <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-white mb-8">
            Hello, {{ user.name }}
        </h1>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
            
            <!-- Left/Top: General Actions -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Profile Block Restored & Modernized -->
                <div class="bg-gradient-to-br from-indigo-600 to-indigo-800 rounded-2xl p-6 text-white shadow-lg relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full blur-2xl transform translate-x-10 -translate-y-10"></div>
                    <div class="relative z-10 flex items-center gap-4">
                        <div class="w-16 h-16 rounded-full bg-white/20 border-2 border-white/50 p-1 shrink-0 overflow-hidden backdrop-blur-sm">
                            <img v-if="user.avatar" :src="user.avatar" alt="Avatar" class="w-full h-full object-cover rounded-full" />
                            <div v-else class="w-full h-full flex items-center justify-center font-bold text-xl">{{ user.name.charAt(0) }}</div>
                        </div>
                        <div>
                            <p class="text-indigo-200 font-medium text-sm">Valid License</p>
                            <h2 class="text-xl font-extrabold tracking-tight">{{ user.name }}</h2>
                            <div class="inline-flex items-center gap-1.5 bg-black/20 px-2.5 py-1 rounded-full text-xs font-medium mt-2 backdrop-blur-md">
                                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                                Online Platform
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 p-6">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-6">Daily Attendance</h3>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <button 
                            @click="openCamera('check_in')"
                            :disabled="props.isCheckedIn"
                            class="flex flex-col items-center justify-center p-5 rounded-xl transition shadow-sm group border"
                            :class="props.isCheckedIn ? 'bg-gray-100 dark:bg-slate-800 border-gray-200 dark:border-slate-700 opacity-60 cursor-not-allowed' : 'bg-primary-50 hover:bg-primary-100 dark:bg-primary-900/20 dark:hover:bg-primary-900/40 border-primary-100 dark:border-primary-800'">
                            <div class="text-white p-3 rounded-full mb-3 shadow-md transition-transform" :class="[props.isCheckedIn ? 'bg-gray-400 dark:bg-slate-600' : 'bg-primary-500', {'group-active:scale-95': !props.isCheckedIn}]">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                                </svg>
                            </div>
                            <span class="font-bold text-sm" :class="props.isCheckedIn ? 'text-gray-500 dark:text-gray-400' : 'text-primary-700 dark:text-primary-400'">
                                {{ props.isCheckedIn ? 'Checked In' : 'Check In' }}
                            </span>
                        </button>

                        <button 
                            @click="openCamera('check_out')"
                            :disabled="!props.isCheckedIn || props.hasCheckedOut"
                            class="flex flex-col items-center justify-center p-5 rounded-xl transition shadow-sm group border"
                            :class="(!props.isCheckedIn || props.hasCheckedOut) ? 'bg-gray-100 dark:bg-slate-800 border-gray-200 dark:border-slate-700 opacity-60 cursor-not-allowed' : 'bg-rose-50 hover:bg-rose-100 dark:bg-rose-900/20 dark:hover:bg-rose-900/40 border-rose-100 dark:border-rose-800'">
                            <div class="text-white p-3 rounded-full mb-3 shadow-md transition-transform" :class="[(!props.isCheckedIn || props.hasCheckedOut) ? 'bg-gray-400 dark:bg-slate-600' : 'bg-rose-500', {'group-active:scale-95': (props.isCheckedIn && !props.hasCheckedOut)}]">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                            </div>
                            <span class="font-bold text-sm" :class="(!props.isCheckedIn || props.hasCheckedOut) ? 'text-gray-500 dark:text-gray-400' : 'text-rose-700 dark:text-rose-400'">
                                {{ props.hasCheckedOut ? 'Checked Out' : 'Check Out' }}
                            </span>
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
                        <div v-if="!props.isCheckedIn" class="p-4 mx-6 mt-4 mb-2 bg-amber-50 dark:bg-amber-900/20 text-amber-800 dark:text-amber-400 border border-amber-200 dark:border-amber-800/50 rounded-lg flex gap-3 text-sm font-medium">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <span>Please Check In to interact with your delivery tasks.</span>
                        </div>

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
                                <button v-if="delivery.status === 'pending'" @click="updateDeliveryStatus(delivery.id, 'on_way')" :disabled="!props.isCheckedIn || props.hasCheckedOut" class="w-full sm:w-auto px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-semibold rounded-lg shadow-sm transition text-sm disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100 active:scale-95">
                                    Start Delivery
                                </button>
                                
                                <button v-else-if="delivery.status === 'on_way'" @click="openCamera('proof_of_delivery', delivery.id)" :disabled="!props.isCheckedIn || props.hasCheckedOut" class="w-full sm:w-auto px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold rounded-lg shadow-sm transition text-sm flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100 active:scale-95">
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

  <CameraCapture 
    v-if="showCameraModal" 
    @close="showCameraModal = false" 
    @capture="handleCapture"
  />

</template>
