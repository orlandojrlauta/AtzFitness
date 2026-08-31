<?php
/**
 * ATZ Fitness Gym Management System
 * Admin Dashboard
 */

$page_title = "Admin Dashboard";

require_once '../includes/db.php';
require_once '../includes/auth.php';

require_role(['Administrator', 'Staff']);


// =====================================================
// DASHBOARD METRICS
// =====================================================

// Total active members
$total_members = mysqli_fetch_assoc(
    mysqli_query(
        $conn,
        "SELECT COUNT(*) AS cnt
         FROM members
         WHERE status = 'Active'"
    )
)['cnt'] ?? 0;


// Memberships expiring within 7 days
$expiring_count = mysqli_fetch_assoc(
    mysqli_query(
        $conn,
        "SELECT COUNT(*) AS cnt
         FROM memberships
         WHERE status = 'Active'
         AND end_date BETWEEN CURDATE()
         AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)"
    )
)['cnt'] ?? 0;


// Today's attendance
$today_attendance = mysqli_fetch_assoc(
    mysqli_query(
        $conn,
        "SELECT COUNT(*) AS cnt
         FROM attendance
         WHERE date = CURDATE()"
    )
)['cnt'] ?? 0;


// Today's revenue
$today_revenue = mysqli_fetch_assoc(
    mysqli_query(
        $conn,
        "SELECT SUM(amount) AS total
         FROM payments
         WHERE DATE(payment_date) = CURDATE()
         AND status = 'Paid'"
    )
)['total'] ?? 0.00;


// Total revenue
$total_revenue_alltime = mysqli_fetch_assoc(
    mysqli_query(
        $conn,
        "SELECT SUM(amount) AS total
         FROM payments
         WHERE status = 'Paid'"
    )
)['total'] ?? 0.00;


// Active membership plans
$active_plans = mysqli_fetch_assoc(
    mysqli_query(
        $conn,
        "SELECT COUNT(*) AS cnt
         FROM membership_plans
         WHERE status = 'Active'"
    )
)['cnt'] ?? 0;


// =====================================================
// RECENT ACTIVITY LOGS
// =====================================================

$recent_logs = null;

if ($_SESSION['role'] === 'Administrator') {

    $recent_logs = mysqli_query(
        $conn,
        "SELECT *
         FROM activity_logs
         ORDER BY created_at DESC
         LIMIT 5"
    );
}


// =====================================================
// MONTHLY REVENUE TREND
// LAST 8 MONTHS
// =====================================================

$revenue_by_month = [];

for ($i = 7; $i >= 0; $i--) {

    $ym = date(
        'Y-m',
        strtotime("-$i months")
    );

    $revenue_by_month[$ym] = [
        'label' => date(
            'M',
            strtotime("-$i months")
        ),
        'total' => 0.0
    ];
}


$rev_trend_res = mysqli_query(
    $conn,
    "SELECT
        DATE_FORMAT(payment_date, '%Y-%m') AS ym,
        SUM(amount) AS total
     FROM payments
     WHERE status = 'Paid'
     AND payment_date >= DATE_SUB(
         CURDATE(),
         INTERVAL 7 MONTH
     )
     GROUP BY ym"
);


if ($rev_trend_res) {

    while ($row = mysqli_fetch_assoc(
        $rev_trend_res
    )) {

        if (
            isset(
                $revenue_by_month[
                    $row['ym']
                ]
            )
        ) {

            $revenue_by_month[
                $row['ym']
            ]['total'] =
                (float)$row['total'];
        }
    }
}


$revenue_chart_labels = array_column(
    $revenue_by_month,
    'label'
);


$revenue_chart_data = array_column(
    $revenue_by_month,
    'total'
);


// =====================================================
// MEMBERSHIP DISTRIBUTION
// =====================================================

$distribution = [
    'Regular' => 0,
    'Student' => 0
];


$dist_res = mysqli_query(
    $conn,
    "SELECT
        member_type,
        COUNT(*) AS cnt
     FROM members
     WHERE status = 'Active'
     GROUP BY member_type"
);


if ($dist_res) {

    while ($row = mysqli_fetch_assoc(
        $dist_res
    )) {

        if (
            isset(
                $distribution[
                    $row['member_type']
                ]
            )
        ) {

            $distribution[
                $row['member_type']
            ] = (int)$row['cnt'];
        }
    }
}


