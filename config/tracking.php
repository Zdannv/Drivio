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
