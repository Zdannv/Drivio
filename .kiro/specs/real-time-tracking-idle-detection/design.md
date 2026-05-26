# Design Document: Real-time Tracking and Idle Detection

## Overview

This design extends the existing TrackingController to support real-time GPS tracking submission by drivers and automatic idle status detection for administrators. The system uses the Haversine formula to calculate distance traveled and determines idle status based on movement patterns within a 15-minute window.

## Architecture

### Components

1. **TrackingController** (existing, to be extended)
   - `store()` - New method for drivers to submit GPS coordinates
   - `index()` - Existing method to be enhanced with idle detection logic

2. **TrackingLog Model** (existing)
   - Eloquent model for tracking_logs table
   - Fields: id, driver_id, delivery_id, latitude, longitude, created_at, updated_at

3. **Route Configuration** (routes/web.php)
   - New POST route: `/tracking/store`
   - Existing GET route: `/tracking/latest` (already mapped to index)

### Data Flow

#### Storing Tracking Data
```
Driver Mobile App → POST /tracking/store → auth middleware → 
TrackingController@store → Validate role='driver' → 
Validate coordinates → Save TrackingLog → Return 201 JSON
```

#### Retrieving Tracking Data with Idle Status
```
Admin Dashboard → GET /tracking/latest → auth middleware →
TrackingController@index → Verify role='admin' →
Fetch all drivers → Load recent tracking logs →
Calculate idle status per driver → Return 200 JSON
```

## Component Design

### TrackingController::store()

**Purpose:** Accept and validate GPS coordinates from authenticated drivers.

**Method Signature:**
```php
public function store(Request $request): JsonResponse
```

**Validation Rules:**
```php
[
    'latitude' => 'required|numeric|min:-90|max:90',
    'longitude' => 'required|numeric|min:-180|max:180',
    'delivery_id' => 'nullable|exists:deliveries,id'
]
```

**Authorization:**
- User must be authenticated (enforced by 'auth' middleware)
- User must have role='driver' (checked in method)
- Return 403 if user is not a driver

**Logic:**
1. Validate user role is 'driver'
2. Validate request data against rules
3. Create TrackingLog record:
   - driver_id: authenticated user's ID
   - latitude: from request
   - longitude: from request
   - delivery_id: from request (nullable)
4. Return 201 with created record

**Response Format:**
```json
{
    "id": 123,
    "driver_id": 45,
    "delivery_id": 67,
    "latitude": "-6.200000",
    "longitude": "106.816666",
    "created_at": "2024-01-15T10:30:00.000000Z",
    "updated_at": "2024-01-15T10:30:00.000000Z"
}
```

### TrackingController::index()

**Purpose:** Return all drivers with their latest position and calculated idle status.

**Method Signature:**
```php
public function index(Request $request): JsonResponse
```

**Authorization:**
- User must be authenticated (enforced by 'auth' middleware)
- User must have role='admin' (checked in method)
- Return 403 if user is not an admin

**Enhanced Logic:**
1. Validate user role is 'admin'
2. Fetch all users with role='driver'
3. Eager load latest tracking log for each driver
4. For each driver, calculate idle status:
   - If no tracking logs exist: `is_idle = false`
   - If latest log is older than 15 minutes: `is_idle = false`
   - If logs exist within last 15 minutes:
     - Fetch all logs from last 15 minutes
     - Calculate total distance using Haversine formula
     - If distance < 30 meters: `is_idle = true`
     - If distance >= 30 meters: `is_idle = false`
5. Append `is_idle` attribute to each driver object
6. Return 200 with JSON array of drivers

**Idle Detection Algorithm:**

