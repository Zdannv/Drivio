<script>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
export default { layout: AuthenticatedLayout };
</script>

<script setup>
import { Head, usePage, router } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';
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

/* ----------------------------------
   GPS TRACKING STATE
----------------------------------- */
const isTracking = ref(false);
const trackingStatus = ref('inactive'); // 'active' | 'inactive' | 'error'
const lastTrackingTime = ref(null);
const trackingError = ref(null);
const trackingIntervalId = ref(null);

// Configuration: Send location every 15 seconds
const TRACKING_INTERVAL_MS = 15000;

/* ----------------------------------
   GPS TRACKING FUNCTIONS
----------------------------------- */
const startTracking = () => {
    if (isTracking.value) {
        console.warn('Tracking already active');
        return;
    }

    if (!navigator.geolocation) {
        trackingStatus.value = 'error';
        trackingError.value = 'Geolocation is not supported by your browser';
        alert('❌ GPS tracking is not supported on this device');
        return;
    }

    isTracking.value = true;
    trackingStatus.value = 'active';
    trackingError.value = null;

    // Send initial location immediately
    sendLocationUpdate();

    // Set up interval for periodic updates (every 15 seconds)
    trackingIntervalId.value = setInterval(() => {
        sendLocationUpdate();
    }, TRACKING_INTERVAL_MS);

    console.log('✅ GPS tracking started');
};

const stopTracking = () => {
    if (!isTracking.value) {
        return;
    }

    if (trackingIntervalId.value) {
        clearInterval(trackingIntervalId.value);
        trackingIntervalId.value = null;
    }

    isTracking.value = false;
    trackingStatus.value = 'inactive';
    lastTrackingTime.value = null;

    console.log('🛑 GPS tracking stopped');
};

const sendLocationUpdate = async () => {
    try {
        navigator.geolocation.getCurrentPosition(
            async (position) => {
                const { latitude, longitude } = position.coords;

                try {
                    await axios.post('/tracking/store', {
                        latitude,
                        longitude,
                        delivery_id: selectedDeliveryId.value
                    });

                    lastTrackingTime.value = new Date();
                    trackingStatus.value = 'active';
                    trackingError.value = null;

                    console.log(`📍 Location updated: ${latitude}, ${longitude}`);
                } catch (error) {
                    console.error('Failed to send location to server:', error);
                    
                    if (error.response?.status === 403) {
                        stopTracking();
                        alert('❌ You are not authorized to submit tracking data');
                    } else {
                        trackingStatus.value = 'error';
                        trackingError.value = 'Failed to send location to server';
                    }
                }
            },
            (error) => {
                handleGeolocationError(error);
            },
            {
                enableHighAccuracy: false,
                timeout: 10000,
                maximumAge: 5000
            }
        );
    } catch (error) {
        console.error('Unexpected error in sendLocationUpdate:', error);
        trackingStatus.value = 'error';
        trackingError.value = 'Unexpected error occurred';
    }
};

const handleGeolocationError = (error) => {
    let errorMessage = 'Unknown error occurred';

    switch (error.code) {
        case error.PERMISSION_DENIED:
            errorMessage = 'Location permission denied. Please enable location access in your browser settings.';
            break;
        case error.POSITION_UNAVAILABLE:
            errorMessage = 'Location information is unavailable. Please check your GPS settings.';
            break;
        case error.TIMEOUT:
            errorMessage = 'Location request timed out. Please try again.';
            break;
    }

    console.error('Geolocation error:', errorMessage);
    trackingStatus.value = 'error';
    trackingError.value = errorMessage;

    if (error.code === error.PERMISSION_DENIED) {
        stopTracking();
        alert(`❌ ${errorMessage}`);
    }
};

const formatTimeAgo = (date) => {
    if (!date) return '';
    
    const seconds = Math.floor((new Date() - date) / 1000);
    
    if (seconds < 60) return `${seconds}s ago`;
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
    return `${Math.floor(seconds / 3600)}h ago`;
};

/* ----------------------------------
   EXISTING DRIVER FUNCTIONS
----------------------------------- */
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

// State for face recognition result display
const showFaceRecognitionResult = ref(false);
const faceRecognitionData = ref({
    similarity: 0,
    message: '',
    success: false
});

// State for testing mode (research panel)
const isTestMode = ref(false);
const isResetting = ref(false);