// =====================================================
// PYTHON ANALYTICS INPUT
// =====================================================

$analytics_input = [

    'daily_revenue' => [],

    'daily_attendance' => [],

    'attendance_hours' => [],

    'member_types' => $distribution,

    'member_stats' => []

];


// =====================================================
// DAILY REVENUE
// LAST 60 DAYS
// (30 days current period + 30 days previous period)
// =====================================================

for ($d = 59; $d >= 0; $d--) {

    $day = date(
        'Y-m-d',
        strtotime("-$d days")
    );

    $analytics_input[
        'daily_revenue'
    ][$day] = [

        'date' => $day,

        'revenue' => 0

    ];
}


$revenue_daily_res = mysqli_query(
    $conn,
    "SELECT
        DATE(payment_date) AS day,
        SUM(amount) AS total
     FROM payments
     WHERE status = 'Paid'
     AND payment_date >= DATE_SUB(
         CURDATE(),
         INTERVAL 59 DAY
     )
     GROUP BY DATE(payment_date)
     ORDER BY day ASC"
);


if ($revenue_daily_res) {

    while ($row = mysqli_fetch_assoc(
        $revenue_daily_res
    )) {

        if (
            isset(
                $analytics_input[
                    'daily_revenue'
                ][$row['day']]
            )
        ) {

            $analytics_input[
                'daily_revenue'
            ][$row['day']]['revenue'] =
                (float)$row['total'];
        }
    }
}


// Convert associative array to normal array
$analytics_input[
    'daily_revenue'
] = array_values(
    $analytics_input[
        'daily_revenue'
    ]
);


// =====================================================
// DAILY ATTENDANCE
// LAST 30 DAYS
// =====================================================

for ($d = 29; $d >= 0; $d--) {

    $day = date(
        'Y-m-d',
        strtotime("-$d days")
    );

    $analytics_input[
        'daily_attendance'
    ][$day] = 0;
}


// Get attendance count per day
$attendance_daily_res = mysqli_query(
    $conn,
    "SELECT
        date AS day,
        COUNT(*) AS total
     FROM attendance
     WHERE date >= DATE_SUB(
         CURDATE(),
         INTERVAL 29 DAY
     )
     GROUP BY date
     ORDER BY date ASC"
);


if ($attendance_daily_res) {

    while ($row = mysqli_fetch_assoc(
        $attendance_daily_res
    )) {

        $day = $row['day'];

        if (
            isset(
                $analytics_input[
                    'daily_attendance'
                ][$day]
            )
        ) {

            $analytics_input[
                'daily_attendance'
            ][$day] =
                (int)$row['total'];
        }
    }
}


// Convert attendance to normal array
$analytics_input[
    'daily_attendance'
] = array_values(
    $analytics_input[
        'daily_attendance'
    ]
);


// =====================================================
// ATTENDANCE BY HOUR
// =====================================================

$analytics_input[
    'attendance_hours'
] = [];


$attendance_hour_res = mysqli_query(
    $conn,
    "SELECT
        HOUR(check_in_time) AS hour,
        COUNT(*) AS total
     FROM attendance
     WHERE date >= DATE_SUB(
         CURDATE(),
         INTERVAL 29 DAY
     )
     AND check_in_time IS NOT NULL
     GROUP BY HOUR(check_in_time)
     ORDER BY hour ASC"
);


if ($attendance_hour_res) {

    while ($row = mysqli_fetch_assoc(
        $attendance_hour_res
    )) {

        $hour = (int)$row['hour'];

        $analytics_input[
            'attendance_hours'
        ][(string)$hour] =
            (int)$row['total'];
    }
}


// =====================================================
// MEMBER ANALYTICS
// =====================================================

$member_stats = [

    'active_members' => 0,

    'new_members_30_days' => 0,

    'expiring_7_days' => 0,

    'new_members_by_day' => []

];


// -----------------------------------------------------
// Total active members
// -----------------------------------------------------

$member_res = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM members
     WHERE status = 'Active'"
);


if ($member_res) {

    $row = mysqli_fetch_assoc(
        $member_res
    );

    $member_stats[
        'active_members'
    ] =
        (int)(
            $row['total'] ?? 0
        );
}


