# Requirements Document

## Introduction

This document specifies the requirements for adding Real-time Tracking and Idle Detection backend logic to the Drivio Laravel application. The system will enable drivers to submit their GPS coordinates in real-time and allow administrators to monitor driver locations with automatic idle status detection based on movement patterns.

## Glossary

- **TrackingController**: The Laravel controller responsible for handling tracking-related HTTP requests
- **TrackingLog**: The Eloquent model representing a GPS coordinate entry in the tracking_logs table
- **Driver**: A user with role='driver' who can submit tracking data
- **Admin**: A user with role='admin' who can view tracking data and idle status
- **Haversine_Formula**: Mathematical formula for calculating great-circle distance between two GPS coordinates
- **Idle_Status**: Boolean indicator showing whether a driver has moved less than 30 meters in the last 15 minutes
- **WGS84**: World Geodetic System 1984, the standard coordinate system for GPS

## Requirements

### Requirement 1

**User Story:** As a driver, I want to submit my current GPS location, so that administrators can track my real-time position during deliveries.

#### Acceptance Criteria

1. THE TrackingController SHALL have a store method that accepts HTTP POST requests
2. WHEN a driver submits tracking data, THE System SHALL validate that the user has role='driver'
3. WHEN a non-driver user attempts to submit tracking data, THE System SHALL return HTTP 403 Forbidden
4. WHEN tracking data is submitted, THE System SHALL validate latitude is between -90 and 90
5. WHEN tracking data is submitted, THE System SHALL validate longitude is between -180 and 180
6. WHEN latitude or longitude validation fails, THE System SHALL return HTTP 422 with validation error messages
7. WHEN valid tracking data is submitted, THE System SHALL save a new TrackingLog record with driver_id set to the authenticated user's ID
8. WHEN valid tracking data with delivery_id is submitted, THE System SHALL save the delivery_id to the TrackingLog record
9. WHEN valid tracking data without delivery_id is submitted, THE System SHALL save the TrackingLog record with delivery_id as null
10. WHEN a TrackingLog is successfully saved, THE System SHALL return HTTP 201 with the created record

### Requirement 2

**User Story:** As an administrator, I want to view all drivers with their latest positions and idle status, so that I can monitor driver activity and identify drivers who may need assistance.

#### Acceptance Criteria

1. THE TrackingController SHALL have an index method that returns driver tracking data
2. WHEN an admin requests tracking data, THE System SHALL return all users with role='driver'
3. WHEN a non-admin user requests tracking data, THE System SHALL return HTTP 403 Forbidden
4. WHEN no drivers exist in the system, THE System SHALL return HTTP 200 with an empty array
5. WHEN tracking data is requested, THE System SHALL include the latest TrackingLog for each driver
6. WHEN tracking data is requested, THE System SHALL calculate idle status for each driver
7. WHEN a driver has no tracking logs, THE System SHALL set is_idle to false
8. WHEN a driver has tracking logs older than 15 minutes, THE System SHALL set is_idle to false
9. WHEN a driver has tracking logs within the last 15 minutes, THE System SHALL fetch all logs from that period
10. WHEN calculating distance, THE System SHALL use the Haversine_Formula to compute total distance between consecutive GPS coordinates
11. WHEN total distance moved is less than 30 meters AND logs exist within 15 minutes, THE System SHALL set is_idle to true
12. WHEN total distance moved is 30 meters or greater, THE System SHALL set is_idle to false
13. WHEN tracking data is returned, THE System SHALL append the is_idle attribute to each driver object
14. WHEN tracking data is returned, THE System SHALL return HTTP 200 with JSON array of drivers

### Requirement 3

**User Story:** As a system administrator, I want the tracking store endpoint to be accessible via a RESTful route, so that mobile applications can submit tracking data using standard HTTP methods.

#### Acceptance Criteria

1. THE System SHALL register a POST route at /tracking/store
2. THE /tracking/store route SHALL be protected by the 'auth' middleware
3. THE /tracking/store route SHALL route requests to TrackingController@store
4. WHEN an unauthenticated user accesses /tracking/store, THE System SHALL return HTTP 401 Unauthorized
