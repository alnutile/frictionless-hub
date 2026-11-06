<?php

use App\Mcp\Servers\HubServer;
use Laravel\Mcp\Facades\Mcp;

// Local only (stdio): `php artisan mcp:start frictionless-hub`. Deliberately not exposed over HTTP.
Mcp::local('frictionless-hub', HubServer::class);