// -----------------------------------------------------
// New members in last 30 days
// -----------------------------------------------------

$new_member_res = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM members
     WHERE created_at >= DATE_SUB(
         CURDATE(),
         INTERVAL 29 DAY
     )"
);


if ($new_member_res) {

    $row = mysqli_fetch_assoc(
        $new_member_res
    );

    $member_stats[
        'new_members_30_days'
    ] =
        (int)(
            $row['total'] ?? 0
        );
}


// -----------------------------------------------------
// Memberships expiring within 7 days
// -----------------------------------------------------

$expiring_res = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM memberships
     WHERE status = 'Active'
     AND end_date BETWEEN CURDATE()
     AND DATE_ADD(
         CURDATE(),
         INTERVAL 7 DAY
     )"
);


if ($expiring_res) {

    $row = mysqli_fetch_assoc(
        $expiring_res
    );

    $member_stats[
        'expiring_7_days'
    ] =
        (int)(
            $row['total'] ?? 0
        );
}


// -----------------------------------------------------
// New members per day
// -----------------------------------------------------

for ($d = 29; $d >= 0; $d--) {

    $day = date(
        'Y-m-d',
        strtotime("-$d days")
    );

    $member_stats[
        'new_members_by_day'
    ][$day] = 0;
}


$new_members_daily = mysqli_query(
    $conn,
    "SELECT
        DATE(created_at) AS day,
        COUNT(*) AS total
     FROM members
     WHERE created_at >= DATE_SUB(
         CURDATE(),
         INTERVAL 29 DAY
     )
     GROUP BY DATE(created_at)"
);


if ($new_members_daily) {

    while ($row = mysqli_fetch_assoc(
        $new_members_daily
    )) {

        if (
            isset(
                $member_stats[
                    'new_members_by_day'
                ][$row['day']]
            )
        ) {

            $member_stats[
                'new_members_by_day'
            ][$row['day']] =
                (int)$row['total'];
        }
    }
}


// Add member analytics
$analytics_input[
    'member_stats'
] = $member_stats;


// =====================================================
// MEMBER TYPES
// =====================================================

$analytics_input[
    'member_types'
] = $distribution;


// =====================================================
// RUN PYTHON ANALYTICS
// =====================================================

// Change this if Python is not available as "python"
$python = 'python';


// Python script location
$python_script =
    __DIR__
    . '/../python/dashboard_analytics.py';


// Build command
$command =
    escapeshellcmd(
        $python
        . ' '
        . $python_script
    );


// Pipes
$descriptorspec = [

    0 => [
        'pipe',
        'r'
    ],

    1 => [
        'pipe',
        'w'
    ],

    2 => [
        'pipe',
        'w'
    ]

];


$python_result = null;


$process = proc_open(
    $command,
    $descriptorspec,
    $pipes
);


if (is_resource($process)) {

    // Send PHP data to Python
    fwrite(
        $pipes[0],
        json_encode(
            $analytics_input
        )
    );

    fclose(
        $pipes[0]
    );


    // Read Python output
    $python_output =
        stream_get_contents(
            $pipes[1]
        );

    fclose(
        $pipes[1]
    );


    // Read Python errors
    $python_error =
        stream_get_contents(
            $pipes[2]
        );

    fclose(
        $pipes[2]
    );


    proc_close(
        $process
    );


    // Decode Python JSON
    if (
        !empty($python_output)
    ) {

        $python_result =
            json_decode(
                $python_output,
                true
            );
    }


    // Save Python errors
    if (
        !empty($python_error)
    ) {

        error_log(
            'Python Analytics Error: '
            . $python_error
        );
    }
}


// =====================================================
// PYTHON ANALYTICS RESULT
// =====================================================

$python_analytics = [];


if (
    is_array($python_result)
    &&
    ($python_result['success'] ?? false)
) {

    $python_analytics =
        $python_result[
            'analytics'
        ] ?? [];
}


// =====================================================
// FALLBACK VALUES
// =====================================================

// Revenue
$python_total_revenue =
    $python_analytics[
        'total_revenue'
    ] ?? $total_revenue_alltime;


