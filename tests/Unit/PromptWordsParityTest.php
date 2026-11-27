<?php

declare(strict_types=1);

use App\Support\AiPromptRules;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

test('the frontend and backend count prompt words identically', function () {
    $node = (new ExecutableFinder)->find('node');

    if ($node === null) {
        $this->markTestSkipped('node is unavailable');
    }

    $corpus = [
        '',
        '   ',
        'make it pop',
        'write a post now',
        '  two   words ',
        'nova coleção de verão',
        '新商品を紹介',
        'Launch 新商品',
        'ช่วยเขียนโพสต์',
        'ช่วยเขียนโพสต์โปรโมทคอร์สเรียนทำน้ำแข็ง',
        'โปรโมทAIสำหรับร้านกาแฟ',
        'ลดราคา50%วันนี้เท่านั้น',
        'เขียนโพสต์ AI ใหม่',
        'ຊ່ວຍຂຽນໂພສໂປຣໂມດສິນຄ້າໃໝ່',
        'សូមជួយសរសេរប្រកាសផ្សព្វផ្សាយផលិតផលថ្មី',
        'ကုန်ပစ္စည်းအသစ်အတွက်ပို့စ်ရေးပေးပါ',
    ];

    $process = (new Process([
        $node,
        base_path('tests/fixtures/prompt-words-harness.ts'),
        resource_path('js/lib/promptWords.ts'),
        json_encode($corpus, JSON_THROW_ON_ERROR),
    ]))->mustRun();

    $fromTypeScript = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    $fromPhp = array_map(fn (string $text): int => AiPromptRules::wordCount($text), $corpus);

    expect(array_combine($corpus, $fromTypeScript))
        ->toBe(array_combine($corpus, $fromPhp));
});
