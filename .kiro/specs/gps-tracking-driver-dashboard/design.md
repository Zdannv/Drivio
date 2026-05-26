# Design Document: GPS Tracking on Driver Dashboard

## Overview

This document describes the technical design for implementing automatic GPS tracking functionality on the Driver Dashboard in the Drivio logistics application. The feature enables real-time location monitoring by automatically capturing and transmitting GPS coordinates to the backend at regular intervals.

The implementation uses Vue 3 Composition API with reactive state management, integrates with the browser's Geolocation API, and communicates with the existing Laravel backend endpoint POST /tracking/store. The design emphasizes proper lifecycle management, error resilience, and user experience through visual status indicators.

## Architecture

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Driver Dashboard Component                │
│  ┌───────────────────────────────────────────────────────┐  │
│  │              useGPSTracking Composable                 │  │
│  │  ┌─────────────────────────────────────────────────┐  │  │
│  │  │         Tracking State Management               │  │  │
│  │  │  - trackingStatus (ref)                         │  │  │
│  │  │  - errorMessage (ref)                           │  │  │
│  │  │  - isTransmitting (ref)                         │  │  │
│  │  └─────────────────────────────────────────────────┘  │  │
│  │  ┌─────────────────────────────────────────────────┐  │  │
│  │  │         GPS Tracking Functions                  │  │  │
│  │  │  - startTracking()                              │  │  │
│  │  │  - stopTracking()                               │  │  │
│  │  │  - captureAndSendLocation()                     │  │  │
│  │  │  - handleGeolocationError()                     │  │  │
│  │  └─────────────────────────────────────────────────┘  │  │
│  │  ┌─────────────────────────────────────────────────┐  │  │
│  │  │         Lifecycle Hooks                         │  │  │
│  │  │  - onMounted: Initialize tracking               │  │  │
│  │  │  - onUnmounted: Cleanup resources               │  │  │
│  │  └─────────────────────────────────────────────────┘  │  │
│  └───────────────────────────────────────────────────────┘  │
│  ┌───────────────────────────────────────────────────────┐  │
│  │         TrackingStatusIndicator Component           │  │  │
│  │  - Visual status display (green/yellow/red/gray)    │  │  │
│  │  - Animated pulse effect                            │  │  │
│  │  - Accessibility attributes                         │  │  │
│  └───────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                            │
                            │ HTTP POST
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                    Backend API Layer                         │
│  POST /tracking/store                                        │
│  - Validates authentication                                  │
│  - Validates coordinates                                     │
│  - Stores TrackingLog record                                 │
└─────────────────────────────────────────────────────────────┘
```

### Component Breakdown

#### 1. useGPSTracking Composable

A Vue 3 composable that encapsulates all GPS tracking logic, providing reactive state and methods for the Driver Dashboard component.

**Responsibilities:**
- Initialize GPS tracking on component mount
- Capture GPS coordinates using Geolocation API
- Transmit coordinates to backend at regular intervals
- Handle errors and update status indicators
- Clean up resources on component unmount

**State:**
- `trackingStatus`: Reactive ref tracking current status ('inactive', 'initializing', 'active', 'error')
- `errorMessage`: Reactive ref storing error messages
- `isTransmitting`: Reactive ref indicating if API request is in progress
- `consecutiveFailures`: Internal counter for failure threshold detection

**Configuration:**
- `TRACKING_INTERVAL`: 30000ms (30 seconds) - configurable
- `GEOLOCATION_TIMEOUT`: 10000ms (10 seconds)
- `GEOLOCATION_MAX_AGE`: 5000ms (5 seconds)
- `MAX_CONSECUTIVE_FAILURES`: 3


#### 2. TrackingStatusIndicator Component

A presentational Vue component that displays the current GPS tracking status with visual indicators.

**Props:**
- `status`: String ('inactive' | 'initializing' | 'active' | 'error')
- `errorMessage`: String (optional)

**Visual States:**
- **Inactive**: Gray circle, "Tracking Inactive" text
- **Initializing**: Yellow circle, "Initializing..." text
- **Active**: Green circle with pulse animation, "Tracking Active" text
- **Error**: Red circle, "Tracking Error" text with error message

**Accessibility:**
- ARIA labels for screen readers
- WCAG AA color contrast compliance
- Semantic HTML structure

#### 3. Driver Dashboard Component Integration

The existing Driver Dashboard component will be enhanced to include GPS tracking functionality.

**Changes:**
- Import and use `useGPSTracking` composable
- Add `TrackingStatusIndicator` component to the template
- Pass active delivery ID to tracking composable for payload inclusion

## Data Models

### GPS Tracking State

```typescript
interface GPSTrackingState {
  trackingStatus: 'inactive' | 'initializing' | 'active' | 'error';
  errorMessage: string | null;
  isTransmitting: boolean;
  consecutiveFailures: number;
}
```

### Geolocation Position

```typescript
interface GeolocationPosition {
  coords: {
    latitude: number;    // -90 to 90
    longitude: number;   // -180 to 180
    accuracy: number;
    altitude: number | null;
    altitudeAccuracy: number | null;
    heading: number | null;
    speed: number | null;
  };
  timestamp: number;
}
```

### Tracking Transmission Payload

```typescript
interface TrackingPayload {
  latitude: number;      // Required, -90 to 90
  longitude: number;     // Required, -180 to 180
  delivery_id: number | null;  // Optional, included if delivery is active
}
```


## Implementation Details

### useGPSTracking Composable Implementation

```javascript
// composables/useGPSTracking.js
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

