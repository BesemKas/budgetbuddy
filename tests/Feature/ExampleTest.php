<?php

test('the home landing page loads for guests', function () {
    $response = $this->get('/');

    $response->assertSuccessful();
    $response->assertSee('Budget Buddy', escape: false);
    $response->assertSee('Get started', escape: false);
    $response->assertSee('Multi-currency accounts', escape: false);
});
