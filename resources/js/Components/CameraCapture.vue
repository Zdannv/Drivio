<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 sm:p-6" @click.self="$emit('close')">
    <div class="bg-gray-900 rounded-2xl overflow-hidden shadow-2xl w-full max-w-md border border-gray-700 flex flex-col relative animate-fade-in-up">
      <!-- Header -->
      <div class="px-5 py-4 border-b border-gray-800 flex justify-between items-center bg-gray-900/80">
        <h3 class="text-white font-semibold text-lg flex items-center gap-2">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-emerald-500" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
          </svg>
          Verification Camera
        </h3>
        <button 
          @click="$emit('close')" 
          class="text-gray-400 hover:text-white transition-colors bg-gray-800 hover:bg-gray-700 rounded-full p-1.5 focus:outline-none"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
          </svg>
        </button>
      </div>

      <!-- Video Area -->
      <div class="relative bg-black flex-1 aspect-[3/4] sm:aspect-square flex flex-col justify-center items-center overflow-hidden">
        <video 
          ref="videoEl" 
          autoplay 
          playsinline 
          class="w-full h-full object-cover transform scale-x-[-1]"
        ></video>
        
        <!-- Loading UI -->
        <div v-if="isLoading" class="absolute inset-0 flex flex-col items-center justify-center bg-black/70 z-10">
          <div class="animate-spin rounded-full h-12 w-12 border-4 border-emerald-500 border-t-transparent mb-4"></div>
          <p class="text-white text-sm font-medium">{{ loadingMessage }}</p>
        </div>

        <div class="absolute bottom-4 left-0 right-0 text-center px-4 z-0">
          <p class="text-white text-sm bg-black/60 py-1.5 px-4 rounded-full inline-block backdrop-blur-md shadow-lg border border-white/10">
            Please align your face within the frame
          </p>
        </div>
      </div>

      <!-- Footer Action -->
      <div class="p-5 bg-gray-900 border-t border-gray-800">
        <button 
          @click="captureAndSubmit" 
          :disabled="isLoading || !streamReady"
          class="w-full flex items-center justify-center gap-2 py-3.5 px-4 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold rounded-xl shadow-lg hover:shadow-emerald-900/50 transition-all active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <svg v-if="!isLoading" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
          <span>Capture & Submit</span>
        </button>
      </div>
      
      <canvas ref="canvasEl" class="hidden"></canvas>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const emit = defineEmits(['capture', 'close']);

const videoEl = ref(null);
const canvasEl = ref(null);
const stream = ref(null);
const isLoading = ref(true);
const streamReady = ref(false);
const loadingMessage = ref('Initializing camera...');

const startCamera = async () => {
    try {
        const mediaStream = await navigator.mediaDevices.getUserMedia({ 
            video: { facingMode: 'user' } 
        });
        
        stream.value = mediaStream;
        if (videoEl.value) {
            videoEl.value.srcObject = mediaStream;
            videoEl.value.onloadedmetadata = () => {
                streamReady.value = true;
                isLoading.value = false;
            };
        }
    } catch (error) {
        console.error('Error accessing camera:', error);
        loadingMessage.value = 'Camera access denied or unavailable.';
    }
};

const stopCamera = () => {
    if (stream.value) {
        stream.value.getTracks().forEach(track => track.stop());
    }
};

const getLocation = () => {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject('Geolocation is not supported by your browser');
        } else {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    resolve({
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude
                    });
                },
                (error) => {
                    console.error('Geolocation error:', error);
                    reject('Unable to retrieve your location');
                },
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
            );
        }
    });
};

const captureAndSubmit = async () => {
    if (!videoEl.value || !canvasEl.value) return;

    isLoading.value = true;
    loadingMessage.value = 'Acquiring GPS location...';

    let locationData = { latitude: null, longitude: null };
    try {
        locationData = await getLocation();
    } catch (err) {
        alert(err || 'Failed to get location. Ensure GPS is enabled.');
        isLoading.value = false;
        return;
    }

    loadingMessage.value = 'Processing image...';
    
    const video = videoEl.value;
    const canvas = canvasEl.value;
    
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    const ctx = canvas.getContext('2d');
    
    ctx.translate(canvas.width, 0);
    ctx.scale(-1, 1);
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    
    const base64Image = canvas.toDataURL('image/jpeg', 0.8);

    loadingMessage.value = 'Verifying with server...';

    emit('capture', {
        image: base64Image,
        latitude: locationData.latitude,
        longitude: locationData.longitude
    });
};

onMounted(() => {
    startCamera();
});

onUnmounted(() => {
    stopCamera();
});
</script>

<style scoped>
@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
.animate-fade-in-up {
  animation: fadeInUp 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}
</style>