// Configuration constants
const TRACKING_INTERVAL = 30000; // 30 seconds
const GEOLOCATION_TIMEOUT = 10000; // 10 seconds
const GEOLOCATION_MAX_AGE = 5000; // 5 seconds
const MAX_CONSECUTIVE_FAILURES = 3;

export function useGPSTracking(activeDeliveryId = ref(null)) {
  // Reactive state
  const trackingStatus = ref('inactive');
  const errorMessage = ref(null);
  const isTransmitting = ref(false);
  
  // Internal state
  let trackingIntervalId = null;
  let consecutiveFailures = 0;
  let geolocationWatchId = null;

  // Geolocation options
  const geolocationOptions = {
    enableHighAccuracy: true,
    timeout: GEOLOCATION_TIMEOUT,
    maximumAge: GEOLOCATION_MAX_AGE
  };

  /**
   * Capture current GPS coordinates and send to backend
   */
  const captureAndSendLocation = async () => {
    // Skip if already transmitting (prevent overlapping requests)
    if (isTransmitting.value) {
      console.log('[GPS Tracker] Skipping interval - transmission in progress');
      return;
    }

    // Check if Geolocation API is available
    if (!navigator.geolocation) {
      trackingStatus.value = 'error';
      errorMessage.value = 'Geolocation is not supported by your browser';
      return;
    }

    try {
      // Get current position
      const position = await new Promise((resolve, reject) => {
        navigator.geolocation.getCurrentPosition(resolve, reject, geolocationOptions);
      });

      // Extract coordinates
      const { latitude, longitude } = position.coords;

      // Prepare payload
      const payload = {
        latitude,
        longitude,
        delivery_id: activeDeliveryId.value || null
      };

      // Send to backend
      isTransmitting.value = true;
      const response = await axios.post('/tracking/store', payload);

      // Handle success
      if (response.status === 201) {
        console.log('[GPS Tracker] Location transmitted successfully', payload);
        trackingStatus.value = 'active';
        consecutiveFailures = 0; // Reset failure counter
      }

    } catch (error) {
      handleTransmissionError(error);
    } finally {
      isTransmitting.value = false;
    }
  };

  /**
   * Handle transmission errors
   */
  const handleTransmissionError = (error) => {
    consecutiveFailures++;
    console.error('[GPS Tracker] Transmission failed', error);

    // Check if it's a geolocation error
    if (error.code) {
      handleGeolocationError(error);
      return;
    }

    // Check if it's an authentication error
    if (error.response?.status === 403) {
      trackingStatus.value = 'error';
      errorMessage.value = 'Authentication failed. Please log in again.';
      stopTracking();
      return;
    }

    // Generic error handling
    if (consecutiveFailures >= MAX_CONSECUTIVE_FAILURES) {
      trackingStatus.value = 'error';
      errorMessage.value = `Failed to send location after ${MAX_CONSECUTIVE_FAILURES} attempts`;
      // Note: We don't stop tracking, allowing it to recover when connectivity returns
    }
  };


  /**
   * Handle geolocation-specific errors
   */
  const handleGeolocationError = (error) => {
    trackingStatus.value = 'error';
    
    switch (error.code) {
      case error.PERMISSION_DENIED:
        errorMessage.value = 'Location permission denied. Please enable location access in your browser settings.';
        stopTracking();
        break;
      case error.POSITION_UNAVAILABLE:
        errorMessage.value = 'Location information unavailable. Please check your device settings.';
        break;
      case error.TIMEOUT:
        errorMessage.value = 'Location request timed out. Retrying...';
        break;
      default:
        errorMessage.value = 'An unknown error occurred while getting your location.';
    }
  };

  /**
   * Start GPS tracking
   */
  const startTracking = async () => {
    // Check if Geolocation API is available
    if (!navigator.geolocation) {
      trackingStatus.value = 'error';
      errorMessage.value = 'Geolocation is not supported by your browser';
      return;
    }

    trackingStatus.value = 'initializing';
    errorMessage.value = null;
    consecutiveFailures = 0;

    // Capture and send initial location
    await captureAndSendLocation();

    // Start periodic tracking if initial transmission succeeded
    if (trackingStatus.value !== 'error') {
      trackingIntervalId = setInterval(captureAndSendLocation, TRACKING_INTERVAL);
      console.log('[GPS Tracker] Periodic tracking started');
    }
  };

  /**
   * Stop GPS tracking and clean up resources
   */
  const stopTracking = () => {
    // Clear interval
    if (trackingIntervalId) {
      clearInterval(trackingIntervalId);
      trackingIntervalId = null;
      console.log('[GPS Tracker] Periodic tracking stopped');
    }

    // Clear geolocation watch if exists
    if (geolocationWatchId) {
      navigator.geolocation.clearWatch(geolocationWatchId);
      geolocationWatchId = null;
    }

    // Reset state
    trackingStatus.value = 'inactive';
    consecutiveFailures = 0;
  };

  // Lifecycle hooks
  onMounted(() => {
    console.log('[GPS Tracker] Component mounted, starting tracking');
    startTracking();
  });

  onUnmounted(() => {
    console.log('[GPS Tracker] Component unmounting, stopping tracking');
    stopTracking();
  });

  // Return reactive state and methods
  return {
    trackingStatus,
    errorMessage,
    isTransmitting,
    startTracking,
    stopTracking
  };
}
```


### TrackingStatusIndicator Component Implementation

```vue
<!-- components/TrackingStatusIndicator.vue -->
<script setup>
import { computed } from 'vue';

