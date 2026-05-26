# Requirements Document

## Introduction

This document specifies the requirements for enhancing the existing driver tracking feature in the Drivio logistics application. The enhancements include making idle detection thresholds configurable via environment variables, enriching the tracking response with metadata, and implementing automated tests to ensure reliability and correctness of the tracking system.

## Glossary

- **Tracking_System**: The subsystem responsible for storing and retrieving driver location data
- **Idle_Detection_Engine**: The component that determines whether a driver is idle based on movement and time thresholds
- **Configuration_Manager**: The component that reads and provides configuration values from environment variables
- **Admin_User**: A user with role 'admin' who can view tracking data
- **Driver_User**: A user with role 'driver' who can submit tracking data
- **Tracking_Log**: A record containing driver location (latitude, longitude) and timestamp
- **Idle_Threshold**: The maximum distance in meters a driver can move to be considered idle
- **Time_Window**: The duration in minutes used to evaluate idle status
- **Haversine_Formula**: The mathematical formula used to calculate distance between GPS coordinates
- **Test_Suite**: The collection of automated tests that verify tracking functionality

## Requirements

### Requirement 1: Configurable Idle Detection Thresholds

**User Story:** As a system administrator, I want to configure idle detection thresholds via environment variables, so that I can adjust the sensitivity of idle detection without modifying code.

#### Acceptance Criteria

1. THE Configuration_Manager SHALL read the idle time threshold from the TRACKING_IDLE_MINUTES environment variable
2. THE Configuration_Manager SHALL read the idle distance threshold from the TRACKING_IDLE_METERS environment variable
3. WHEN the TRACKING_IDLE_MINUTES environment variable is not set, THE Configuration_Manager SHALL use 15 minutes as the default value
4. WHEN the TRACKING_IDLE_METERS environment variable is not set, THE Configuration_Manager SHALL use 30 meters as the default value
5. THE Idle_Detection_Engine SHALL use the Configuration_Manager values instead of hardcoded thresholds
6. THE Configuration_Manager SHALL provide numeric values for both thresholds

### Requirement 2: Enhanced Tracking Response Metadata

**User Story:** As an administrator, I want to see detailed movement metadata for each driver, so that I can better understand driver activity patterns and make informed decisions.

#### Acceptance Criteria

1. WHEN an Admin_User requests the latest tracking data, THE Tracking_System SHALL include idle_distance_meters for each driver
2. THE idle_distance_meters value SHALL represent the total distance moved within the Time_Window
3. WHEN an Admin_User requests the latest tracking data, THE Tracking_System SHALL include minutes_since_last_log for each driver
4. THE minutes_since_last_log value SHALL represent the time elapsed since the driver's most recent Tracking_Log
5. THE Tracking_System SHALL continue to include the existing is_idle boolean value
6. WHEN a driver has no Tracking_Log entries, THE Tracking_System SHALL return null for idle_distance_meters
7. WHEN a driver has no Tracking_Log entries, THE Tracking_System SHALL return null for minutes_since_last_log
8. THE Tracking_System SHALL calculate idle_distance_meters using the Haversine_Formula

### Requirement 3: Tracking Endpoint Authorization Tests

**User Story:** As a developer, I want automated tests for endpoint authorization, so that I can ensure only authorized users can access tracking endpoints.

#### Acceptance Criteria

1. THE Test_Suite SHALL verify that a Driver_User can successfully submit tracking data to the store endpoint
2. THE Test_Suite SHALL verify that an Admin_User cannot submit tracking data to the store endpoint
3. THE Test_Suite SHALL verify that an Admin_User can successfully retrieve tracking data from the index endpoint
4. THE Test_Suite SHALL verify that a Driver_User cannot retrieve tracking data from the index endpoint
5. WHEN an unauthorized user attempts to access an endpoint, THE Test_Suite SHALL verify that a 403 status code is returned
6. THE Test_Suite SHALL use Laravel's authentication system to simulate authenticated users

### Requirement 4: Idle Detection Logic Tests

**User Story:** As a developer, I want automated tests for idle detection logic, so that I can ensure the Idle_Detection_Engine correctly identifies idle and active drivers.

#### Acceptance Criteria

1. THE Test_Suite SHALL verify that a driver is marked as idle when they move less than the Idle_Threshold within the Time_Window
2. THE Test_Suite SHALL verify that a driver is marked as not idle when they move more than the Idle_Threshold within the Time_Window
3. THE Test_Suite SHALL verify that a driver is marked as not idle when their latest Tracking_Log is older than the Time_Window
4. THE Test_Suite SHALL verify that a driver is marked as not idle when they have no Tracking_Log entries
5. THE Test_Suite SHALL verify that a driver is marked as not idle when they have only one Tracking_Log entry within the Time_Window
6. THE Test_Suite SHALL use sample GPS coordinates to create test Tracking_Log entries
7. THE Test_Suite SHALL verify that the Haversine_Formula correctly calculates distances between GPS coordinates

### Requirement 5: Test Infrastructure and Execution

**User Story:** As a developer, I want a properly configured test infrastructure, so that I can run tests reliably using Laravel Sail.

#### Acceptance Criteria

1. THE Test_Suite SHALL use PHPUnit as the testing framework
2. THE Test_Suite SHALL use Laravel's RefreshDatabase trait to ensure test isolation
3. THE Test_Suite SHALL be executable using the command "./vendor/bin/sail artisan test"
4. THE Test_Suite SHALL use database transactions to prevent test data from persisting
5. THE Test_Suite SHALL create test users with appropriate roles for authorization testing
6. THE Test_Suite SHALL clean up test data after each test execution

### Requirement 6: Configuration File Structure

**User Story:** As a developer, I want a dedicated configuration file for tracking settings, so that configuration values are centralized and easily maintainable.

#### Acceptance Criteria

1. THE Configuration_Manager SHALL read values from a config/tracking.php file
2. THE config/tracking.php file SHALL contain an 'idle_minutes' key that reads from TRACKING_IDLE_MINUTES
3. THE config/tracking.php file SHALL contain an 'idle_meters' key that reads from TRACKING_IDLE_METERS
4. THE Configuration_Manager SHALL be accessible via Laravel's config() helper function
5. THE config/tracking.php file SHALL follow Laravel's configuration file conventions
