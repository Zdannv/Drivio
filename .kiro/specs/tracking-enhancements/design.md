# Design Document: Tracking Enhancements

## Overview

This document describes the technical design for enhancing the driver tracking system in the Drivio logistics application. The enhancements focus on three main areas:

1. **Configuration Management**: Making idle detection thresholds configurable via environment variables
2. **Enhanced Response Metadata**: Adding detailed movement metrics to the tracking API response
3. **Comprehensive Testing**: Implementing automated tests for authorization, idle detection logic, and distance calculations

The design maintains backward compatibility with the existing tracking system while adding new capabilities for better monitoring and configurability.

## Architecture

### System Overview

```
┌─────────────────────────────────────────────────────────────┐
│                     TrackingController                       │
│  ┌────────────────────────────────────────────────────────┐ │
│  │  store()         - Store GPS tracking data             │ │
│  │  index()         - Retrieve driver tracking data       │ │
│  │  calculateIdleStatus()  - Determine if driver is idle  │ │
│  │  calculateMetadata()    - Calculate movement metrics   │ │
│  │  haversineDistance()    - Calculate GPS distances      │ │
│  └────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
                              │
                              │ uses
                              ▼
┌─────────────────────────────────────────────────────────────┐
│              Configuration Manager (config/tracking.php)     │
│  ┌────────────────────────────────────────────────────────┐ │
│  │  idle_minutes    - Time window for idle detection      │ │
│  │  idle_meters     - Distance threshold for idle status  │ │
│  └────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
                              │
                              │ reads from
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                    Environment Variables                     │
│  ┌────────────────────────────────────────────────────────┐ │
│  │  TRACKING_IDLE_MINUTES  (default: 15)                  │ │
│  │  TRACKING_IDLE_METERS   (default: 30)                  │ │
│  └────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
```


## Components and Interfaces

### Configuration File Structure

### File: `config/tracking.php`

This new configuration file centralizes all tracking-related settings and provides a clean interface for accessing environment variables with sensible defaults.

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Idle Detection Time Window
    |--------------------------------------------------------------------------
    |
    | The time window (in minutes) used to evaluate whether a driver is idle.
    | The system will look at tracking logs within this time period to
    | calculate movement distance.
    |
    */
    'idle_minutes' => (int) env('TRACKING_IDLE_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Idle Detection Distance Threshold
    |--------------------------------------------------------------------------
    |
    | The maximum distance (in meters) a driver can move within the time
    | window to be considered idle. If a driver moves less than this
    | distance, they are marked as idle.
    |
    */
    'idle_meters' => (int) env('TRACKING_IDLE_METERS', 30),
];
```

**Key Design Decisions:**
- Uses `(int)` casting to ensure numeric types are returned
- Provides sensible defaults (15 minutes, 30 meters) based on current hardcoded values
- Follows Laravel's configuration file conventions with descriptive comments
- Accessible via `config('tracking.idle_minutes')` and `config('tracking.idle_meters')`


## Environment Variable Additions

### File: `.env` and `.env.example`

Add the following environment variables to both files:

```env
# Tracking Configuration
TRACKING_IDLE_MINUTES=15
TRACKING_IDLE_METERS=30
```

**Purpose:**
- `TRACKING_IDLE_MINUTES`: Configures the time window for idle detection (default: 15 minutes)
- `TRACKING_IDLE_METERS`: Configures the distance threshold for idle status (default: 30 meters)

**Usage Example:**
- To make idle detection more sensitive: `TRACKING_IDLE_MINUTES=10` and `TRACKING_IDLE_METERS=20`
- To make idle detection less sensitive: `TRACKING_IDLE_MINUTES=20` and `TRACKING_IDLE_METERS=50`


## TrackingController Modifications

### Overview of Changes

The `TrackingController` requires modifications in three areas:
1. Replace hardcoded thresholds with config values
2. Add a new `calculateMetadata()` method to compute movement metrics
3. Enhance the `index()` method to include metadata in the response

### Modified Method: `calculateIdleStatus()`

**Current Implementation:**
```php
private function calculateIdleStatus(User $driver): bool
{
    // ... existing code ...
    $fifteenMinutesAgo = now()->subMinutes(15);  // ← Hardcoded
    // ... existing code ...
    return $totalDistance < 30;  // ← Hardcoded
}
```

**New Implementation:**
```php
private function calculateIdleStatus(User $driver): bool
{
    $latestLog = $driver->trackingLogs()->latest()->first();

    if (!$latestLog) {
        return false;
    }

    $idleMinutes = config('tracking.idle_minutes');  // ← Use config
    $idleMeters = config('tracking.idle_meters');    // ← Use config
    $timeWindowStart = now()->subMinutes($idleMinutes);

    if ($latestLog->created_at < $timeWindowStart) {
        return false;
    }

    $recentLogs = $driver->trackingLogs()
        ->where('created_at', '>=', $timeWindowStart)
        ->orderBy('created_at', 'asc')
        ->get();

    if ($recentLogs->count() < 2) {
        return false;
    }

    $totalDistance = 0;
    for ($i = 1; $i < $recentLogs->count(); $i++) {
        $totalDistance += $this->haversineDistance(
            (float) $recentLogs[$i - 1]->latitude,
            (float) $recentLogs[$i - 1]->longitude,
            (float) $recentLogs[$i]->latitude,
            (float) $recentLogs[$i]->longitude
        );
    }

    return $totalDistance < $idleMeters;
}
```


### New Method: `calculateMetadata()`

This new private method calculates movement metadata for a driver, including distance moved and time since last log.

```php
/**
 * Calculate movement metadata for a driver.
 * 
 * @param User $driver The driver to calculate metadata for
 * @return array{idle_distance_meters: float|null, minutes_since_last_log: float|null}
 */
