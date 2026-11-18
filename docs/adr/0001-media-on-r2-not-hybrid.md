# เก็บ media บน Cloudflare R2 ทั้งหมด ไม่ทำแบบเก็บในเครื่องก่อนแล้วล้นไป R2

ก่อนเปิดขาย ย้ายไฟล์ media ทั้งหมดจากดิสก์ของ VPS ไป R2 (`FILESYSTEM_DISK=r2`) แทนแนวคิดเดิมที่จะเก็บในเครื่อง
จนถึงราว 100 GB แล้วค่อยให้ไฟล์ใหม่ไปลง R2 เหตุผล: ตาราง `medias` ไม่บันทึกว่าไฟล์อยู่ disk ไหน และทุกจุดที่อ่าน
เขียน ลบ คัดลอกไฟล์ (ราว 20 จุด) ใช้ default disk แบบ hybrid จึงต้องเพิ่มคอลัมน์ disk และแก้ทุกจุดนั้น แล้วสุดท้ายก็ต้อง
ย้ายอยู่ดี ขณะที่ upstream TryPost Cloud รันบน R2 อยู่แล้ว (มี disk `r2` และอัปโหลด multipart ตรงขึ้น R2) R2 ราคา
ประมาณ $0.015/GB/เดือนและไม่มีค่า egress และช่วงก่อนเปิดขายข้อมูลยังน้อย ย้ายถูกที่สุด

## Consequences

- bucket ต้องอ่านได้แบบ public ผ่าน custom domain ไม่ใช่ `r2.dev` (LinkedIn ทิ้ง media ที่มาจาก `r2.dev`)
  และ TikTok ต้อง verify โดเมนนั้นสำหรับ `PULL_FROM_URL`
- URL ของ media ถูกเก็บแบบเต็มใน JSON ของโพสต์ (`posts.media`, `ideas.media`, `meta.thread_replies`) ตอนย้ายต้อง
  rewrite จาก `APP_URL/storage/` เป็นโดเมนของ R2 และคง `/storage` ไว้ช่วงเปลี่ยน
- Disk guard ยังเฝ้าดิสก์ของ VPS (ไฟล์ชั่วคราวและ chunk ยังอยู่ที่นั่น) แต่คำเตือน over-commit ของ `rxdesk:quota`
  ที่เทียบ Quota กับดิสก์ของ VPS ต้องปรับ
