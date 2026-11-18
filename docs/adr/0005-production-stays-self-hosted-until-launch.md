# production คงโหมด self-host จนถึงวันเปิดขาย

ระหว่างพัฒนาเฟส 2 production (live ตั้งแต่ 17 พฤศจิกายน 2026) ต้องใช้งานได้ปกติ จึงคง `SELF_HOSTED=true` ไว้จนวันเปิดขาย
และโค้ดใหม่ทุกชิ้น (Plan ตามพื้นที่, Launch price, Trial ตั้งต้น, AI credits) ต้องไม่เปลี่ยนพฤติกรรมเมื่ออยู่ในโหมด self-host
merge เข้า `main` และ deploy ทีละชิ้นได้โดยไม่กระทบเพื่อนที่ใช้อยู่ migration เป็นแบบเพิ่มอย่างเดียว (คอลัมน์ใหม่ nullable
หรือมีค่าเริ่มต้น) Stripe test mode และ R2 bucket ทดสอบใช้กับ dev stack เท่านั้น วันเปิดขายคือการพลิก
`SELF_HOSTED=false` หลังออก Promotion code ให้เพื่อนแล้ว

## Consequences

- test ของงานเฟส 2 ต้องครอบทั้งโหมด self-host และโหมดขาย
- `SELF_HOSTED` คุมพฤติกรรม 21 จุดใน 12 ไฟล์ ต้องไล่ตัดสินทีละจุดก่อนพลิก (บางจุดเปิดของ TryPost Cloud ที่เราไม่ใช้
  เช่นเมนู chat support และ workspace limit)
