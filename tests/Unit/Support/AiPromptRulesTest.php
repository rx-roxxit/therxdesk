<?php

declare(strict_types=1);

use App\Support\AiPromptRules;

test('wordCount counts words and each CJK character', function (string $text, int $expected) {
    expect(AiPromptRules::wordCount($text))->toBe($expected);
})->with([
    'plain words' => ['write a post now', 4],
    'extra whitespace' => ['  two   words ', 2],
    'empty' => ['', 0],
    'japanese' => ['新商品を紹介', 6],
    'mixed latin and japanese' => ['Launch 新商品', 4],
    'accented portuguese' => ['nova coleção de verão', 4],
    'thai without spaces' => ['ช่วยเขียนโพสต์โปรโมทคอร์สเรียนทำน้ำแข็ง', 9],
    'short thai without spaces' => ['ช่วยเขียนโพสต์', 3],
    'mixed thai and latin without spaces' => ['โปรโมทAIสำหรับร้านกาแฟ', 5],
    'lao without spaces' => ['ຊ່ວຍຂຽນໂພສໂປຣໂມດສິນຄ້າໃໝ່', 7],
    'khmer without spaces' => ['សូមជួយសរសេរប្រកាសផ្សព្វផ្សាយផលិតផលថ្មី', 7],
    'myanmar without spaces' => ['ကုန်ပစ္စည်းအသစ်အတွက်ပို့စ်ရေးပေးပါ', 8],
]);