$python_average_revenue =
    $python_analytics[
        'average_daily_revenue'
    ] ?? 0;


$python_revenue_growth =
    $python_analytics[
        'revenue_growth'
    ] ?? 0;


$python_forecast =
    $python_analytics[
        'forecast_30_day_revenue'
    ] ?? 0;


// Members
$python_active_members =
    $python_analytics[
        'member_stats'
    ]['active_members']
    ?? $total_members;


$python_new_members =
    $python_analytics[
        'member_stats'
    ]['new_members_30_days']
    ?? 0;


$python_expiring =
    $python_analytics[
        'member_stats'
    ]['expiring_7_days']
    ?? $expiring_count;


// Attendance
$python_total_attendance =
    $python_analytics[
        'total_attendance_30_days'
    ] ?? 0;


$python_avg_attendance =
    $python_analytics[
        'avg_daily_attendance'
    ] ?? 0;


$python_peak_hour =
    $python_analytics[
        'peak_hour_label'
    ] ?? 'No data';


$python_peak_hour_count =
    $python_analytics[
        'peak_hour_count'
    ] ?? 0;


// Member type
$python_top_member_type =
    $python_analytics[
        'top_member_type'
    ] ?? 'No data';


// Insight
$python_insight =
    $python_analytics[
        'insight'
    ] ?? 'No analytics available.';


require_once '../includes/header.php';
require_once '../includes/sidebar.php';

?>


<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-3 mb-4 border-bottom">

    <div>

        <h1 class="h2 fw-bold text-dark mb-1">
            Dashboard Overview
        </h1>

        <p class="text-muted mb-0">

            Welcome back,

            <strong class="text-warning">

                <?php

                echo sanitize(
                    $_SESSION['full_name']
                );

                ?>

            </strong>

            — here's what's happening today.

        </p>

    </div>


    <div class="btn-toolbar mb-2 mb-md-0 gap-2">

        <a
            href="attendance.php"
            class="btn btn-warning fw-bold shadow-sm"
        >

            <i class="bi bi-box-arrow-in-right me-1"></i>

            Check-in Member

        </a>


        <a
            href="walkins.php"
            class="btn btn-dark fw-bold shadow-sm"
        >

            <i class="bi bi-person-walking me-1"></i>

            New Walk-in

        </a>

    </div>

</div>


<!-- =================================================
     METRICS CARDS
================================================= -->