const openCameraForTest = () => {
    isTestMode.value = true;
    currentAction.value = 'test_face';
    selectedDeliveryId.value = null;
    showCameraModal.value = true;
};

const handleTestCapture = async (payload) => {
    try {
        const response = await axios.post('/attendance/test', {
            image: payload.image,
        });

        const score = response.data.similarity_score ?? 0;
        const similarity = (score * 100).toFixed(1);
        const success = response.data.status === 'success';

        faceRecognitionData.value = {
            similarity,
            message: success
                ? `${response.data.message} ${response.data.is_match ? '(Match)' : '(No match)'}`
                : response.data.message,
            success,
        };
        showFaceRecognitionResult.value = true;

        setTimeout(() => {
            showFaceRecognitionResult.value = false;
        }, 6000);
    } catch (error) {
        faceRecognitionData.value = {
            similarity: 0,
            message: error.response?.data?.message || 'Test verification failed',
            success: false,
        };
        showFaceRecognitionResult.value = true;
        setTimeout(() => {
            showFaceRecognitionResult.value = false;
        }, 6000);
    } finally {
        showCameraModal.value = false;
        isTestMode.value = false;
    }
};

const resetTestData = async () => {
    if (!confirm('This will delete ALL your attendance records and tracking logs. Continue?')) {
        return;
    }

    isResetting.value = true;
    try {
        const response = await axios.post('/attendance/reset-test');
        alert(`✅ ${response.data.message}`);

        // Stop tracking and reload to refresh check-in/check-out state
        stopTracking();
        router.reload({ only: ['isCheckedIn', 'hasCheckedOut'] });
        fetchDeliveries();
    } catch (error) {
        alert(`❌ ${error.response?.data?.message || 'Failed to reset test data'}`);
    } finally {
        isResetting.value = false;
    }
};

const handleCapture = async (payload) => {
    // Route to test handler if in test mode
    if (isTestMode.value) {
        await handleTestCapture(payload);
        return;
    }

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
            
            // Show face recognition result
            faceRecognitionData.value = {
                similarity: similarity,
                message: response.data.message,
                success: true
            };
            showFaceRecognitionResult.value = true;
            
            // Auto hide after 5 seconds
            setTimeout(() => {
                showFaceRecognitionResult.value = false;
            }, 5000);
            
            // Start tracking on check-in
            if (currentAction.value === 'check_in') {
                startTracking();
            }
            
            // Stop tracking on check-out
            if (currentAction.value === 'check_out') {
                stopTracking();
            }
            
            if (currentAction.value === 'proof_of_delivery' && selectedDeliveryId.value) {
                await updateDeliveryStatus(selectedDeliveryId.value, 'completed');
            }
        } else {
            // Show error result
            faceRecognitionData.value = {
                similarity: response.data.similarity_score ? (response.data.similarity_score * 100).toFixed(1) : 0,
                message: response.data.message,
                success: false
            };
            showFaceRecognitionResult.value = true;
            
            setTimeout(() => {
                showFaceRecognitionResult.value = false;
            }, 5000);
        }
    } catch (error) {
        faceRecognitionData.value = {
            similarity: 0,
            message: error.response?.data?.message || 'Verification failed',
            success: false
        };
        showFaceRecognitionResult.value = true;
        
        setTimeout(() => {
            showFaceRecognitionResult.value = false;
        }, 5000);
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

    // Start tracking if driver is already checked in
    if (props.isCheckedIn && !props.hasCheckedOut) {
        startTracking();
    }
});

onUnmounted(() => {
    // Always stop tracking and clear intervals on component unmount
    stopTracking();
});
</script>

