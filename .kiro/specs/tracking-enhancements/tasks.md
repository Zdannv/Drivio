# Implementation Plan: Tracking Enhancements

## Overview

This implementation plan breaks down the tracking enhancements feature into discrete coding tasks. The enhancements focus on three main areas: making idle detection thresholds configurable via environment variables, adding detailed movement metadata to the tracking API response, and implementing comprehensive automated tests.

The implementation follows a logical progression: configuration setup → controller modifications → comprehensive testing → final integration verification.

## Tasks

- [x] 1. Set up configuration infrastructure
  - [x] 1.1 Create tracking configuration file
    - Create `config/tracking.php` with idle_minutes and idle_meters keys
    - Configure environment variable reading with default values (15 minutes, 30 meters)
    - Add type casting to ensure numeric values
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.6, 6.1, 6.2, 6.3, 6.4, 6.5_
  
  - [x] 1.2 Update environment configuration files
    - Add TRACKING_IDLE_MINUTES=15 to `.env.example`
    - Add TRACKING_IDLE_METERS=30 to `.env.example`
    - Add TRACKING_IDLE_MINUTES=15 to `.env`
    - Add TRACKING_IDLE_METERS=30 to `.env`
    - _Requirements: 1.1, 1.2_

- [x] 2. Modify TrackingController for configurable thresholds and metadata
  - [x] 2.1 Update calculateIdleStatus() to use configuration values
    - Replace hardcoded 15-minute threshold with `config('tracking.idle_minutes')`
    - Replace hardcoded 30-meter threshold with `config('tracking.idle_meters')`
    - Ensure all existing logic remains intact
    - _Requirements: 1.5_
  
  - [x] 2.2 Implement calculateMetadata() method
    - Create private method that accepts a User (driver) parameter
    - Calculate minutes_since_last_log using Carbon's diffInMinutes
    - Calculate idle_distance_meters by summing Haversine distances within time window
    - Return array with idle_distance_meters and minutes_since_last_log keys
    - Return null values when driver has no tracking logs
    - Return 0.0 for distance when driver has only one log
    - Round distance to 2 decimal places
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.6, 2.7, 2.8_
  
  - [x] 2.3 Enhance index() method to include metadata
    - Call calculateMetadata() for each driver in the collection
    - Append idle_distance_meters to driver object
    - Append minutes_since_last_log to driver object
    - Maintain existing is_idle field for backward compatibility
    - _Requirements: 2.1, 2.3, 2.5_

- [x] 3. Checkpoint - Verify configuration and controller changes
  - Manually test the index endpoint with Postman or curl
  - Verify response includes new metadata fields
  - Test with different config values in .env
  - Ensure all tests pass, ask the user if questions arise

- [x] 4. Implement Feature Tests for TrackingController
  - [x] 4.1 Create TrackingControllerTest.php file
    - Create `tests/Feature/TrackingControllerTest.php`
    - Add namespace and use statements
    - Add RefreshDatabase trait
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6_
  
  - [ ]* 4.2 Write authorization tests for store endpoint
    - Test that driver can submit tracking data (201 status, database entry created)
    - Test that admin cannot submit tracking data (403 status)
    - _Requirements: 3.1, 3.2, 3.5, 3.6_
  
  - [ ]* 4.3 Write authorization tests for index endpoint
    - Test that admin can retrieve tracking data (200 status, drivers returned)
    - Test that driver cannot retrieve tracking data (403 status)
    - _Requirements: 3.3, 3.4, 3.5, 3.6_
  
  - [ ]* 4.4 Write response format tests
    - Test that index response includes idle_distance_meters field
    - Test that index response includes minutes_since_last_log field
    - Test that index response includes is_idle field
    - Test that index returns null metadata for drivers without logs
    - _Requirements: 2.1, 2.3, 2.5, 2.6, 2.7_
  
  - [ ]* 4.5 Write configuration integration tests
    - Test that idle detection uses configured time threshold (set custom config, verify behavior)
    - Test that idle detection uses configured distance threshold (set custom config, verify behavior)
    - _Requirements: 1.5_

