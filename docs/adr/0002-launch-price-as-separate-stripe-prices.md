# Launch price เป็น Stripe Price แยกของแต่ละ Plan ไม่ใช้ coupon และล็อกเฉพาะ Plan เดิม

แต่ละ Plan มี Stripe Price สองตัว: ราคาจริงกับ Launch price checkout ในช่วง 3 เดือนแรกหลังเปิดขายใช้ Launch price
หลังจากนั้นใช้ราคาจริง ไม่ใช้ coupon เพราะส่วนลดของแต่ละ Plan ไม่เท่ากัน ($7.9/$11.9 ถึง $29.9/$49.9 คือ 34–40%)
coupon ตัวเดียวทำไม่ได้ และ Stripe คงราคาของ subscription ที่มีอยู่ไว้เองตลอดอายุ จึงได้กติกา "ได้ราคานี้ตลอดถ้า
subscription ไม่ขาด" โดยไม่ต้องเขียนโค้ดดูแล เจ้าของเลือกให้ Launch price ล็อกกับ Plan เดิมเท่านั้น: เปลี่ยน Plan
เมื่อไรได้ราคาจริงของ Plan ใหม่ subscription ที่ขาดแล้วสมัครใหม่คือ checkout ใหม่ จึงได้ราคาของช่วงเวลานั้น

## Consequences

- การหา Plan จาก Stripe Price (`Plan::forStripePrices()`, webhook ที่เขียน `plan_id`) ต้องรู้จัก Price ทั้งสองตัว
- `ChangePlan` ใช้ราคาจริงของ Plan ใหม่เสมอ และควรเตือนว่าจะเสีย Launch price