private function calculateMetadata(User $driver): array
{
    $latestLog = $driver->trackingLogs()->latest()->first();

    // No logs = null metadata
    if (!$latestLog) {
        return [
            'idle_distance_meters' => null,
            'minutes_since_last_log' => null,
        ];
    }

    // Calculate minutes since last log
    $minutesSinceLastLog = now()->diffInMinutes($latestLog->created_at);

    // Calculate distance moved within time window
    $idleMinutes = config('tracking.idle_minutes');
    $timeWindowStart = now()->subMinutes($idleMinutes);

    $recentLogs = $driver->trackingLogs()
        ->where('created_at', '>=', $timeWindowStart)
        ->orderBy('created_at', 'asc')
        ->get();

    // Need at least 2 points to calculate distance
    if ($recentLogs->count() < 2) {
        $idleDistanceMeters = 0.0;
    } else {
        $totalDistance = 0;
        for ($i = 1; $i < $recentLogs->count(); $i++) {
            $totalDistance += $this->haversineDistance(
                (float) $recentLogs[$i - 1]->latitude,
                (float) $recentLogs[$i - 1]->longitude,
                (float) $recentLogs[$i]->latitude,
                (float) $recentLogs[$i]->longitude
            );
        }
        $idleDistanceMeters = $totalDistance;
    }

    return [
        'idle_distance_meters' => round($idleDistanceMeters, 2),
        'minutes_since_last_log' => $minutesSinceLastLog,
    ];
}
```

**Key Design Decisions:**
- Returns `null` for both metrics when driver has no tracking logs
- Returns `0.0` for distance when driver has only one log (can't calculate distance)
- Rounds distance to 2 decimal places for cleaner API responses
- Reuses the same time window logic as `calculateIdleStatus()` for consistency


### Modified Method: `index()`

**Current Implementation:**
```php
public function index(Request $request): JsonResponse
{
    if ($request->user()->role !== 'admin') {
        abort(403);
    }

    $drivers = User::where('role', 'driver')
        ->with(['trackingLogs' => function($q) {
            $q->latest()->take(1);
        }])
        ->get();

    $drivers->each(function ($driver) {
        $driver->is_idle = $this->calculateIdleStatus($driver);
    });

    return response()->json($drivers);
}
```

**New Implementation:**
```php
public function index(Request $request): JsonResponse
{
    if ($request->user()->role !== 'admin') {
        abort(403);
    }

    $drivers = User::where('role', 'driver')
        ->with(['trackingLogs' => function($q) {
            $q->latest()->take(1);
        }])
        ->get();

    $drivers->each(function ($driver) {
        $driver->is_idle = $this->calculateIdleStatus($driver);
        
        // Add metadata
        $metadata = $this->calculateMetadata($driver);
        $driver->idle_distance_meters = $metadata['idle_distance_meters'];
        $driver->minutes_since_last_log = $metadata['minutes_since_last_log'];
    });

    return response()->json($drivers);
}
```

**Changes:**
- Calls `calculateMetadata()` for each driver
- Adds `idle_distance_meters` and `minutes_since_last_log` to the driver object
- Maintains backward compatibility by keeping `is_idle` field


## Response Format Changes

### Enhanced Index Endpoint Response

**Before (Current):**
```json
[
  {
    "id": 1,
    "name": "John Driver",
    "email": "john@example.com",
    "role": "driver",
    "tracking_logs": [
      {
        "id": 123,
        "driver_id": 1,
        "latitude": "-6.200000",
        "longitude": "106.816666",
        "created_at": "2024-01-15T10:30:00.000000Z"
      }
    ],
    "is_idle": true
  }
]
```

**After (Enhanced):**
```json
[
  {
    "id": 1,
    "name": "John Driver",
    "email": "john@example.com",
    "role": "driver",
    "tracking_logs": [
      {
        "id": 123,
        "driver_id": 1,
        "latitude": "-6.200000",
        "longitude": "106.816666",
        "created_at": "2024-01-15T10:30:00.000000Z"
      }
    ],
    "is_idle": true,
    "idle_distance_meters": 15.42,
    "minutes_since_last_log": 3
  }
]
```

**New Fields:**
- `idle_distance_meters` (float|null): Total distance moved within the configured time window
- `minutes_since_last_log` (int|null): Minutes elapsed since the driver's most recent tracking log

**Null Values Example (Driver with no logs):**
```json
[
  {
    "id": 2,
    "name": "Jane Driver",
    "email": "jane@example.com",
    "role": "driver",
    "tracking_logs": [],
    "is_idle": false,
    "idle_distance_meters": null,
    "minutes_since_last_log": null
  }
]
```


## Test File Structure

### Test Organization

Tests will be organized into two main test classes following Laravel's testing conventions:

```
tests/
├── Feature/
│   └── TrackingControllerTest.php    # Integration tests for API endpoints
└── Unit/
    └── TrackingIdleDetectionTest.php  # Unit tests for idle detection logic
