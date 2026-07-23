<?php

beforeEach(fn () => seedCore());

it('confirms authentic reference numbers without exposing case data', function () {
    $case = createCase();
    attachClaimant($case, ['last_name' => 'Demoperson']);
    $case->update(['estimated_surplus' => 42500]);

    $response = $this->post(route('site.verify.check'), [
        'reference' => $case->case_number, 'last_name' => 'Demoperson', 'zip' => '21401',
    ]);

    $response->assertOk()
        ->assertSee('authentic')
        ->assertDontSee('42,500')   // never expose amounts
        ->assertDontSee('42500');
});

it('rejects wrong name and reference combinations', function () {
    $case = createCase();
    attachClaimant($case, ['last_name' => 'Demoperson']);

    $this->post(route('site.verify.check'), [
        'reference' => $case->case_number, 'last_name' => 'Wrongname', 'zip' => '21401',
    ])->assertOk()->assertSee('could not verify');
});