<template>
  <Head title="Driver Dashboard" />

  <div class="w-full min-h-[calc(100vh-4rem)] bg-gray-50 dark:bg-slate-900 py-6 sm:py-10">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 text-gray-800 dark:text-gray-200">
      
        <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-white mb-8">
            Hello, {{ user.name }}
        </h1>

        <!-- GPS Tracking Status Indicator -->
        <div v-if="props.isCheckedIn && !props.hasCheckedOut" 
             class="mb-6 p-4 rounded-xl border transition-all duration-300"
             :class="{
               'bg-emerald-50 border-emerald-200 dark:bg-emerald-900/20 dark:border-emerald-800': trackingStatus === 'active',
               'bg-gray-50 border-gray-200 dark:bg-slate-800 dark:border-slate-700': trackingStatus === 'inactive',
               'bg-rose-50 border-rose-200 dark:bg-rose-900/20 dark:border-rose-800': trackingStatus === 'error'
             }">
          <div class="flex items-center gap-3">
            <!-- Status Icon -->
            <div class="shrink-0">
              <div v-if="trackingStatus === 'active'" 
                   class="w-3 h-3 rounded-full bg-emerald-500 animate-pulse ring-4 ring-emerald-100 dark:ring-emerald-900/30"></div>
              <div v-else-if="trackingStatus === 'inactive'" 
                   class="w-3 h-3 rounded-full bg-gray-400 ring-4 ring-gray-100 dark:ring-gray-800"></div>
              <div v-else 
                   class="w-3 h-3 rounded-full bg-rose-500 ring-4 ring-rose-100 dark:ring-rose-900/30"></div>
            </div>

            <!-- Status Text -->
            <div class="flex-1">
              <p class="font-semibold text-sm"
                 :class="{
                   'text-emerald-800 dark:text-emerald-400': trackingStatus === 'active',
                   'text-gray-700 dark:text-gray-400': trackingStatus === 'inactive',
                   'text-rose-800 dark:text-rose-400': trackingStatus === 'error'
                 }">
                <span v-if="trackingStatus === 'active'">📍 GPS Tracking Active</span>
                <span v-else-if="trackingStatus === 'inactive'">⏸️ GPS Tracking Inactive</span>
                <span v-else>⚠️ GPS Tracking Error</span>
              </p>
              <p class="text-xs mt-1"
                 :class="{
                   'text-emerald-700 dark:text-emerald-500': trackingStatus === 'active',
                   'text-gray-600 dark:text-gray-500': trackingStatus === 'inactive',
                   'text-rose-700 dark:text-rose-500': trackingStatus === 'error'
                 }">
                <span v-if="trackingStatus === 'active' && lastTrackingTime">
                  Last update: {{ formatTimeAgo(lastTrackingTime) }}
                </span>
                <span v-else-if="trackingStatus === 'inactive'">
                  Location tracking is paused
                </span>
                <span v-else-if="trackingError">
                  {{ trackingError }}
                </span>
              </p>
            </div>

            <!-- GPS Icon -->
            <div class="shrink-0">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" 
                   :class="{
                     'text-emerald-600 dark:text-emerald-400': trackingStatus === 'active',
                     'text-gray-400 dark:text-gray-600': trackingStatus === 'inactive',
                     'text-rose-600 dark:text-rose-400': trackingStatus === 'error'
                   }"
                   fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" 
                      d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" 
                      d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
            
            <!-- Left/Top: General Actions -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Profile Block Restored & Modernized -->
                <div class="bg-gradient-to-br from-indigo-600 to-indigo-800 rounded-2xl p-6 text-white shadow-lg relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full blur-2xl transform translate-x-10 -translate-y-10"></div>
                    <div class="relative z-10 flex items-center gap-4">
                        <div class="w-16 h-16 rounded-full bg-white/20 border-2 border-white/50 p-1 shrink-0 overflow-hidden backdrop-blur-sm">
                            <img v-if="user.avatar" :src="user.avatar" alt="Avatar" class="w-full h-full object-cover rounded-full" />
                            <div v-else class="w-full h-full flex items-center justify-center font-bold text-xl bg-gradient-to-br from-blue-400 to-purple-500 text-white rounded-full">{{ user.name.charAt(0) }}</div>
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

                <!-- Research Testing Panel -->
                <div class="bg-gradient-to-br from-amber-50 to-orange-50 dark:from-amber-900/20 dark:to-orange-900/20 rounded-2xl shadow-sm border border-amber-200 dark:border-amber-800 p-6">
                    <div class="flex items-center gap-2 mb-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                        </svg>
                        <h3 class="text-lg font-bold text-amber-900 dark:text-amber-300">Research Testing</h3>
                    </div>
                    <p class="text-xs text-amber-700 dark:text-amber-400 mb-5">
                        Tools for testing face recognition accuracy. No data is saved when testing.
                    </p>

                    <div class="space-y-3">
                        <!-- Test Face Recognition Button -->
                        <button
                            @click="openCameraForTest"
                            class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-amber-600 hover:bg-amber-500 text-white font-semibold rounded-lg shadow-sm transition active:scale-95"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            <span>Test Face Recognition</span>
                        </button>

                        <!-- Reset Test Data Button -->
                        <button
                            @click="resetTestData"
                            :disabled="isResetting"
                            class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-white dark:bg-slate-800 hover:bg-rose-50 dark:hover:bg-rose-900/20 text-rose-600 dark:text-rose-400 font-semibold rounded-lg shadow-sm border border-rose-200 dark:border-rose-800 transition active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <svg v-if="!isResetting" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                            <svg v-else class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>{{ isResetting ? 'Resetting...' : 'Reset Test Data' }}</span>
                        </button>
                    </div>

                    <div class="mt-4 p-3 bg-white/60 dark:bg-slate-900/40 rounded-lg border border-amber-200/50 dark:border-amber-800/50">
                        <p class="text-[11px] text-amber-800 dark:text-amber-400 leading-relaxed">
                            <strong>Note:</strong> Reset will delete all attendance records and tracking logs for this account. Use this when you finish a testing round and want a clean state.
                        </p>
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
    :skip-gps="isTestMode"
    :title="isTestMode ? 'Face Recognition Test' : 'Verification Camera'"
    @close="showCameraModal = false; isTestMode = false" 
    @capture="handleCapture"
  />

  <!-- Face Recognition Result Toast -->
  <Teleport to="body">
    <Transition
      enter-active-class="transition ease-out duration-300"
      enter-from-class="translate-x-full opacity-0"
      enter-to-class="translate-x-0 opacity-100"
      leave-active-class="transition ease-in duration-200"
      leave-from-class="translate-x-0 opacity-100"
      leave-to-class="translate-x-full opacity-0"
    >
      <div v-if="showFaceRecognitionResult" 
           class="fixed top-4 right-4 z-50 w-96 max-w-[calc(100vw-2rem)] bg-white dark:bg-slate-800 rounded-xl shadow-2xl border overflow-hidden"
           :class="faceRecognitionData.success ? 'border-emerald-200 dark:border-emerald-800' : 'border-rose-200 dark:border-rose-800'">
        
        <!-- Header -->
        <div class="px-4 py-3 flex items-center justify-between border-b"
             :class="faceRecognitionData.success ? 'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-100 dark:border-emerald-800' : 'bg-rose-50 dark:bg-rose-900/20 border-rose-100 dark:border-rose-800'">
          <div class="flex items-center gap-2">
            <!-- Success Icon -->
            <svg v-if="faceRecognitionData.success" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <!-- Error Icon -->
            <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-rose-600 dark:text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            
            <h3 class="font-bold text-sm"
                :class="faceRecognitionData.success ? 'text-emerald-800 dark:text-emerald-400' : 'text-rose-800 dark:text-rose-400'">
              Face Recognition Result
            </h3>
          </div>
          
          <!-- Close Button -->
          <button @click="showFaceRecognitionResult = false" 
                  class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Content -->
        <div class="p-4">
          <!-- Message -->
          <p class="text-sm font-medium text-gray-800 dark:text-gray-200 mb-3">
            {{ faceRecognitionData.message }}
          </p>

          <!-- Accuracy Display -->
          <div class="space-y-2">
            <div class="flex items-center justify-between text-xs">
              <span class="font-semibold text-gray-700 dark:text-gray-300">Similarity Score</span>
              <span class="font-bold"
                    :class="faceRecognitionData.success ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                {{ faceRecognitionData.similarity }}%
              </span>
            </div>
            
            <!-- Progress Bar -->
            <div class="w-full bg-gray-200 dark:bg-slate-700 rounded-full h-2 overflow-hidden">
              <div class="h-full rounded-full transition-all duration-500"
                   :class="faceRecognitionData.success ? 'bg-emerald-500' : 'bg-rose-500'"
                   :style="{ width: faceRecognitionData.similarity + '%' }">
              </div>
            </div>
          </div>

          <!-- Research Note -->
          <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
            <p class="text-xs text-blue-800 dark:text-blue-400 flex items-start gap-2">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <span><strong>Research Purpose:</strong> This accuracy metric is displayed for research and evaluation purposes only.</span>
            </p>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>

</template>
