<?php

use App\Models\User;

test('content management requires an authenticated administrator', function () {
    $this->get(route('programs.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(route('programs.index'))
        ->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('programs.index'))
        ->assertOk();
});

test('learner routes require authentication and email verification', function () {
    $this->get(route('study.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('study.index'))
        ->assertRedirect(route('verification.notice'));

    $this->actingAs(User::factory()->create())
        ->get(route('study.index'))
        ->assertOk();
});
