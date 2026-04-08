<?php

it('shows a mobile navigation control on the marketing home page', function (): void {
    $this->get(route('home'))
        ->assertSuccessful()
        ->assertSee(__('Menu'), escape: false);
});
