<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$kernel->call('route:clear');
echo "Routes cleared\n";

$kernel->call('view:clear');
echo "Views cleared\n";

$kernel->call('cache:clear');
echo "Cache cleared\n";

$kernel->call('config:clear');
echo "Config cleared\n";

echo "\nAll caches cleared successfully!\n";