<div class="row g-3 mb-4 row-cols-1 row-cols-md-2 row-cols-xl-5">


    <!-- Active Members -->

    <div class="col">

        <div class="card stat-card h-100 p-3">

            <div class="d-flex justify-content-between align-items-center">

                <div>

                    <span class="text-muted fw-semibold small text-uppercase">
                        Active Members
                    </span>

                    <h3 class="fw-bold mb-0 mt-1">

                        <?php

                        echo number_format(
                            $python_active_members
                        );

                        ?>

                    </h3>

                </div>


                <div
                    class="bg-dark text-white rounded-circle p-3"
                    style="width:56px;height:56px;display:flex;align-items:center;justify-content:center;"
                >

                    <i
                        class="bi bi-people-fill fs-3"
                        style="line-height:1;"
                    ></i>

                </div>

            </div>

        </div>

    </div>


    <!-- Expiring -->

    <div class="col">

        <div class="card border-left border-4 border-danger h-100 p-3">

            <div class="d-flex justify-content-between align-items-center">

                <div>

                    <span class="text-muted fw-semibold small text-uppercase">
                        Expiring Soon (7 Days)
                    </span>

                    <h3 class="fw-bold mb-0 mt-1 text-danger">

                        <?php

                        echo number_format(
                            $python_expiring
                        );

                        ?>

                    </h3>

                </div>


                <div
                    class="bg-danger text-white rounded-circle p-3"
                    style="width:56px;height:56px;display:flex;align-items:center;justify-content:center;"
                >

                    <i
                        class="bi bi-exclamation-triangle-fill fs-3"
                        style="line-height:1;"
                    ></i>

                </div>

            </div>

        </div>

    </div>


    <!-- Today's Attendance -->

    <div class="col">

        <div class="card border-left border-4 border-dark h-100 p-3">

            <div class="d-flex justify-content-between align-items-center">

                <div>

                    <span class="text-muted fw-semibold small text-uppercase">
                        Today's Attendance
                    </span>

                    <h3 class="fw-bold mb-0 mt-1 text-dark">

                        <?php

                        echo number_format(
                            $today_attendance
                        );

                        ?>

                    </h3>

                </div>


                <div
                    class="bg-dark text-white rounded-circle p-3"
                    style="width:56px;height:56px;display:flex;align-items:center;justify-content:center;"
                >

                    <i
                        class="bi bi-card-checklist fs-3"
                        style="line-height:1;"
                    ></i>

                </div>

            </div>

        </div>

    </div>


    <!-- Today's Revenue -->

    <div class="col">

        <div class="card border-left border-4 border-success h-100 p-3">

            <div class="d-flex justify-content-between align-items-center">

                <div>

                    <span class="text-muted fw-semibold small text-uppercase">
                        Today's Revenue
                    </span>

                    <h3 class="fw-bold mb-0 mt-1 text-success">

                        ₱<?php

                        echo number_format(
                            $today_revenue,
                            2
                        );

                        ?>

                    </h3>

                </div>


                <div
                    class="bg-success text-white rounded-circle p-3"
                    style="width:56px;height:56px;display:flex;align-items:center;justify-content:center;"
                >

                    <span
                        class="fs-3 fw-bold"
                        style="line-height:1;"
                    >
                        ₱
                    </span>

                </div>

            </div>

        </div>

    </div>


    <!-- Total Revenue -->

    <div class="col">

        <div class="card bg-dark text-white h-100 p-3">

            <div class="d-flex justify-content-between align-items-center">

                <div>

                    <span class="text-warning fw-semibold small text-uppercase">
                        Total Revenue
                    </span>

                    <h3 class="fw-bold mb-0 mt-1">

                        ₱<?php

                        echo number_format(
                            $python_total_revenue,
                            2
                        );

                        ?>

                    </h3>

                </div>


                <div
                    class="text-white-50 rounded-circle border border-secondary-subtle p-3"
                    style="width:56px;height:56px;display:flex;align-items:center;justify-content:center;"
                >

                    <i
                        class="bi bi-graph-up-arrow fs-3"
                        style="line-height:1;"
                    ></i>

                </div>

            </div>

        </div>

    </div>

</div>


<!-- =================================================
     PYTHON ANALYTICS SUMMARY
================================================= -->

<div class="row g-3 mb-4">


    <!-- Average Revenue -->

    <div class="col-md-3">

        <div class="card p-3 h-100">

            <span class="text-muted small text-uppercase fw-semibold">
                Avg Daily Revenue
            </span>

            <h4 class="fw-bold mt-2 mb-0">

                ₱<?php

                echo number_format(
                    $python_average_revenue,
                    2
                );

                ?>

            </h4>

        </div>

    </div>


    <!-- Revenue Growth -->

    <div class="col-md-3">

        <div class="card p-3 h-100">

            <span class="text-muted small text-uppercase fw-semibold">
                Revenue Growth
            </span>

            <h4 class="fw-bold mt-2 mb-0">

                <?php

                echo number_format(
                    $python_revenue_growth,
                    1
                );

                ?>%

            </h4>

        </div>

    </div>


    <!-- New Members -->

    <div class="col-md-3">

        <div class="card p-3 h-100">

            <span class="text-muted small text-uppercase fw-semibold">
                New Members · 30 Days
            </span>

            <h4 class="fw-bold mt-2 mb-0">

                <?php

                echo number_format(
                    $python_new_members
                );

                ?>

            </h4>

        </div>

    </div>


    <!-- Forecast -->

    <div class="col-md-3">

        <div class="card p-3 h-100">

            <span class="text-muted small text-uppercase fw-semibold">
                30-Day Revenue Forecast
            </span>

            <h4 class="fw-bold mt-2 mb-0">

                ₱<?php

                echo number_format(
                    $python_forecast,
                    2
                );

                ?>

            </h4>

        </div>

    </div>

</div>


<!-- =================================================
     ATTENDANCE ANALYTICS
================================================= -->

