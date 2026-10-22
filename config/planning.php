<?php

return [
    // Five is the business maximum; an installation may choose a smaller allowance.
    'outside_weekly_limit' => min(5, max(1, (int) env('PLANNING_OUTSIDE_WEEKLY_LIMIT', 5))),
    'visit_reminder_minutes' => max(1, (int) env('PLANNING_VISIT_REMINDER_MINUTES', 20)),
];