const props = defineProps({
  status: {
    type: String,
    required: true,
    validator: (value) => ['inactive', 'initializing', 'active', 'error'].includes(value)
  },
  errorMessage: {
    type: String,
    default: null
  }
});

// Compute visual properties based on status
const statusConfig = computed(() => {
  const configs = {
    inactive: {
      color: 'bg-gray-400 dark:bg-gray-600',
      ringColor: 'ring-gray-200 dark:ring-gray-700',
      textColor: 'text-gray-600 dark:text-gray-400',
      label: 'Tracking Inactive',
      icon: 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636',
      pulse: false
    },
    initializing: {
      color: 'bg-yellow-400 dark:bg-yellow-500',
      ringColor: 'ring-yellow-200 dark:ring-yellow-800',
      textColor: 'text-yellow-700 dark:text-yellow-400',
      label: 'Initializing...',
      icon: 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
      pulse: false
    },
    active: {
      color: 'bg-emerald-500 dark:bg-emerald-600',
      ringColor: 'ring-emerald-200 dark:ring-emerald-800',
      textColor: 'text-emerald-700 dark:text-emerald-400',
      label: 'Tracking Active',
      icon: 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z',
      pulse: true
    },
    error: {
      color: 'bg-rose-500 dark:bg-rose-600',
      ringColor: 'ring-rose-200 dark:ring-rose-800',
      textColor: 'text-rose-700 dark:text-rose-400',
      label: 'Tracking Error',
      icon: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
      pulse: false
    }
  };
  return configs[props.status];
});