```php
function calculateIdleStatus(User $driver): bool
{
    $latestLog = $driver->trackingLogs()->latest()->first();
    
    if (!$latestLog) {
        return false; // No logs = not idle
    }
    
    $fifteenMinutesAgo = now()->subMinutes(15);
    
    if ($latestLog->created_at < $fifteenMinutesAgo) {
        return false; // Old logs = not idle
    }
    
    $recentLogs = $driver->trackingLogs()
        ->where('created_at', '>=', $fifteenMinutesAgo)
        ->orderBy('created_at', 'asc')
        ->get();
    
    if ($recentLogs->count() < 2) {
        return false; // Need at least 2 points to calculate distance
    }
    
    $totalDistance = 0;
    for ($i = 1; $i < $recentLogs->count(); $i++) {
        $totalDistance += haversineDistance(
            $recentLogs[$i-1]->latitude,
            $recentLogs[$i-1]->longitude,
            $recentLogs[$i]->latitude,
            $recentLogs[$i]->longitude
        );
    }
    
    return $totalDistance < 30; // Idle if moved less than 30 meters
}
```

**Haversine Formula Implementation:**

```php
function haversineDistance(
    float $lat1, 
    float $lon1, 
    float $lat2, 
    float $lon2
): float {
    $earthRadius = 6371000; // Earth's radius in meters
    
    $latDelta = deg2rad($lat2 - $lat1);
    $lonDelta = deg2rad($lon2 - $lon1);
    
    $a = sin($latDelta / 2) * sin($latDelta / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($lonDelta / 2) * sin($lonDelta / 2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    
    return $earthRadius * $c; // Distance in meters
}
```

**Response Format:**
```json
[
    {
        "id": 45,
        "name": "John Driver",
        "email": "john@example.com",
        "role": "driver",
        "tracking_logs": [
            {
                "id": 123,
                "driver_id": 45,
                "latitude": "-6.200000",
                "longitude": "106.816666",
                "created_at": "2024-01-15T10:30:00.000000Z"
            }
        ],
        "is_idle": true
    }
]
```

## Route Configuration

**New Route:**
```php
Route::middleware('auth')->group(function () {
    Route::post('/tracking/store', [TrackingController::class, 'store'])
        ->name('tracking.store');
});
```

**Existing Route (no changes needed):**
```php
Route::get('/tracking/latest', [TrackingController::class, 'index'])
    ->name('tracking.latest');
```

## Error Handling

### Validation Errors (422)
```json
{
    "message": "The latitude field must be between -90 and 90.",
    "errors": {
        "latitude": ["The latitude field must be between -90 and 90."]
    }
}
```

### Authorization Errors (403)
```json
{
    "message": "Forbidden"
}
```

### Authentication Errors (401)
```json
{
    "message": "Unauthenticated."
}
```

## Database Considerations

**No schema changes required.** The existing `tracking_logs` table already has all necessary fields:
- id (primary key)
- driver_id (foreign key to users)
- delivery_id (nullable foreign key to deliveries)
- latitude (decimal)
- longitude (decimal)
- created_at (timestamp)
- updated_at (timestamp)

**Index Recommendations:**
- Existing index on `driver_id` for efficient driver lookups
- Consider adding composite index on `(driver_id, created_at)` for efficient recent log queries

## Performance Considerations

1. **Idle Calculation Overhead:** The idle detection algorithm runs for each driver on every admin request. For systems with many drivers, consider:
   - Caching idle status with short TTL (30-60 seconds)
   - Background job to pre-calculate idle status
   - Pagination for driver list

2. **Database Queries:** The current design uses N+1 queries (one per driver for recent logs). Consider:
   - Eager loading with constraints
   - Single query to fetch all recent logs for all drivers

3. **Haversine Calculation:** Pure PHP implementation is sufficient for small datasets. For large-scale systems, consider:
   - Database-level spatial functions (MySQL ST_Distance_Sphere)
   - Caching distance calculations

## Security Considerations

1. **Role-Based Access Control:**
   - Only drivers can store tracking data
   - Only admins can view tracking data
   - Middleware enforces authentication

2. **Input Validation:**
   - Coordinates validated against WGS84 bounds
   - delivery_id validated against existing deliveries
   - Prevents injection of invalid data

3. **Data Privacy:**
   - Tracking data only accessible to admins
   - Drivers cannot view other drivers' locations
   - Consider data retention policy for old tracking logs

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Driver Role Authorization

*For any* authenticated user attempting to store tracking data, the system SHALL accept the request if and only if the user has role='driver', returning 403 for all other roles.

