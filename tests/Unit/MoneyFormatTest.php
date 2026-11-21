<?php

declare(strict_types=1);

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

test('a price is USD in every language, always with two decimals', function () {
    $node = (new ExecutableFinder)->find('node');

    if ($node === null) {
        $this->markTestSkipped('node is unavailable');
    }

    $cases = ['en:1190', 'fr:1190', 'pt-BR:1190', 'de:1190'];

    $process = (new Process([
        $node,
        base_path('tests/fixtures/money-format-harness.ts'),
        resource_path('js/lib/money.ts'),
        ...$cases,
    ]))->mustRun();

    expect(json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR))
        ->toBe([
            '$11.90',
            "11,90\u{a0}\$US",
            "US\$\u{a0}11,90",
            "11,90\u{a0}\$",
        ]);
});
