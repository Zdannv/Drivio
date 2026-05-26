# Implementation Plan: Real-time Tracking and Idle Detection

## Overview

This implementation adds real-time GPS tracking submission for drivers and automatic idle status detection for administrators. The system uses the Haversine formula to calculate distance traveled and determines idle status based on movement within a 15-minute window.

## Tasks

- [ ] 1. Add route for tracking data submission
  - Add POST route `/tracking/store` in routes/web.php
  - Protect route with 'auth' middleware
  - Map route to TrackingController@store
  - _Requirements: 3.1, 3.2, 3.3_

- [ ] 2. Implement TrackingController::store() method
  - [ ] 2.1 Create store method with role validation and coordinate validation
    - Validate authenticated user has role='driver' (return 403 if not)
    - Validate latitude between -90 and 90
    - Validate longitude between -180 and 180
    - Validate optional delivery_id exists in deliveries table
    - Create TrackingLog record with driver_id, latitude, longitude, delivery_id
    - Return 201 JSON response with created record
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 1.8, 1.9, 1.10_
  
  - [ ]* 2.2 Write property test for driver role authorization
    - **Property 1: Driver Role Authorization**
    - **Validates: Requirements 1.2, 1.3**
  
  - [ ]* 2.3 Write property test for coordinate validation
    - **Property 2: Coordinate Validation Bounds**
    - **Validates: Requirements 1.4, 1.5, 1.6**
  
  - [ ]* 2.4 Write property test for tracking data persistence
    - **Property 3: Tracking Data Persistence**
    - **Validates: Requirements 1.7, 1.8, 1.9, 1.10**

- [ ] 3. Create Haversine distance calculation helper
  - [ ] 3.1 Implement haversineDistance() helper function
    - Create helper function in app/Helpers or as TrackingController private method
    - Accept four parameters: lat1, lon1, lat2, lon2
    - Use Earth radius of 6,371,000 meters
    - Implement Haversine formula: convert to radians, calculate deltas, compute distance
    - Return distance in meters as float
    - _Requirements: 2.10_
  
  - [ ]* 3.2 Write property test for Haversine distance calculation
    - **Property 9: Haversine Distance Calculation**
    - **Validates: Requirements 2.10**

- [ ] 4. Implement idle status calculation logic
  - [ ] 4.1 Create calculateIdleStatus() method in TrackingController
    - Accept User (driver) as parameter
    - Return boolean indicating idle status
    - Fetch latest tracking log for driver
    - Return false if no logs exist
    - Return false if latest log is older than 15 minutes
    - Fetch all logs from last 15 minutes ordered by created_at
    - Return false if fewer than 2 logs (cannot calculate distance)
    - Calculate total distance between consecutive logs using haversineDistance()
    - Return true if total distance < 30 meters, false otherwise
    - _Requirements: 2.6, 2.7, 2.8, 2.9, 2.11, 2.12_
  
  - [ ]* 4.2 Write property test for idle status with no logs
    - **Property 6: Idle Status for No Logs**
    - **Validates: Requirements 2.7**
  
  - [ ]* 4.3 Write property test for idle status with old logs
    - **Property 7: Idle Status for Old Logs**
    - **Validates: Requirements 2.8**
  
  - [ ]* 4.4 Write property test for recent logs retrieval
    - **Property 8: Recent Logs Retrieval**
    - **Validates: Requirements 2.9**
  
  - [ ]* 4.5 Write property test for idle threshold detection
    - **Property 10: Idle Threshold Detection**
    - **Validates: Requirements 2.11, 2.12**

- [ ] 5. Update TrackingController::index() method with idle detection
  - [ ] 5.1 Enhance index method to calculate and append idle status
    - Keep existing admin role validation (return 403 if not admin)
    - Keep existing driver query with latest tracking log
    - For each driver, call calculateIdleStatus() to get idle status
    - Append is_idle attribute to each driver object in response
    - Return 200 JSON response with array of drivers including is_idle
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.13, 2.14_
  
  - [ ]* 5.2 Write property test for admin authorization
    - **Property 4: Admin Authorization for Tracking View**
    - **Validates: Requirements 2.2, 2.3**
  
  - [ ]* 5.3 Write property test for latest tracking log inclusion
    - **Property 5: Latest Tracking Log Inclusion**
    - **Validates: Requirements 2.5**
  
  - [ ]* 5.4 Write property test for idle attribute presence
    - **Property 11: Idle Attribute Presence**
    - **Validates: Requirements 2.13**
  
  - [ ]* 5.5 Write property test for successful response format
    - **Property 12: Successful Response Format**
    - **Validates: Requirements 2.14**

- [ ] 6. Checkpoint - Ensure all tests pass
  - Run all tests to verify implementation
  - Test manually with Postman or similar tool
  - Verify route is accessible at POST /tracking/store
  - Verify GET /tracking/latest returns drivers with is_idle attribute
  - Ask the user if questions arise

- [ ]* 7. Write integration tests for end-to-end flows
  - [ ]* 7.1 Write integration test for unauthenticated access
    - Test POST /tracking/store without authentication returns 401
    - **Validates: Requirements 3.4**
  
  - [ ]* 7.2 Write integration test for complete tracking submission flow
    - Test driver can submit tracking data and receive 201 response
    - Test non-driver receives 403 when attempting to submit
    - Test invalid coordinates receive 422 validation errors
  
  - [ ]* 7.3 Write integration test for complete tracking retrieval flow
    - Test admin can retrieve tracking data with idle status
    - Test non-admin receives 403 when attempting to retrieve
    - Test empty driver list returns 200 with empty array

## Notes

- Tasks marked with `*` are optional and can be skipped for faster MVP
- Each task references specific requirements for traceability
- Property tests validate universal correctness properties from the design document
- Integration tests verify end-to-end flows and middleware behavior
- The Haversine formula uses Earth's radius of 6,371,000 meters for accuracy
- Idle detection requires at least 2 tracking points within 15 minutes to calculate distance
