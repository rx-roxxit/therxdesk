---
status: proposed
---

# หน้าแอดมินบน admin.therxdesk.com ด้วยแพ็กเกจแอดมิน open source

เฟส 1 ตัดสินว่าแอดมินใช้ CLI เท่านั้น เมื่อเปิดขายเป็น SaaS งานแอดมิน (ดูลูกค้า, Plan, subscription, ปรับ Quota และ
AI credits, ระงับบัญชี, ออก Promotion code) มากเกินกว่าจะทำผ่าน CLI จึงมีหน้าแอดมินแยก subdomain ไม่อยู่ในแอปหลัก
เพื่อให้ session แยกจากลูกค้าและโค้ดแยกจาก upstream แนะนำ Filament (MIT): CRUD, ตาราง, dashboard และ MFA ในตัว
ผูก panel กับ domain ได้ และทำงานบน Livewire แยกจาก Inertia/Vue ของแอปหลัก สถานะยังเป็น proposed จนกว่าเจ้าของ
จะยืนยันการเพิ่ม dependency

## Considered Options

- เขียนหน้าแอดมินเองด้วย Inertia/Vue: ไม่ต้องเพิ่ม dependency แต่ต้องเขียนตาราง ฟอร์ม และ auth แยกเองทั้งหมด
- MoonShine, Orchid: open source แต่ชุมชนเล็กกว่า
- Laravel Nova: เสียเงิน ไม่ใช่ open source; Backpack: บางส่วนเสียเงิน
