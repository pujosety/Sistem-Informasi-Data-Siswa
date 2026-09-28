<?php

return [
    // First: verifies the session and cache tables exist before the framework
    // resolves either store. The deployed environment returns 500 on every
    // route without this, because a database-backed store with no table throws
    // inside StartSession before any controller runs.
    App\Providers\EnsureStoresAreUsable::class,
    App\Providers\AppServiceProvider::class,
];