const ariaLabel = computed(() => {
  if (props.status === 'error' && props.errorMessage) {
    return `${statusConfig.value.label}: ${props.errorMessage}`;
  }
  return statusConfig.value.label;
});
</script>

<template>
  <div 
    class="flex items-center gap-3 px-4 py-3 bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 shadow-sm"
    :aria-label="ariaLabel"
    role="status"
  >
    <!-- Status Indicator Circle -->
    <div class="relative">
      <div 
        :class="[
          'w-3 h-3 rounded-full ring-4',
          statusConfig.color,
          statusConfig.ringColor,
          { 'animate-pulse': statusConfig.pulse }
        ]"
      ></div>
    </div>

    <!-- Status Text -->
    <div class="flex-1">
      <p :class="['text-sm font-semibold', statusConfig.textColor]">
        {{ statusConfig.label }}
      </p>
      <p 
        v-if="status === 'error' && errorMessage" 
        class="text-xs text-gray-600 dark:text-gray-400 mt-0.5"
      >
        {{ errorMessage }}
      </p>
    </div>

    <!-- Status Icon -->
    <svg 
      xmlns="http://www.w3.org/2000/svg" 
      :class="['h-5 w-5', statusConfig.textColor]"
      fill="none" 
      viewBox="0 0 24 24" 
      stroke="currentColor" 
      stroke-width="2"
      aria-hidden="true"
    >
      <path stroke-linecap="round" stroke-linejoin="round" :d="statusConfig.icon" />
    </svg>
  </div>
</template>
```


### Driver Dashboard Integration

```vue
<!-- Pages/Driver/Dashboard.vue -->
<script setup>
import { Head, usePage, router } from '@inertiajs/vue3';
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import CameraCapture from '@/Components/CameraCapture.vue';
import TrackingStatusIndicator from '@/Components/TrackingStatusIndicator.vue';
import { useGPSTracking } from '@/composables/useGPSTracking';

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

// Compute active delivery ID for GPS tracking
const activeDeliveryId = computed(() => {
  const activeDelivery = deliveries.value.find(d => d.status === 'on_way');
  return activeDelivery ? activeDelivery.id : null;
});

// Initialize GPS tracking with active delivery ID
const { trackingStatus, errorMessage, isTransmitting } = useGPSTracking(activeDeliveryId);

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
  <AuthenticatedLayout>
    <Head title="Driver Dashboard" />

    <div class="w-full min-h-[calc(100vh-4rem)] bg-gray-50 dark:bg-slate-900 py-6 sm:py-10">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 text-gray-800 dark:text-gray-200">
        
          <h1 class="text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-white mb-8">
              Hello, {{ user.name }}
          </h1>

          <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
              
              <!-- Left/Top: General Actions -->
              <div class="lg:col-span-1 space-y-6">
                  <!-- GPS Tracking Status Indicator -->
                  <TrackingStatusIndicator 
                    :status="trackingStatus" 
                    :errorMessage="errorMessage"
                  />

                  <!-- Profile Block (existing code) -->
                  <div class="bg-gradient-to-br from-indigo-600 to-indigo-800 rounded-2xl p-6 text-white shadow-lg relative overflow-hidden">
                      <!-- ... existing profile code ... -->
                  </div>

                  <!-- Daily Attendance (existing code) -->
                  <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-gray-100 dark:border-slate-700 p-6">
                      <!-- ... existing attendance code ... -->
                  </div>
              </div>

              <!-- Right/Bottom: Task List (existing code) -->
              <div class="lg:col-span-2">
                  <!-- ... existing deliveries list code ... -->
              </div>
          </div>

      </div>
    </div>

    <CameraCapture 
      v-if="showCameraModal" 
      @close="showCameraModal = false" 
      @capture="handleCapture"
    />
  </AuthenticatedLayout>
