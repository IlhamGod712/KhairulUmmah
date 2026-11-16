<?php

test('root redirects to the api health endpoint', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('api.health'));
});
