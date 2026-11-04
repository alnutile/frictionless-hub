<?php

/*
 * Architecture rules: conventions this codebase relies on, enforced so new code follows them.
 */

arch('no debugging calls are left behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'die'])
    ->not->toBeUsed();

arch('enums live in App\Enums and are string-backed')
    ->expect('App\Enums')
    ->toBeStringBackedEnums();

arch('controllers are named controllers and extend the base controller')
    ->expect('App\Http\Controllers')
    ->classes()
    ->toHaveSuffix('Controller')
    ->toExtend('App\Http\Controllers\Controller')
    ->ignoring('App\Http\Controllers\Controller');

arch('models extend Eloquent')
    ->expect('App\Models')
    ->classes()
    ->toExtend('Illuminate\Database\Eloquent\Model');

arch('pure logic in Services and Support stays framework-free of HTTP')
    ->expect(['App\Services\FrictionHeatmap', 'App\Services\RatingSummary', 'App\Support\Walkthrough'])
    ->not->toUse(['Illuminate\Http', 'Illuminate\Support\Facades']);

arch('form requests are named requests')
    ->expect('App\Http\Requests')
    ->classes()
    ->toHaveSuffix('Request');

arch('policies are named policies')
    ->expect('App\Policies')
    ->toHaveSuffix('Policy');

arch('the application does not use env() outside config')
    ->expect('env')
    ->not->toBeUsedIn('App');