- [x] 5. Implement Unit Tests for Idle Detection Logic
  - [x] 5.1 Create TrackingIdleDetectionTest.php file
    - Create `tests/Unit/TrackingIdleDetectionTest.php`
    - Add namespace and use statements
    - Add RefreshDatabase trait
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6_
  
  - [ ]* 5.2 Write idle detection logic tests
    - Test driver is idle when moving less than threshold (create logs ~10m apart)
    - Test driver is not idle when moving more than threshold (create logs >30m apart)
    - Test driver is not idle when latest log is old (create log outside time window)
    - Test driver is not idle with no logs
    - Test driver is not idle with single log
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6_
  
  - [ ]* 5.3 Write property test for distance calculation accuracy
    - **Property 3: Distance calculation accuracy**
    - **Validates: Requirements 2.8, 4.7**
    - Test haversineDistance with known coordinates (Jakarta to Bandung ~150km)
    - Use reflection to access private method
    - Assert distance matches expected value within 0.1% margin
    - _Requirements: 2.8, 4.7_
  
  - [ ]* 5.4 Write property test for idle distance calculation correctness
    - **Property 4: Idle distance calculation correctness**
    - **Validates: Requirements 2.2**
    - Create multiple tracking logs with known distances
    - Verify idle_distance_meters equals sum of Haversine distances
    - Test with 2, 3, and 5 logs within time window
    - _Requirements: 2.2_
  
  - [ ]* 5.5 Write tests for time elapsed calculation
    - Test minutes_since_last_log calculation with known timestamps
    - Test with logs 5, 15, and 60 minutes old
    - _Requirements: 2.4_
  
  - [ ]* 5.6 Write property test for configuration reads
    - **Property 1: Configuration reads environment variables correctly**
    - **Validates: Requirements 1.1, 1.2, 1.6**
    - Test config reads idle_minutes from environment variable
    - Test config reads idle_meters from environment variable
    - Test config uses default idle_minutes when env var not set
    - Test config uses default idle_meters when env var not set
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.6_

- [~] 6. Checkpoint - Verify all tests pass
  - Run full test suite: `./vendor/bin/sail artisan test`
  - Verify all authorization tests pass
  - Verify all idle detection tests pass
  - Verify all distance calculation tests pass
  - Ensure all tests pass, ask the user if questions arise

- [ ] 7. Final integration and verification
  - [~] 7.1 Test with different configuration values
    - Modify .env with custom TRACKING_IDLE_MINUTES and TRACKING_IDLE_METERS
    - Run tests to verify behavior changes accordingly
    - Reset to default values
    - _Requirements: 1.5_
  
  - [~] 7.2 Verify backward compatibility
    - Test that existing API consumers still work without modifications
    - Verify all existing fields remain in response
    - Confirm new fields are additive only
    - _Requirements: 2.5_
  
  - [ ]* 7.3 Run final test suite verification
    - Execute `./vendor/bin/sail artisan test`
    - Verify 100% of tests pass
    - Check test coverage for new methods
    - _Requirements: 5.3_

- [~] 8. Final checkpoint - Complete implementation verification
  - Ensure all tests pass, ask the user if questions arise

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- All test commands should use Laravel Sail: `./vendor/bin/sail artisan test`
- The design document includes Correctness Properties, so property-based test tasks are included
- Configuration changes require no database migrations
- The implementation maintains full backward compatibility with existing API consumers
- Each task references specific requirements for traceability
- Property tests validate universal correctness properties from the design document
- Unit tests validate specific examples and edge cases

## Task Dependency Graph

```json
{
  "waves": [
    { "id": 0, "tasks": ["1.1", "1.2"] },
    { "id": 1, "tasks": ["2.1", "2.2"] },
    { "id": 2, "tasks": ["2.3"] },
    { "id": 3, "tasks": ["4.1", "5.1"] },
    { "id": 4, "tasks": ["4.2", "4.3", "4.4", "4.5", "5.2", "5.3", "5.4", "5.5", "5.6"] },
    { "id": 5, "tasks": ["7.1", "7.2", "7.3"] }
  ]
}
```