```

### File: `tests/Feature/TrackingControllerTest.php`

**Purpose:** Test HTTP endpoints, authorization, and full request/response cycles

**Test Structure:**
```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\TrackingLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TrackingControllerTest extends TestCase
{
    use RefreshDatabase;

    // Authorization Tests
    public function test_driver_can_submit_tracking_data(): void
    public function test_admin_cannot_submit_tracking_data(): void
    public function test_admin_can_retrieve_tracking_data(): void
    public function test_driver_cannot_retrieve_tracking_data(): void

    // Response Format Tests
    public function test_index_includes_idle_distance_meters(): void
    public function test_index_includes_minutes_since_last_log(): void
    public function test_index_includes_is_idle_field(): void
    public function test_index_returns_null_metadata_for_drivers_without_logs(): void

    // Configuration Tests
    public function test_idle_detection_uses_configured_time_threshold(): void
    public function test_idle_detection_uses_configured_distance_threshold(): void
}
```


### File: `tests/Unit/TrackingIdleDetectionTest.php`

**Purpose:** Test idle detection logic, distance calculations, and edge cases

**Test Structure:**
```php
<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\TrackingLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TrackingIdleDetectionTest extends TestCase
{
    use RefreshDatabase;

    // Idle Detection Logic Tests
    public function test_driver_is_idle_when_moving_less_than_threshold(): void
    public function test_driver_is_not_idle_when_moving_more_than_threshold(): void
    public function test_driver_is_not_idle_when_latest_log_is_old(): void
    public function test_driver_is_not_idle_with_no_logs(): void
    public function test_driver_is_not_idle_with_single_log(): void

    // Distance Calculation Tests
    public function test_haversine_distance_calculates_correctly(): void
    public function test_idle_distance_meters_calculation(): void
    public function test_idle_distance_meters_is_zero_with_single_log(): void

    // Time Calculation Tests
    public function test_minutes_since_last_log_calculation(): void

    // Configuration Tests
    public function test_config_reads_idle_minutes_from_env(): void
    public function test_config_reads_idle_meters_from_env(): void
    public function test_config_uses_default_idle_minutes(): void
    public function test_config_uses_default_idle_meters(): void
}
```

**Key Testing Patterns:**

1. **Authorization Tests**: Use `actingAs()` to simulate authenticated users with different roles
2. **Idle Detection Tests**: Create tracking logs with known GPS coordinates and timestamps
3. **Distance Tests**: Use known GPS coordinates with mathematically verifiable distances
4. **Configuration Tests**: Use `Config::set()` to override config values during tests
5. **Edge Case Tests**: Test boundary conditions (no logs, single log, old logs)


## Test Case Examples

### Example 1: Authorization Test

```php
public function test_driver_can_submit_tracking_data(): void
{
    $driver = User::factory()->create(['role' => 'driver']);

    $response = $this->actingAs($driver)->postJson('/api/tracking', [
        'latitude' => -6.200000,
        'longitude' => 106.816666,
    ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('tracking_logs', [
        'driver_id' => $driver->id,
        'latitude' => -6.200000,
        'longitude' => 106.816666,
    ]);
}
```

### Example 2: Idle Detection Test

```php
public function test_driver_is_idle_when_moving_less_than_threshold(): void
{
    $driver = User::factory()->create(['role' => 'driver']);
    
    // Create two tracking logs 10 meters apart, within the time window
    // Jakarta coordinates: approximately 10 meters apart
    TrackingLog::factory()->create([
        'driver_id' => $driver->id,
        'latitude' => -6.200000,
        'longitude' => 106.816666,
        'created_at' => now()->subMinutes(5),
    ]);
    
    TrackingLog::factory()->create([
        'driver_id' => $driver->id,
        'latitude' => -6.200090,  // ~10 meters north
        'longitude' => 106.816666,
        'created_at' => now()->subMinutes(2),
    ]);

    $admin = User::factory()->create(['role' => 'admin']);
    $response = $this->actingAs($admin)->getJson('/api/tracking');

    $response->assertStatus(200);
    $driverData = collect($response->json())->firstWhere('id', $driver->id);
    $this->assertTrue($driverData['is_idle']);
    $this->assertLessThan(30, $driverData['idle_distance_meters']);
}
```


### Example 3: Distance Calculation Test

```php
public function test_haversine_distance_calculates_correctly(): void
{
    $driver = User::factory()->create(['role' => 'driver']);
    
    // Known coordinates with known distance
    // Jakarta to Bandung: approximately 150 km
    $jakartaLat = -6.200000;
    $jakartaLon = 106.816666;
    $bandungLat = -6.914744;
    $bandungLon = 107.609810;
    
    // Use reflection to access private method for testing
    $controller = new \App\Http\Controllers\TrackingController();
    $reflection = new \ReflectionClass($controller);
    $method = $reflection->getMethod('haversineDistance');
    $method->setAccessible(true);
    
    $distance = $method->invoke(
        $controller,
        $jakartaLat,
        $jakartaLon,
        $bandungLat,
        $bandungLon
    );
    
    // Assert distance is approximately 150 km (150,000 meters)
    // Allow 1% margin of error
    $this->assertEqualsWithDelta(150000, $distance, 1500);
}
```

### Example 4: Configuration Test

```php
public function test_idle_detection_uses_configured_time_threshold(): void
{
    // Set custom config value
    Config::set('tracking.idle_minutes', 10);
    
    $driver = User::factory()->create(['role' => 'driver']);
    
    // Create a log 12 minutes ago (outside the 10-minute window)
    TrackingLog::factory()->create([
        'driver_id' => $driver->id,
        'latitude' => -6.200000,
        'longitude' => 106.816666,
        'created_at' => now()->subMinutes(12),
    ]);

    $admin = User::factory()->create(['role' => 'admin']);
    $response = $this->actingAs($admin)->getJson('/api/tracking');

    $driverData = collect($response->json())->firstWhere('id', $driver->id);
    
    // Should not be idle because log is outside the configured time window
    $this->assertFalse($driverData['is_idle']);
}
```


## Data Models

### TrackingLog Model

**Existing Structure** (no changes required):
```php
class TrackingLog extends Model
{
    protected $fillable = [
        'driver_id',
        'delivery_id',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'latitude'  => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}
```

### User Model

**Existing Structure** (no changes required):
```php
class User extends Authenticatable
{
    public function trackingLogs()
    {
        return $this->hasMany(TrackingLog::class, 'driver_id');
    }

    public function isDriver(): bool
    {
        return $this->role === 'driver';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
```

**Note:** The enhanced metadata fields (`idle_distance_meters`, `minutes_since_last_log`) are computed dynamically and appended to the User model in the controller. They are not stored in the database.


## Error Handling

### Configuration Errors

**Scenario:** Invalid environment variable values (non-numeric)

**Handling:**
- The `(int)` casting in `config/tracking.php` will convert invalid values to `0`
- This is acceptable as `0` will cause all drivers to be marked as not idle (safe default)
- For production, consider adding validation in `AppServiceProvider::boot()`:

```php
public function boot(): void
{
    $idleMinutes = config('tracking.idle_minutes');
    $idleMeters = config('tracking.idle_meters');
    
    if ($idleMinutes <= 0 || $idleMeters <= 0) {
        throw new \RuntimeException(
            'Invalid tracking configuration: idle_minutes and idle_meters must be positive integers'
        );
    }
}
```

### API Errors

**Scenario:** Unauthorized access attempts

**Handling:**
- Return HTTP 403 Forbidden with appropriate message
- Already implemented in existing controller

**Scenario:** Invalid GPS coordinates in store endpoint

**Handling:**
- Laravel validation returns HTTP 422 Unprocessable Entity
- Already implemented in existing controller

### Database Errors

**Scenario:** Database connection failure during tracking log retrieval

**Handling:**
- Laravel will throw `QueryException`
- Should be caught by global exception handler
- Return HTTP 500 Internal Server Error


## Performance Considerations

### Database Query Optimization

**Current Approach:**
- Uses eager loading with `with(['trackingLogs'])` to avoid N+1 queries
- Limits eager loaded logs to 1 per driver for the response
- Fetches additional logs within the time window for calculations

**Potential Optimization:**
- For large numbers of drivers, consider caching idle status calculations
- Consider adding a database index on `tracking_logs.created_at` for faster time-based queries
- Consider adding a composite index on `(driver_id, created_at)` for optimal query performance

**Recommended Index:**
```sql
CREATE INDEX idx_tracking_logs_driver_created 
ON tracking_logs(driver_id, created_at DESC);
```

### Calculation Complexity

**Time Complexity:**
- `calculateIdleStatus()`: O(n) where n = number of logs within time window
- `calculateMetadata()`: O(n) where n = number of logs within time window
- `haversineDistance()`: O(1) constant time

**Space Complexity:**
- O(n) for storing recent logs in memory during calculation

**Typical Performance:**
- With default 15-minute window and logs every 30 seconds: ~30 logs per driver
- Distance calculation: ~30 iterations per driver
- For 100 drivers: ~3,000 distance calculations per request
- Expected response time: < 500ms on modern hardware


## Security Considerations

### Authorization

**Endpoint Protection:**
- `store()`: Only drivers can submit tracking data (role check)
- `index()`: Only admins can retrieve tracking data (role check)
- Both endpoints require authentication via Laravel Sanctum/session

**Data Validation:**
- GPS coordinates validated for valid ranges (-90 to 90 for latitude, -180 to 180 for longitude)
- Delivery ID validated against existing deliveries table

### Data Privacy

**Sensitive Information:**
- Tracking logs contain precise location data
- Only accessible to admin users
- Consider implementing data retention policies (e.g., delete logs older than 90 days)

**Recommendations:**
- Add rate limiting to prevent tracking data spam
- Consider encrypting GPS coordinates at rest
- Implement audit logging for admin access to tracking data

### Configuration Security

**Environment Variables:**
- Configuration values are read-only at runtime
- Cannot be modified via API
- Require server access to change

**Validation:**
- Consider adding validation to prevent unreasonable values (e.g., idle_minutes > 1440 or idle_meters > 10000)


## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Configuration reads environment variables correctly

*For any* valid numeric value set in TRACKING_IDLE_MINUTES or TRACKING_IDLE_METERS environment variables, the configuration system SHALL return that exact numeric value when accessed via `config('tracking.idle_minutes')` or `config('tracking.idle_meters')`.

**Validates: Requirements 1.1, 1.2, 1.6**

### Property 2: Idle detection uses configured thresholds

*For any* configured idle threshold values, when a driver's movement is calculated, the idle status SHALL be determined using those configured values rather than hardcoded constants.

**Validates: Requirements 1.5**

### Property 3: Distance calculation accuracy

*For any* two valid GPS coordinate pairs, the haversineDistance function SHALL return a distance value that matches the mathematically correct Haversine formula result within a 0.1% margin of error.

**Validates: Requirements 2.8, 4.7**


### Property 4: Idle distance calculation correctness

*For any* set of tracking logs within the configured time window, the calculated idle_distance_meters SHALL equal the sum of Haversine distances between consecutive log points.

**Validates: Requirements 2.2**

### Property 5: Time elapsed calculation correctness

*For any* tracking log with a known creation timestamp, the minutes_since_last_log value SHALL equal the difference in minutes between the current time and the log's creation time.

**Validates: Requirements 2.4**

### Property 6: Response includes required metadata fields

*For any* driver returned by the index endpoint, the response SHALL include the fields is_idle, idle_distance_meters, and minutes_since_last_log.

**Validates: Requirements 2.1, 2.3, 2.5**

### Property 7: Idle status determination for low movement

*For any* driver whose total movement within the configured time window is less than the configured distance threshold, and whose latest log is within the time window, and who has at least two logs, the is_idle field SHALL be true.

**Validates: Requirements 4.1**

### Property 8: Non-idle status determination for high movement

*For any* driver whose total movement within the configured time window exceeds the configured distance threshold, the is_idle field SHALL be false.

**Validates: Requirements 4.2**


## Testing Strategy

### Test Approach

The testing strategy follows a dual approach combining unit tests and integration tests:

**Unit Tests** (`tests/Unit/TrackingIdleDetectionTest.php`):
- Test idle detection logic in isolation
- Test distance calculation functions with known GPS coordinates
- Test configuration reading with various environment variable values
- Test edge cases (no logs, single log, old logs)

**Integration Tests** (`tests/Feature/TrackingControllerTest.php`):
- Test full HTTP request/response cycles
- Test authorization for different user roles
- Test response format and metadata inclusion
- Test configuration integration with actual API calls

### Test Data Strategy

**GPS Coordinates:**
- Use real-world coordinates (Jakarta, Bandung) for realistic distance calculations
- Use coordinates with known distances for verification
- Test edge cases: equator, poles, date line crossing

**Timestamps:**
- Use Carbon's `now()->subMinutes()` for relative time testing
- Test boundary conditions (exactly at threshold, just before, just after)

**User Roles:**
- Create test users with `User::factory()->create(['role' => 'driver'])`
- Create test users with `User::factory()->create(['role' => 'admin'])`

### Test Execution

**Running Tests:**
```bash
# Run all tests
./vendor/bin/sail artisan test

# Run specific test file
./vendor/bin/sail artisan test tests/Feature/TrackingControllerTest.php

# Run specific test method
./vendor/bin/sail artisan test --filter test_driver_can_submit_tracking_data
```

**Test Coverage Goals:**
- 100% coverage of new methods (`calculateMetadata()`)
- 100% coverage of modified methods (`calculateIdleStatus()`, `index()`)
- All acceptance criteria have corresponding tests

## Implementation Checklist

### Phase 1: Configuration Setup
- [ ] Create `config/tracking.php` with idle_minutes and idle_meters keys
- [ ] Add TRACKING_IDLE_MINUTES and TRACKING_IDLE_METERS to `.env.example`
- [ ] Add TRACKING_IDLE_MINUTES and TRACKING_IDLE_METERS to `.env`
- [ ] Verify config values are accessible via `config('tracking.idle_minutes')`

### Phase 2: Controller Modifications
- [ ] Modify `calculateIdleStatus()` to use config values instead of hardcoded thresholds
- [ ] Implement `calculateMetadata()` method
- [ ] Modify `index()` method to include metadata in response
- [ ] Test manually with Postman/curl to verify response format

### Phase 3: Test Implementation
- [ ] Create `tests/Feature/TrackingControllerTest.php`
- [ ] Implement authorization tests (4 tests)
- [ ] Implement response format tests (4 tests)
- [ ] Implement configuration integration tests (2 tests)
- [ ] Create `tests/Unit/TrackingIdleDetectionTest.php`
- [ ] Implement idle detection logic tests (5 tests)
- [ ] Implement distance calculation tests (3 tests)
- [ ] Implement time calculation tests (1 test)
- [ ] Implement configuration unit tests (4 tests)

### Phase 4: Verification
- [ ] Run full test suite: `./vendor/bin/sail artisan test`
- [ ] Verify all tests pass
- [ ] Test with different config values to ensure configurability works
- [ ] Verify backward compatibility (existing API consumers still work)

### Phase 5: Documentation
- [ ] Update API documentation with new response fields
- [ ] Document environment variable configuration options
- [ ] Add inline code comments for new methods


## Backward Compatibility

### API Compatibility

**Guaranteed Compatibility:**
- The `index()` endpoint response format is backward compatible
- All existing fields remain unchanged
- New fields are added without removing or modifying existing fields
- Existing API consumers will continue to work without modifications

**Breaking Changes:**
- None

### Configuration Compatibility

**Migration Path:**
- If environment variables are not set, defaults match current hardcoded values (15 minutes, 30 meters)
- Existing deployments will behave identically until environment variables are explicitly configured
- No database migrations required

### Testing Compatibility

**New Tests:**
- All new tests are additive
- Existing tests (if any) remain unchanged
- Test suite can be run incrementally during development

## Future Enhancements

### Potential Improvements

1. **Caching Layer**
   - Cache idle status calculations for 30 seconds to reduce database load
   - Invalidate cache when new tracking logs are submitted

2. **Real-time Updates**
   - Implement WebSocket support for real-time tracking updates
   - Push idle status changes to admin dashboard

3. **Historical Analytics**
   - Add endpoint to retrieve idle time statistics over custom date ranges
   - Generate reports on driver activity patterns

4. **Geofencing**
   - Add support for defining geographic zones
   - Alert when drivers enter/exit specific zones

5. **Route Optimization**
   - Use tracking data to suggest optimal routes
   - Identify frequently traveled paths



---

# GPS Tracking on Driver Dashboard

## Overview

This section describes the technical design for implementing real-time GPS tracking on the Driver Dashboard. The feature enables automatic location tracking when drivers are checked in, with intelligent throttling to optimize battery usage and network bandwidth, visual status indicators, and proper cleanup on component unmount.

## Feature Requirements

### Core Functionality
1. **Automatic GPS Tracking**: Start tracking when driver checks in, stop when they check out
2. **Throttled Updates**: Send location updates every 30 seconds (configurable)
3. **Visual Status Indicator**: Show tracking status (active, inactive, error)
4. **Proper Cleanup**: Clear intervals and stop tracking on component unmount
5. **Error Handling**: Handle GPS permission denials and geolocation errors gracefully

### User Experience Goals
- Minimal battery drain through throttled updates
- Clear visual feedback on tracking status
- Seamless integration with existing check-in/check-out flow
- No manual intervention required from drivers

## Architecture

### Component Structure

```
Driver Dashboard (Dashboard.vue)
├── GPS Tracking State
│   ├── isTracking (ref<boolean>)
│   ├── trackingStatus (ref<'active' | 'inactive' | 'error'>)
│   ├── lastTrackingTime (ref<Date | null>)
│   └── trackingError (ref<string | null>)
├── GPS Tracking Logic
│   ├── startTracking()
│   ├── stopTracking()
│   ├── sendLocationUpdate()
│   └── handleGeolocationError()
├── Lifecycle Hooks
│   ├── onMounted() - Initialize tracking if checked in
│   └── onUnmounted() - Cleanup intervals and stop tracking
└── Visual Components
    └── Tracking Status Indicator
```

### Data Flow

```
┌─────────────────────────────────────────────────────────────┐
│                    Driver Dashboard                          │
│                                                              │
│  ┌────────────────────────────────────────────────────────┐ │
│  │  Check In Button Clicked                               │ │
│  │         │                                               │ │
│  │         ▼                                               │ │
│  │  startTracking()                                       │ │
│  │         │                                               │ │
│  │         ├─► Set isTracking = true                      │ │
│  │         ├─► Set trackingStatus = 'active'              │ │
│  │         └─► Start 30s interval                         │ │
│  │                  │                                      │ │
│  │                  ▼                                      │ │
│  │         Every 30 seconds:                              │ │
│  │         sendLocationUpdate()                           │ │
│  │                  │                                      │ │
│  │                  ├─► Get GPS coordinates               │ │
│  │                  ├─► POST /api/tracking                │ │
│  │                  └─► Update lastTrackingTime           │ │
│  │                                                         │ │
│  │  Check Out Button Clicked                              │ │
│  │         │                                               │ │
│  │         ▼                                               │ │
│  │  stopTracking()                                        │ │
│  │         │                                               │ │
│  │         ├─► Clear interval                             │ │
│  │         ├─► Set isTracking = false                     │ │
│  │         └─► Set trackingStatus = 'inactive'            │ │
│  └────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
                              │
                              │ HTTP POST
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                  TrackingController::store()                 │
│  ┌────────────────────────────────────────────────────────┐ │
│  │  Validate GPS coordinates                              │ │
│  │  Create TrackingLog record                             │ │
│  │  Return 201 Created                                    │ │
│  └────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
```


## Implementation Details

### State Management

Add the following reactive state to the Driver Dashboard component:

```vue
<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue';

// Existing imports and state...

/* ----------------------------------
   GPS TRACKING STATE
----------------------------------- */
const isTracking = ref(false);
const trackingStatus = ref('inactive'); // 'active' | 'inactive' | 'error'
const lastTrackingTime = ref(null);
const trackingError = ref(null);
const trackingIntervalId = ref(null);

// Configuration
const TRACKING_INTERVAL_MS = 30000; // 30 seconds
</script>
```

**State Variables:**
- `isTracking`: Boolean flag indicating if GPS tracking is currently active
- `trackingStatus`: String enum for visual indicator ('active', 'inactive', 'error')
- `lastTrackingTime`: Timestamp of the last successful location update
- `trackingError`: Error message if geolocation fails
- `trackingIntervalId`: Reference to the setInterval timer for cleanup
- `TRACKING_INTERVAL_MS`: Configurable interval for location updates (30 seconds)

### Core Tracking Functions

#### Function: `startTracking()`

Initiates GPS tracking when the driver checks in.

```vue
const startTracking = () => {
    // Prevent multiple tracking sessions
    if (isTracking.value) {
        console.warn('Tracking already active');
        return;
    }

    // Check if geolocation is supported
    if (!navigator.geolocation) {
        trackingStatus.value = 'error';
        trackingError.value = 'Geolocation is not supported by your browser';
        alert('❌ GPS tracking is not supported on this device');
        return;
    }

    // Start tracking
    isTracking.value = true;
    trackingStatus.value = 'active';
    trackingError.value = null;

    // Send initial location immediately
    sendLocationUpdate();

    // Set up interval for periodic updates
    trackingIntervalId.value = setInterval(() => {
        sendLocationUpdate();
    }, TRACKING_INTERVAL_MS);

    console.log('✅ GPS tracking started');
};
```

**Key Design Decisions:**
- Checks for existing tracking session to prevent duplicates
- Validates browser geolocation support before starting
- Sends immediate location update on start (don't wait 30 seconds)
- Uses `setInterval` for periodic updates
- Stores interval ID for cleanup


#### Function: `stopTracking()`

Stops GPS tracking when the driver checks out or component unmounts.

```vue
const stopTracking = () => {
    if (!isTracking.value) {
        return;
    }

    // Clear the interval
    if (trackingIntervalId.value) {
        clearInterval(trackingIntervalId.value);
        trackingIntervalId.value = null;
    }

    // Reset state
    isTracking.value = false;
    trackingStatus.value = 'inactive';
    lastTrackingTime.value = null;

    console.log('🛑 GPS tracking stopped');
};
```

**Key Design Decisions:**
- Safely clears interval even if called multiple times
- Resets all tracking-related state
- Sets status to 'inactive' for visual indicator
- Idempotent (safe to call multiple times)

#### Function: `sendLocationUpdate()`

Retrieves current GPS coordinates and sends them to the backend.

```vue
const sendLocationUpdate = async () => {
    try {
        // Get current position
        navigator.geolocation.getCurrentPosition(
            async (position) => {
                const { latitude, longitude } = position.coords;

                try {
                    // Send to backend
                    await axios.post('/api/tracking', {
                        latitude,
                        longitude,
                        delivery_id: selectedDeliveryId.value // Include if driver is on a delivery
                    });

                    // Update success state
                    lastTrackingTime.value = new Date();
                    trackingStatus.value = 'active';
                    trackingError.value = null;

                    console.log(`📍 Location updated: ${latitude}, ${longitude}`);
                } catch (error) {
                    console.error('Failed to send location to server:', error);
                    trackingStatus.value = 'error';
                    trackingError.value = 'Failed to send location to server';
                }
            },
            (error) => {
                handleGeolocationError(error);
            },
            {
                enableHighAccuracy: false, // Use network location for battery efficiency
                timeout: 10000, // 10 second timeout
                maximumAge: 5000 // Accept cached position up to 5 seconds old
            }
        );
    } catch (error) {
        console.error('Unexpected error in sendLocationUpdate:', error);
        trackingStatus.value = 'error';
        trackingError.value = 'Unexpected error occurred';
    }
};
```

**Key Design Decisions:**
- Uses `getCurrentPosition` instead of `watchPosition` for better control
- Sets `enableHighAccuracy: false` to save battery (network location is sufficient)
- 10-second timeout prevents hanging requests
- Accepts cached positions up to 5 seconds old for efficiency
- Includes `delivery_id` if driver is currently on a delivery
- Updates `lastTrackingTime` on success for UI display
- Handles both geolocation errors and network errors separately


#### Function: `handleGeolocationError()`

Handles geolocation API errors with user-friendly messages.

```vue
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

    // Optionally stop tracking on permission denial
    if (error.code === error.PERMISSION_DENIED) {
        stopTracking();
        alert(`❌ ${errorMessage}`);
    }
};
```

**Error Codes:**
- `PERMISSION_DENIED` (1): User denied location access - stop tracking and alert
- `POSITION_UNAVAILABLE` (2): GPS unavailable - set error status but keep trying
- `TIMEOUT` (3): Request timed out - set error status but keep trying

**Key Design Decisions:**
- Provides user-friendly error messages
- Stops tracking permanently on permission denial
- Continues tracking on temporary errors (timeout, unavailable)
- Alerts user on critical errors (permission denied)

### Lifecycle Integration

#### onMounted Hook

Initialize tracking if driver is already checked in when component loads.

```vue
onMounted(() => {
    fetchDeliveries();

    // Start tracking if driver is already checked in
    if (props.isCheckedIn && !props.hasCheckedOut) {
        startTracking();
    }
});
```

**Purpose:**
- Handles page refresh scenarios where driver is already checked in
- Ensures tracking resumes automatically after navigation
- Prevents tracking if driver has already checked out

#### onUnmounted Hook

Clean up tracking resources when component is destroyed.

```vue
onUnmounted(() => {
    // Always stop tracking and clear intervals on component unmount
    stopTracking();
});
```

**Purpose:**
- Prevents memory leaks from lingering intervals
- Ensures clean component teardown
- Critical for SPA navigation (prevents multiple tracking sessions)


### Integration with Check-In/Check-Out Flow

Modify the existing `handleCapture` function to start/stop tracking:

```vue
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
```

**Integration Points:**
- Call `startTracking()` after successful check-in
- Call `stopTracking()` after successful check-out
- No changes needed for proof of delivery (tracking continues)

### Visual Status Indicator

Add a tracking status indicator to the UI:

```vue
<template>
  <!-- ... existing template ... -->

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

    <!-- ... rest of existing template ... -->
  </div>
</template>
```


**Visual Indicator Features:**
- **Active State** (Green): Pulsing green dot, shows last update time
- **Inactive State** (Gray): Static gray dot, shows "paused" message
- **Error State** (Red): Red dot, shows error message
- **Conditional Display**: Only shows when driver is checked in
- **Responsive Design**: Adapts to light/dark mode
- **Icon Animation**: Pulsing animation on active state for visual feedback

### Helper Function: Time Formatting

Add a helper function to format the last tracking time:

```vue
const formatTimeAgo = (date) => {
    if (!date) return '';
    
    const seconds = Math.floor((new Date() - date) / 1000);
    
    if (seconds < 60) return `${seconds}s ago`;
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
    return `${Math.floor(seconds / 3600)}h ago`;
};
```

**Purpose:**
- Displays human-readable time since last update
- Updates automatically as time passes
- Shows seconds, minutes, or hours based on elapsed time

## Configuration

### Environment Variables

Add tracking configuration to `.env`:

```env
# GPS Tracking Configuration
TRACKING_INTERVAL_SECONDS=30
```

**Usage in Component:**
```vue
// Read from environment or use default
const TRACKING_INTERVAL_MS = (import.meta.env.VITE_TRACKING_INTERVAL_SECONDS || 30) * 1000;
```

**Purpose:**
- Allows administrators to adjust tracking frequency without code changes
- Default: 30 seconds (balance between accuracy and battery life)
- Can be increased for battery savings or decreased for more frequent updates

### Recommended Intervals

| Interval | Use Case | Battery Impact | Data Accuracy |
|----------|----------|----------------|---------------|
| 15s | High-priority deliveries | High | Excellent |
| 30s | Standard operations (recommended) | Medium | Good |
| 60s | Battery-saving mode | Low | Acceptable |
| 120s | Minimal tracking | Very Low | Basic |


## Error Handling

### Geolocation Errors

| Error Type | Code | Handling Strategy | User Impact |
|------------|------|-------------------|-------------|
| Permission Denied | 1 | Stop tracking, alert user | Must enable location manually |
| Position Unavailable | 2 | Keep trying, show error status | Temporary - may resolve |
| Timeout | 3 | Keep trying, show error status | Temporary - may resolve |

### Network Errors

**Scenario:** Backend API is unreachable

**Handling:**
```vue
try {
    await axios.post('/api/tracking', { latitude, longitude });
} catch (error) {
    if (error.response?.status === 403) {
        // Authorization error - stop tracking
        stopTracking();
        alert('❌ You are not authorized to submit tracking data');
    } else if (error.response?.status === 422) {
        // Validation error - log but continue
        console.error('Invalid GPS coordinates:', error.response.data);
    } else {
        // Network error - log but continue trying
        console.error('Network error, will retry on next interval:', error);
        trackingStatus.value = 'error';
        trackingError.value = 'Network error - will retry';
    }
}
```

**Strategy:**
- **403 Forbidden**: Stop tracking (authorization issue)
- **422 Validation Error**: Log error, continue tracking (coordinate issue)
- **Network Errors**: Log error, continue tracking (temporary issue)
- **500 Server Error**: Log error, continue tracking (backend issue)

### Browser Compatibility

**Geolocation API Support:**
- ✅ Chrome 5+
- ✅ Firefox 3.5+
- ✅ Safari 5+
- ✅ Edge (all versions)
- ✅ Mobile browsers (iOS Safari, Chrome Mobile)

**Fallback for Unsupported Browsers:**
```vue
if (!navigator.geolocation) {
    trackingStatus.value = 'error';
    trackingError.value = 'Geolocation is not supported by your browser';
    alert('❌ GPS tracking is not supported on this device');
    return;
}
```

## Performance Considerations

### Battery Optimization

**Strategies Implemented:**
1. **Throttled Updates**: 30-second intervals instead of continuous tracking
2. **Low Accuracy Mode**: `enableHighAccuracy: false` uses network location
3. **Cached Positions**: `maximumAge: 5000` accepts recent cached positions
4. **Timeout**: 10-second timeout prevents hanging requests

**Expected Battery Impact:**
- Minimal impact with 30-second intervals
- Approximately 2-5% additional battery drain per hour
- Comparable to navigation apps in background mode

### Network Optimization

**Data Usage per Update:**
- Request payload: ~100 bytes (latitude, longitude, delivery_id)
- Response payload: ~50 bytes (success confirmation)
- Total per update: ~150 bytes

**Daily Data Usage Estimate:**
- 8-hour shift = 960 updates (30s intervals)
- Total data: ~144 KB per day
- Negligible impact on mobile data plans

### Memory Management

**Cleanup Strategy:**
- Clear interval on component unmount
- Reset all refs to prevent memory leaks
- No persistent watchers or event listeners

**Memory Footprint:**
- Minimal: ~5 state variables
- No large data structures
- Interval cleared properly on cleanup


## Security Considerations

### Location Data Privacy

**Data Transmission:**
- All location data sent over HTTPS
- Authenticated requests only (Laravel Sanctum/session)
- Driver role verification on backend

**Data Storage:**
- Location data stored in `tracking_logs` table
- Associated with driver_id and optional delivery_id
- No client-side storage of location history

**Access Control:**
- Only authenticated drivers can submit location data
- Only admins can retrieve location data
- Backend enforces role-based access control

### Permission Handling

**Browser Permissions:**
- Request location permission on first tracking attempt
- Handle permission denial gracefully
- Provide clear instructions for enabling permissions

**User Consent:**
- Tracking only active when driver is checked in
- Driver can check out to stop tracking
- Clear visual indicator of tracking status

## Testing Strategy

### Manual Testing Checklist

**Basic Functionality:**
- [ ] Tracking starts automatically on check-in
- [ ] Tracking stops automatically on check-out
- [ ] Location updates sent every 30 seconds
- [ ] Visual indicator shows correct status
- [ ] Last update time displays correctly

**Error Scenarios:**
- [ ] Permission denied: Shows error, stops tracking
- [ ] GPS unavailable: Shows error, continues trying
- [ ] Network error: Shows error, continues trying
- [ ] Backend 403: Stops tracking, alerts user

**Lifecycle:**
- [ ] Tracking resumes after page refresh (if checked in)
- [ ] Tracking stops on component unmount
- [ ] No duplicate intervals created
- [ ] No memory leaks after multiple check-in/out cycles

**UI/UX:**
- [ ] Status indicator visible when checked in
- [ ] Status indicator hidden when checked out
- [ ] Colors match tracking status (green/gray/red)
- [ ] Pulsing animation on active status
- [ ] Dark mode styling correct

### Browser Testing

**Desktop Browsers:**
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)

**Mobile Browsers:**
- [ ] iOS Safari
- [ ] Chrome Mobile (Android)
- [ ] Samsung Internet

### Performance Testing

**Metrics to Monitor:**
- [ ] Battery drain over 1-hour period
- [ ] Network data usage over 1-hour period
- [ ] Memory usage (check for leaks)
- [ ] CPU usage during tracking

**Acceptance Criteria:**
- Battery drain < 5% per hour
- Data usage < 20 KB per hour
- No memory leaks after 10 check-in/out cycles
- CPU usage < 5% average


## Implementation Checklist

### Phase 1: State Setup
- [ ] Add GPS tracking state variables to Dashboard.vue
- [ ] Add TRACKING_INTERVAL_MS configuration constant
- [ ] Import necessary Vue composition functions (onMounted, onUnmounted)

### Phase 2: Core Functions
- [ ] Implement `startTracking()` function
- [ ] Implement `stopTracking()` function
- [ ] Implement `sendLocationUpdate()` function
- [ ] Implement `handleGeolocationError()` function
- [ ] Implement `formatTimeAgo()` helper function

### Phase 3: Lifecycle Integration
- [ ] Add tracking initialization to `onMounted()` hook
- [ ] Add cleanup to `onUnmounted()` hook
- [ ] Integrate `startTracking()` into check-in flow
- [ ] Integrate `stopTracking()` into check-out flow

### Phase 4: UI Components
- [ ] Add GPS tracking status indicator component
- [ ] Style active state (green, pulsing)
- [ ] Style inactive state (gray)
- [ ] Style error state (red)
- [ ] Add dark mode support
- [ ] Test responsive design

### Phase 5: Configuration
- [ ] Add VITE_TRACKING_INTERVAL_SECONDS to .env
- [ ] Add VITE_TRACKING_INTERVAL_SECONDS to .env.example
- [ ] Document configuration options

### Phase 6: Testing
- [ ] Test check-in starts tracking
- [ ] Test check-out stops tracking
- [ ] Test page refresh resumes tracking
- [ ] Test component unmount cleanup
- [ ] Test permission denial handling
- [ ] Test network error handling
- [ ] Test visual indicator states
- [ ] Test on multiple browsers
- [ ] Test on mobile devices

### Phase 7: Documentation
- [ ] Update README with GPS tracking feature
- [ ] Document environment variables
- [ ] Add troubleshooting guide for common issues

## Complete Code Example

Here's the complete modified Dashboard.vue with GPS tracking:

```vue
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

// Configuration
const TRACKING_INTERVAL_MS = (import.meta.env.VITE_TRACKING_INTERVAL_SECONDS || 30) * 1000;

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

    sendLocationUpdate();

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
                    await axios.post('/api/tracking', {
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

/* ----------------------------------
   LIFECYCLE HOOKS
----------------------------------- */
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
```


### Template with GPS Status Indicator

```vue
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
                <!-- Profile Block -->
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

                <!-- Daily Attendance -->
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
```

## Summary

This design document provides a complete technical specification for implementing GPS tracking on the Driver Dashboard with the following key features:

1. **Automatic Tracking**: Starts on check-in, stops on check-out
2. **Throttled Updates**: 30-second intervals for battery efficiency
3. **Visual Status Indicator**: Clear feedback with three states (active, inactive, error)
4. **Proper Cleanup**: Lifecycle hooks ensure no memory leaks
5. **Error Handling**: Graceful handling of permissions, network, and GPS errors
6. **Battery Optimized**: Low accuracy mode, cached positions, reasonable intervals
7. **Security**: HTTPS, authentication, role-based access control
8. **Responsive UI**: Works on desktop and mobile, light/dark mode support

The implementation is production-ready and follows Vue 3 Composition API best practices.