<div class="row g-3 mb-4">


    <!-- 30 Day Attendance -->

    <div class="col-md-4">

        <div class="card p-3 h-100">

            <div class="d-flex justify-content-between align-items-start">

                <div>

                    <span class="text-muted small text-uppercase fw-semibold">
                        Attendance · Last 30 Days
                    </span>

                    <h4 class="fw-bold mt-2 mb-0">

                        <?php

                        echo number_format(
                            $python_total_attendance
                        );

                        ?>

                    </h4>

                    <small class="text-muted">
                        Total check-ins
                    </small>

                </div>


                <i class="bi bi-calendar-check fs-3 text-warning"></i>

            </div>

        </div>

    </div>


    <!-- Average Daily Attendance -->

    <div class="col-md-4">

        <div class="card p-3 h-100">

            <div class="d-flex justify-content-between align-items-start">

                <div>

                    <span class="text-muted small text-uppercase fw-semibold">
                        Average Daily Attendance
                    </span>

                    <h4 class="fw-bold mt-2 mb-0">

                        <?php

                        echo number_format(
                            $python_avg_attendance,
                            1
                        );

                        ?>

                    </h4>

                    <small class="text-muted">
                        Check-ins per day
                    </small>

                </div>


                <i class="bi bi-bar-chart-line fs-3 text-warning"></i>

            </div>

        </div>

    </div>


    <!-- Peak Attendance Hour -->

    <div class="col-md-4">

        <div class="card p-3 h-100">

            <div class="d-flex justify-content-between align-items-start">

                <div>

                    <span class="text-muted small text-uppercase fw-semibold">
                        Peak Attendance Hour
                    </span>

                    <h4 class="fw-bold mt-2 mb-0">

                        <?php

                        echo sanitize(
                            $python_peak_hour
                        );

                        ?>

                    </h4>

                    <small class="text-muted">

                        <?php

                        echo number_format(
                            $python_peak_hour_count
                        );

                        ?>

                        check-ins

                    </small>

                </div>


                <i class="bi bi-clock-history fs-3 text-warning"></i>

            </div>

        </div>

    </div>

</div>


<!-- =================================================
     PYTHON INSIGHTS
================================================= -->

<div class="row g-3 mb-4">


    <!-- Top Member Type -->

    <div class="col-md-4">

        <div class="card p-3 h-100">

            <span class="text-muted small text-uppercase fw-semibold">
                Top Member Type
            </span>

            <h4 class="fw-bold mt-2 mb-0">

                <?php

                echo sanitize(
                    $python_top_member_type
                );

                ?>

            </h4>

        </div>

    </div>


    <!-- Python Insight -->

    <div class="col-md-8">

        <div class="card p-3 h-100">

            <span class="text-muted small text-uppercase fw-semibold">
                Python Analytics Insight
            </span>

            <p class="fw-semibold mt-2 mb-0">

                <?php

                echo sanitize(
                    $python_insight
                );

                ?>

            </p>

        </div>

    </div>

</div>


<!-- =================================================
     CHARTS
================================================= -->

<div class="row g-4 mb-4">


    <!-- Revenue -->

    <div class="col-lg-8">

        <div class="card h-100 p-3">

            <div class="d-flex align-items-center justify-content-between mb-3">

                <h5 class="fw-bold mb-0">

                    <i class="bi bi-graph-up-arrow text-warning me-2"></i>

                    Monthly Revenue Trend

                </h5>


                <span class="badge bg-light text-dark border fw-semibold">
                    Last 8 months · ₱
                </span>

            </div>


            <canvas
                id="revenueChart"
                height="120"
            ></canvas>

        </div>

    </div>


    <!-- Membership Distribution -->

    <div class="col-lg-4">

        <div class="card h-100 p-3">

            <h5 class="fw-bold mb-3">

                <i class="bi bi-pie-chart-fill text-warning me-2"></i>

                Membership Distribution

            </h5>


            <canvas
                id="membershipPieChart"
                height="200"
            ></canvas>

        </div>

    </div>

</div>


<!-- =================================================
     RECENT LOGS
================================================= -->

<?php if (
    $_SESSION['role'] === 'Administrator'
): ?>

