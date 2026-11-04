<?php

test('guests visiting the root are redirected to login', function () {
    $this->get('/')->assertRedirect(route('login'));
});
