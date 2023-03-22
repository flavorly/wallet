<?php

it('can test', function () {
    expect(true)->toBeTrue();
});

it('can make sums', function () {
    $service = new \Flavorly\Wallet\Services\Math\WalletMathService(10);
    $result = $service->add('300000.4234132131', 20000.234242428888);
    rd((string) $result, $service->toFloat($result));
});
