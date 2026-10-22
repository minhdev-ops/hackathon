<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Quorum quản trị (FR-QRM-02)
    |--------------------------------------------------------------------------
    |
    | Số admin tối thiểu phải duyệt một Change Request trước khi thực thi.
    */

    'threshold' => (int) env('QUORUM_THRESHOLD', 2),

    /*
    |--------------------------------------------------------------------------
    | FR-QRM-04 fallback: bắt buộc xác thực lại trước khi duyệt.
    */

    'reauth_minutes' => (int) env('QUORUM_REAUTH_MINUTES', 5),

    /*
    |--------------------------------------------------------------------------
    | FR-QRM-05: bật khi đã cấu hình QEA cho realm (TideCloak).
    */

    'qea_enabled' => (bool) env('TIDECLOAK_QEA_ENABLED', false),

];