<div class="card p-3">

    <div class="d-flex justify-content-between align-items-center mb-3">

        <h5 class="fw-bold mb-0">

            <i class="bi bi-clock-history me-2"></i>

            Recent Activity Logs

        </h5>


        <a
            href="activity_logs.php"
            class="btn btn-sm btn-outline-dark"
        >
            View All Logs
        </a>

    </div>


    <div class="table-responsive">

        <table class="table table-hover align-middle mb-0">

            <thead>

                <tr>

                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Role</th>
                    <th>Action</th>
                    <th>Description</th>

                </tr>

            </thead>


            <tbody>

                <?php if ($recent_logs): ?>

                    <?php while (
                        $log = mysqli_fetch_assoc(
                            $recent_logs
                        )
                    ): ?>

                    <tr>

                        <td class="small text-muted">

                            <?php

                            echo date(
                                'M d, Y h:i A',
                                strtotime(
                                    $log['created_at']
                                )
                            );

                            ?>

                        </td>


                        <td class="fw-semibold">

                            <?php

                            echo sanitize(
                                $log['username']
                            );

                            ?>

                        </td>


                        <td>

                            <span class="badge bg-secondary">

                                <?php

                                echo sanitize(
                                    $log['role']
                                );

                                ?>

                            </span>

                        </td>


                        <td>

                            <span class="badge bg-dark">

                                <?php

                                echo sanitize(
                                    $log['action']
                                );

                                ?>

                            </span>

                        </td>


                        <td>

                            <?php

                            echo sanitize(
                                $log['description']
                            );

                            ?>

                        </td>

                    </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="5"
                            class="text-center text-muted py-4"
                        >
                            No recent activity logs.
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

<?php endif; ?>


<script>

// =====================================================
// REVENUE CHART
// =====================================================

const ctx =
    document
        .getElementById(
            'revenueChart'
        )
        .getContext('2d');


const revenueGradient =
    ctx.createLinearGradient(
        0,
        0,
        0,
        260
    );


revenueGradient.addColorStop(
    0,
    'rgba(216, 19, 36, 0.28)'
);


revenueGradient.addColorStop(
    1,
    'rgba(216, 19, 36, 0.02)'
);


new Chart(
    ctx,
    {
        type: 'line',

        data: {

            labels:
                <?php

                echo json_encode(
                    $revenue_chart_labels
                );

                ?>,

            datasets: [{

                label: 'Revenue (₱)',

                data:
                    <?php

                    echo json_encode(
                        $revenue_chart_data
                    );

                    ?>,

                borderColor: '#d81324',

                backgroundColor:
                    revenueGradient,

                borderWidth: 2.5,

                pointBackgroundColor:
                    '#d81324',

                pointBorderColor:
                    '#fff',

                pointBorderWidth: 2,

                pointRadius: 4,

                pointHoverRadius: 6,

                fill: true,

                tension: 0.35

            }]

        },


        options: {

            plugins: {

                legend: {

                    display: false

                }

            },


            scales: {

                y: {

                    beginAtZero: true,

                    grid: {

                        color: '#eef1f5'

                    },

                    ticks: {

                        color: '#667085'

                    }

                },


                x: {

                    grid: {

                        display: false

                    },

                    ticks: {

                        color: '#667085'

                    }

                }

            }

        }

    }
);


// =====================================================
// MEMBERSHIP PIE CHART
// =====================================================

const pieCtx =
    document
        .getElementById(
            'membershipPieChart'
        )
        .getContext('2d');


new Chart(
    pieCtx,
    {
        type: 'doughnut',

        data: {

            labels: [

                'Regular',

                'Student'

            ],


            datasets: [{

                data: [

                    <?php

                    echo (int)
                        $distribution[
                            'Regular'
                        ];

                    ?>,

                    <?php

                    echo (int)
                        $distribution[
                            'Student'
                        ];

                    ?>

                ],


                backgroundColor: [

                    '#0e0f13',

                    '#d81324'

                ],


                borderColor:
                    '#ffffff',

                borderWidth: 3,

                hoverOffset: 6

            }]

        },


        options: {

            cutout: '68%',


            plugins: {

                legend: {

                    position: 'bottom',


                    labels: {

                        color: '#333846',

                        font: {

                            family: 'Inter',

                            weight: '600'

                        },

                        padding: 16

                    }

                }

            }

        }

    }
);

</script>


<?php

require_once '../includes/footer.php';

?>