import sys
import json
from datetime import datetime
from statistics import mean


def calculate_revenue_analytics(data):

    # =================================================
    # MEMBER ANALYTICS
    # =================================================

    member_stats = data.get(
        "member_stats",
        {}
    )

    active_members = int(
        member_stats.get(
            "active_members",
            0
        )
    )

    new_members_30_days = int(
        member_stats.get(
            "new_members_30_days",
            0
        )
    )

    expiring_7_days = int(
        member_stats.get(
            "expiring_7_days",
            0
        )
    )

    new_members_by_day = member_stats.get(
        "new_members_by_day",
        {}
    )

    new_member_values = [
        int(v)
        for v in new_members_by_day.values()
    ]

    new_member_average = (
        mean(new_member_values)
        if new_member_values
        else 0
    )


    # =================================================
    # ATTENDANCE ANALYTICS
    # =================================================

    daily_attendance = data.get(
        "daily_attendance",
        []
    )

    # Make sure attendance values are numbers
    cleaned_attendance = []

    for value in daily_attendance:

        try:
            cleaned_attendance.append(
                int(value)
            )

        except (
            TypeError,
            ValueError
        ):
            cleaned_attendance.append(0)


    # -------------------------------------------------
    # Total attendance - last 30 days
    # -------------------------------------------------

    total_attendance = sum(
        cleaned_attendance
    )


    # -------------------------------------------------
    # Average daily attendance
    # -------------------------------------------------

    avg_attendance = (
        mean(cleaned_attendance)
        if cleaned_attendance
        else 0
    )


    # -------------------------------------------------
    # Attendance by hour
    # -------------------------------------------------

    attendance_hours = data.get(
        "attendance_hours",
        {}
    )

    peak_hour = None
    peak_count = 0


    if attendance_hours:

        cleaned_hours = {}

        for hour, count in attendance_hours.items():

            try:

                hour_int = int(hour)
                count_int = int(count)

                cleaned_hours[
                    hour_int
                ] = count_int

            except (
                TypeError,
                ValueError
            ):
                continue


        if cleaned_hours:

            peak_hour = max(
                cleaned_hours,
                key=cleaned_hours.get
            )

            peak_count = cleaned_hours[
                peak_hour
            ]


    # -------------------------------------------------
    # Peak hour label
    # -------------------------------------------------

    def hour_label(hour):

        if hour is None:
            return "No data"

        hour = int(hour)

        if hour == 0:
            return "12 AM"

        if hour == 12:
            return "12 PM"

        if hour > 12:
            return f"{hour - 12} PM"

        return f"{hour} AM"


    # =================================================
    # REVENUE ANALYTICS
    # =================================================

    daily_revenue = data.get(
        "daily_revenue",
        []
    )

    cleaned = []


    # -------------------------------------------------
    # Clean revenue values
    # -------------------------------------------------

    for item in daily_revenue:

        try:

            cleaned.append({
                "date": item["date"],
                "revenue": float(
                    item["revenue"]
                )
            })

        except (
            KeyError,
            TypeError,
            ValueError
        ):

            continue


    # =================================================
    # NO REVENUE DATA
    # =================================================

    if not cleaned:

        return {

            "total_revenue": 0,

            "average_daily_revenue": 0,

            "last_7_days_revenue": 0,

            "last_30_days_revenue": 0,

            "revenue_growth": 0,

            "revenue_trend": "insufficient_data",

            "previous_30_day_revenue": 0,

            "current_30_day_revenue": 0,

            "growth_available": False,

            "best_day": None,

            "lowest_day": None,

            "daily_revenue": [],


            # Dashboard Summary

            "avg_daily_revenue": 0,

            "active_members":
                active_members,

            "new_members_30_days":
                new_members_30_days,

            "expiring_7_days":
                expiring_7_days,

            "new_member_average":
                round(
                    new_member_average,
                    1
                ),

            "revenue_growth_pct": 0,

            "forecast_30_day_revenue": 0,

            "total_attendance_30_days":
                total_attendance,

            "avg_daily_attendance":
                round(
                    avg_attendance,
                    1
                ),

            "peak_hour_label":
                hour_label(peak_hour),

            "peak_hour_count":
                peak_count,

            "top_member_type":
                "No data",

            "insight":
                "No revenue data available.",


            # Member Analytics

            "member_stats": {

                "active_members":
                    active_members,

                "new_members_30_days":
                    new_members_30_days,

                "expiring_7_days":
                    expiring_7_days,

                "new_members_by_day":
                    new_members_by_day,

                "new_member_average":
                    round(
                        new_member_average,
                        2
                    )

            }

        }


    # =================================================
    # REVENUE VALUES
    # =================================================

    # PHP supplies a continuous 60-day calendar series, including zero days.
    # Use the latest 30 calendar days for the dashboard average so the
    # metric means exactly: current 30-day revenue / 30 days.
    revenue_values = [
        item["revenue"]
        for item in cleaned
    ]

    total_revenue = sum(revenue_values)

    current_30 = cleaned[-30:] if len(cleaned) >= 30 else cleaned
    current_30_revenue = sum(
        item["revenue"] for item in current_30
    )

    avg_daily_revenue = (
        current_30_revenue / 30
        if len(cleaned) >= 30
        else (mean(revenue_values) if revenue_values else 0)
    )


    # =================================================
    # LAST 7 DAYS
    # =================================================

    last_7 = cleaned[-7:]


    last_7_days_revenue = sum(
        item["revenue"]
        for item in last_7
    )


    # =================================================
    # LAST 30 DAYS
    # =================================================

    last_30 = cleaned[-30:]

    last_30_days_revenue = sum(
        item["revenue"]
        for item in last_30
    )


    # =================================================
    # REVENUE GROWTH
    # =================================================
    # Compare the latest 30 complete calendar days with
    # the 30 days immediately before them. PHP supplies
    # 60 days of real Paid payment totals from MySQL.
    # This avoids treating missing/insufficient history as
    # a false 0% (stable) trend.

    growth = 0.0
    trend_status = "insufficient_data"
    previous_revenue = 0.0
    current_revenue = 0.0
    growth_available = False

    if len(cleaned) >= 60:

        previous_period = cleaned[-60:-30]
        current_period = cleaned[-30:]

        previous_revenue = sum(
            item["revenue"]
            for item in previous_period
        )

        current_revenue = sum(
            item["revenue"]
            for item in current_period
        )

        if previous_revenue > 0:
            growth = (
                (current_revenue - previous_revenue)
                / previous_revenue
            ) * 100
            growth_available = True

            # A small movement is treated as stable rather than
            # calling normal day-to-day variation a decline/growth.
            if growth > 5:
                trend_status = "increasing"
            elif growth < -5:
                trend_status = "declining"
            else:
                trend_status = "stable"

        elif current_revenue > 0:
            # No revenue in the previous period, but real revenue
            # exists now. Percentage growth is undefined, so report
            # the business state explicitly instead of showing 0%.
            growth = None
            growth_available = False
            trend_status = "new_revenue"

        else:
            trend_status = "stable"


    # =================================================
    # BEST DAY
    # =================================================

    best_day = max(
        cleaned,
        key=lambda x: x["revenue"]
    )


    # =================================================
    # LOWEST DAY
    # =================================================

    lowest_day = min(
        cleaned,
        key=lambda x: x["revenue"]
    )


    # =================================================
    # 30-DAY FORECAST
    # =================================================

    # Forecast the next 30 days from the actual current 30-day daily average.
    forecast = current_30_revenue if len(cleaned) >= 30 else avg_daily_revenue * 30


    # =================================================
    # MEMBER TYPE
    # =================================================

    member_types = data.get(
        "member_types",
        {}
    )


    if member_types:

        # Only consider member types
        # that actually have members.

        active_member_types = {
            key: int(value)
            for key, value in member_types.items()
            if int(value) > 0
        }

        if active_member_types:

            top_type = max(
                active_member_types,
                key=active_member_types.get
            )

        else:

            top_type = "No data"

    else:

        top_type = "No data"


    # =================================================
    # BASIC BUSINESS INSIGHT
    # =================================================

    if trend_status == "insufficient_data":

        insight = (
            "Not enough revenue history to determine a reliable trend."
        )

    elif trend_status == "new_revenue":

        insight = (
            "Revenue activity has started in the current period; "
            "there is no previous-period revenue for a percentage comparison."
        )

    elif trend_status == "increasing":

        if growth > 10:
            insight = (
                f"Revenue is showing strong growth, up {growth:.1f}% "
                "compared with the previous 30-day period."
            )
        else:
            insight = (
                f"Revenue is increasing by {growth:.1f}% "
                "compared with the previous 30-day period."
            )

    elif trend_status == "declining":

        insight = (
            f"Revenue is declining by {abs(growth):.1f}% "
            "compared with the previous 30-day period. "
            "Consider reviewing membership sales and customer retention."
        )

    else:

        insight = (
            f"Revenue is currently stable ({growth:+.1f}% vs. the previous "
            "30-day period)."
        )


    # =================================================
    # FINAL ANALYTICS
    # =================================================

    return {

        # -------------------------------------------------
        # Revenue Analytics
        # -------------------------------------------------

        "total_revenue":
            round(
                total_revenue,
                2
            ),

        "average_daily_revenue":
            round(
                avg_daily_revenue,
                2
            ),

        "last_7_days_revenue":
            round(
                last_7_days_revenue,
                2
            ),

        "last_30_days_revenue":
            round(
                last_30_days_revenue,
                2
            ),

        "revenue_growth":
            (round(growth, 2) if growth is not None else None),

        "revenue_trend":
            trend_status,

        "previous_30_day_revenue":
            round(
                previous_revenue,
                2
            ),

        "current_30_day_revenue":
            round(
                current_revenue,
                2
            ),

        "growth_available":
            growth_available,

        "best_day":
            best_day,

        "lowest_day":
            lowest_day,

        "daily_revenue":
            cleaned,


        # -------------------------------------------------
        # Dashboard Summary
        # -------------------------------------------------

        "avg_daily_revenue":
            round(
                avg_daily_revenue,
                2
            ),

        "active_members":
            active_members,

        "new_members_30_days":
            new_members_30_days,

        "expiring_7_days":
            expiring_7_days,

        "new_member_average":
            round(
                new_member_average,
                1
            ),

        "revenue_growth_pct":
            (round(growth, 1) if growth is not None else None),

        "forecast_30_day_revenue":
            round(
                forecast,
                2
            ),


        # -------------------------------------------------
        # Attendance Analytics
        # -------------------------------------------------

        "total_attendance_30_days":
            total_attendance,

        "avg_daily_attendance":
            round(
                avg_attendance,
                1
            ),

        "peak_hour_label":
            hour_label(peak_hour),

        "peak_hour_count":
            peak_count,


        # -------------------------------------------------
        # Member Type
        # -------------------------------------------------

        "top_member_type":
            top_type,


        # -------------------------------------------------
        # Business Insight
        # -------------------------------------------------

        "insight":
            insight,


        # -------------------------------------------------
        # Member Analytics
        # -------------------------------------------------

        "member_stats": {

            "active_members":
                active_members,

            "new_members_30_days":
                new_members_30_days,

            "expiring_7_days":
                expiring_7_days,

            "new_members_by_day":
                new_members_by_day,

            "new_member_average":
                round(
                    new_member_average,
                    2
                )

        }

    }


# =====================================================
# MAIN
# =====================================================

def main():

    try:

        # Read JSON from PHP

        raw_data = sys.stdin.read()


        if not raw_data:

            raise ValueError(
                "No data received."
            )


        # Convert JSON to Python

        data = json.loads(
            raw_data
        )


        # Calculate analytics

        analytics = (
            calculate_revenue_analytics(
                data
            )
        )


        # Final response

        result = {

            "success": True,

            "generated_at":
                datetime.now().strftime(
                    "%Y-%m-%d %H:%M:%S"
                ),

            "analytics":
                analytics

        }


        # Send JSON back to PHP

        print(
            json.dumps(
                result
            )
        )


    except Exception as e:

        print(
            json.dumps({

                "success": False,

                "error": str(e)

            })
        )


if __name__ == "__main__":

    main()