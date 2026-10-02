<?php

use App\Models\SyllabusTopic;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('renders all topics from the root topics index', function () {
    $firstTopic = SyllabusTopic::factory()->create(['sort_order' => 1]);
    $secondTopic = SyllabusTopic::factory()->create(['sort_order' => 2]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('topics.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/topics/index')
            ->where('subject', null)
            ->has('topics', 2)
            ->where('topics.0.id', $firstTopic->id)
            ->where('topics.1.id', $secondTopic->id));
});