</template>
```


## Error Handling Strategy

### Error Categories and Responses

| Error Type | Detection | Response | User Feedback |
|------------|-----------|----------|---------------|
| **Geolocation API Unavailable** | `!navigator.geolocation` | Set status to 'error', disable tracking | "Geolocation is not supported by your browser" |
| **Permission Denied** | `error.code === PERMISSION_DENIED` | Stop tracking, show error | "Location permission denied. Please enable location access in your browser settings." |
| **Position Unavailable** | `error.code === POSITION_UNAVAILABLE` | Retry on next interval | "Location information unavailable. Please check your device settings." |
| **Timeout** | `error.code === TIMEOUT` | Retry on next interval | "Location request timed out. Retrying..." |
| **Network Error** | API request fails | Increment failure counter, retry | No immediate feedback (silent retry) |
| **Authentication Error** | HTTP 403 response | Stop tracking, show error | "Authentication failed. Please log in again." |
| **Consecutive Failures** | 3+ failures in a row | Show warning, continue retrying | "Failed to send location after 3 attempts" |

### Error Recovery Mechanisms

1. **Automatic Retry**: Most errors trigger automatic retry on the next interval
2. **Failure Counter Reset**: Successful transmission resets the consecutive failure counter
3. **Graceful Degradation**: Errors don't crash the dashboard; tracking continues when possible
4. **User Guidance**: Clear, actionable error messages guide users to resolve issues

## Performance Considerations

### Throttling and Optimization

1. **Request Deduplication**: Skip intervals if a transmission is already in progress
2. **Cached Positions**: Use `maximumAge: 5000` to allow recent cached positions
3. **Timeout Management**: 10-second timeout prevents indefinite waiting
4. **Memory Efficiency**: No historical coordinate storage in frontend
5. **Interval Cleanup**: Proper cleanup prevents memory leaks and zombie intervals

### Network Optimization

- **Payload Size**: Minimal payload (latitude, longitude, optional delivery_id)
- **Request Frequency**: 30-second interval balances accuracy and resource usage
- **Silent Failures**: Network errors don't interrupt user workflow

## Security Considerations

1. **Authentication**: All API requests include authentication token via Axios interceptors
2. **Data Validation**: Backend validates coordinate ranges and delivery_id existence
3. **Permission Handling**: Respects browser geolocation permissions
4. **No Sensitive Logging**: Production mode doesn't log coordinate data
5. **HTTPS Required**: Geolocation API requires secure context (HTTPS)

## Accessibility Features

1. **ARIA Labels**: Status indicator includes descriptive aria-label
2. **Role Attributes**: Status indicator uses role="status" for screen readers
3. **Color Contrast**: All status colors meet WCAG AA standards
4. **Semantic HTML**: Proper use of semantic elements
5. **Keyboard Navigation**: No keyboard traps or focus issues
6. **Screen Reader Support**: Status changes announced to assistive technologies


## Testing Strategy

### Unit Tests

**useGPSTracking Composable Tests:**
- Initialization and permission request
- Successful coordinate capture and transmission
- Error handling for each error type
- Cleanup on unmount
- Failure counter and threshold detection
- Request deduplication (skip overlapping intervals)

**TrackingStatusIndicator Component Tests:**
- Renders correct visual state for each status
- Displays error messages when provided
- Includes proper ARIA attributes
- Applies pulse animation when active

### Integration Tests

- Component mount triggers tracking initialization
- Active delivery ID is included in payload
- Backend API receives correct payload format
- Authentication token is included in requests
- Component unmount stops tracking

### Manual Testing Scenarios

1. **Permission Flow**: Test permission grant/deny scenarios
2. **Network Interruption**: Disconnect network and verify recovery
3. **Tab Close**: Verify cleanup when closing browser tab
4. **Mobile Devices**: Test on various mobile browsers
5. **Screen Sizes**: Verify responsive layout
6. **Accessibility**: Test with screen readers

## Deployment Considerations

### Environment Requirements

- **Browser Support**: Modern browsers with Geolocation API support
- **HTTPS**: Required for Geolocation API to function
- **Permissions**: Users must grant location permissions

### Configuration

The following constants can be adjusted in `useGPSTracking.js`:

```javascript
const TRACKING_INTERVAL = 30000;        // Adjust tracking frequency
const GEOLOCATION_TIMEOUT = 10000;      // Adjust geolocation timeout
const GEOLOCATION_MAX_AGE = 5000;       // Adjust cached position age
const MAX_CONSECUTIVE_FAILURES = 3;     // Adjust failure threshold
```

### Monitoring

Recommended monitoring points:
- Tracking initialization success rate
- Transmission success rate
- Average consecutive failures
- Geolocation error frequency
- API response times


## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property Reflection

After analyzing all acceptance criteria, the following properties were identified as providing unique validation value. Several redundant properties were consolidated:

- Properties 4.1, 4.2, and 4.6 all test cleanup behavior and were consolidated into Property 4
- Properties 2.5 and 6.5 both test delivery_id inclusion and were consolidated into Property 2
- Properties 6.3 and 6.4 both test coordinate validation and were consolidated into Property 5

### Property 1: Coordinate Transmission Completeness

*For any* captured GPS coordinates, the system SHALL transmit them to the backend API via POST request.

**Validates: Requirements 2.3**

### Property 2: Active Delivery Payload Inclusion

*For any* active delivery state, the GPS tracking payload SHALL include the delivery_id field.

**Validates: Requirements 2.5, 6.5**

### Property 3: Status Indicator State Consistency

*For any* tracking status value ('active', 'initializing', 'error', 'inactive'), the TrackingStatusIndicator SHALL display the corresponding visual state with correct color, text, and icon.

**Validates: Requirements 3.2, 3.4**

### Property 4: Cleanup Resource Release

*For any* component unmount event, the GPS tracker SHALL clear all active intervals, timers, and geolocation resources.

**Validates: Requirements 4.1, 4.2, 4.3, 4.6**

### Property 5: Coordinate Range Validation

*For any* GPS transmission, the latitude SHALL be a numeric value in the range [-90, 90] and the longitude SHALL be a numeric value in the range [-180, 180].

**Validates: Requirements 6.3, 6.4**

### Property 6: Post-Unmount Transmission Prevention

*For any* unmounted component state, the GPS tracker SHALL not transmit GPS coordinates to the backend API.

**Validates: Requirements 4.4**

### Property 7: Error Recovery Retry

*For any* coordinate capture failure or API transmission failure (excluding permission denial and authentication errors), the GPS tracker SHALL retry on the next tracking interval.

**Validates: Requirements 5.3, 5.4**

### Property 8: Dashboard Stability Under Errors

*For any* error condition (geolocation error, network error, API error), the Driver Dashboard SHALL remain interactive and functional.

**Validates: Requirements 5.6**

### Property 9: Authentication Token Inclusion

*For any* API request to POST /tracking/store, the GPS tracker SHALL include the authentication token in the request headers.

**Validates: Requirements 6.2**

### Property 10: Request Deduplication

*For any* overlapping tracking intervals where a transmission is already in progress, the GPS tracker SHALL skip the next interval and not queue multiple simultaneous API requests.

**Validates: Requirements 7.2, 7.3**

### Property 11: Accessibility Attribute Presence

*For any* TrackingStatusIndicator render state, the component SHALL include an aria-label attribute describing the current status.

**Validates: Requirements 8.3**

