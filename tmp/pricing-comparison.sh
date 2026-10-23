#!/bin/sh
cd /var/www/mahadum || exit 1
sudo -u adebareshowemimo php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo app(App\Http\Controllers\Billing\PlanController::class)->index()->getContent();'