**Validates: Requirements 1.2, 1.3**

### Property 2: Coordinate Validation Bounds

*For any* tracking submission, the system SHALL accept latitude values between -90 and 90 (inclusive) and longitude values between -180 and 180 (inclusive), returning 422 validation error for values outside these ranges.

**Validates: Requirements 1.4, 1.5, 1.6**

### Property 3: Tracking Data Persistence

*For any* valid tracking submission from a driver, the system SHALL create a TrackingLog record with the authenticated user's ID as driver_id, the submitted coordinates, and the optional delivery_id (or null if not provided), returning 201 with the created record.

**Validates: Requirements 1.7, 1.8, 1.9, 1.10**

### Property 4: Admin Authorization for Tracking View

*For any* authenticated user attempting to view tracking data, the system SHALL return driver data if and only if the user has role='admin', returning 403 for all other roles.

**Validates: Requirements 2.2, 2.3**

### Property 5: Latest Tracking Log Inclusion

*For any* driver with one or more tracking logs, the system SHALL include the most recent TrackingLog (by created_at timestamp) in the tracking data response.

**Validates: Requirements 2.5**

### Property 6: Idle Status for No Logs

*For any* driver with zero tracking logs, the system SHALL set is_idle to false in the tracking data response.

**Validates: Requirements 2.7**

### Property 7: Idle Status for Old Logs

*For any* driver whose most recent tracking log has created_at timestamp older than 15 minutes from the current time, the system SHALL set is_idle to false.

**Validates: Requirements 2.8**

### Property 8: Recent Logs Retrieval

*For any* driver with tracking logs within the last 15 minutes, the system SHALL fetch all TrackingLog records where created_at is greater than or equal to 15 minutes ago for idle calculation.

**Validates: Requirements 2.9**

### Property 9: Haversine Distance Calculation

*For any* two GPS coordinate pairs (lat1, lon1) and (lat2, lon2), the distance calculation SHALL use the Haversine formula with Earth's radius of 6,371,000 meters, producing results within 0.5% of the true great-circle distance.

**Validates: Requirements 2.10**

### Property 10: Idle Threshold Detection

*For any* driver with tracking logs in the last 15 minutes, the system SHALL set is_idle to true if and only if the total distance calculated between consecutive coordinates (ordered by created_at) is less than 30 meters.

**Validates: Requirements 2.11, 2.12**

### Property 11: Idle Attribute Presence

*For any* driver in the tracking data response, the JSON object SHALL contain an is_idle attribute with a boolean value.

**Validates: Requirements 2.13**

### Property 12: Successful Response Format

*For any* successful tracking data request by an admin, the system SHALL return HTTP 200 with a JSON array containing driver objects.

**Validates: Requirements 2.14**

## Testing Strategy

### Unit Tests
- Test Haversine formula with known coordinate pairs
- Test idle calculation logic with various scenarios
- Test validation rules for edge cases
- Test role authorization logic

### Integration Tests
- Test route registration and middleware application
- Test end-to-end tracking submission flow
- Test end-to-end tracking retrieval with idle detection
- Test authentication and authorization flows

### Property-Based Tests
- Generate random valid/invalid coordinates to test validation
- Generate random user roles to test authorization
- Generate random tracking sequences to test idle detection
- Generate random time windows to test 15-minute boundary

### Edge Cases to Test
- Driver with no tracking logs
- Driver with exactly one tracking log
- Driver with logs exactly at 15-minute boundary
- Distance exactly at 30-meter threshold
- Empty driver list
- Null delivery_id handling

## Implementation Notes

1. **Helper Functions:** The Haversine distance calculation and idle status logic should be extracted into helper functions or a service class for reusability and testability.

2. **Code Organization:** Consider creating a `TrackingService` class to encapsulate idle detection logic, keeping the controller thin.

3. **Response Transformation:** Use Laravel API Resources or custom transformers to consistently format JSON responses.

4. **Middleware:** The existing 'auth' middleware handles authentication. Role checks are performed within controller methods.

5. **Timestamps:** Use Laravel's `now()` helper and Carbon for all timestamp operations to ensure consistency and testability.
