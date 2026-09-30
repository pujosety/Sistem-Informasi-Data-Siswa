<?php

/*
|--------------------------------------------------------------------------
| Outbound notification channels (PHASE 1)
|--------------------------------------------------------------------------
|
| OWNED BY ONE FEATURE. Another agent working in parallel must not edit this
| file — put new routes for the same feature in the same file, not a second
| one, or the two will register duplicate paths.
|
| This file is required from routes/web.php OUTSIDE any middleware group, so
| it carries its own. Every group below is auth + the permission that defines
| the surface; never widen it to a neighbouring permission to save a line,
| because a broad guard is how a teacher ends up with a school's user list.
|
*/

// Routes for this feature are added below by its owner.
