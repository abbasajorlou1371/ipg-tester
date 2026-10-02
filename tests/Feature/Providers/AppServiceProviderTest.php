<?php

use App\Providers\AppServiceProvider;

test('builds payment urls from the configured application url', function () {
    config(['app.url' => 'https://dev-api.metarang.com']);

    app()->getProvider(AppServiceProvider::class)->boot();

    $this->get(route('payments.create'))
        ->assertOk()
        ->assertSee('action="https://dev-api.metarang.com/payments"', false);
});
