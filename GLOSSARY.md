# The RX Desk

แพลตฟอร์ม Social Media Management แบบ SaaS สร้างจาก TryPost แต่ละ Owner ซื้อ Plan ตามพื้นที่จัดเก็บ
และใช้งานเหมือนลง TryPost ไว้ใช้เอง โดยมี System admin ดูแลระบบ
(ก่อนเปิดขาย ระบบยังรันในโหมด self-host ที่สมัครด้วย Signup code)

## คน

**System admin**:
ผู้ดูแลระบบ ทำงานผ่านหน้าแอดมิน (admin.therxdesk.com) และ artisan CLI ไม่ใช่ลูกค้า
_Avoid_: root admin, super admin

**Owner**:
User ที่เป็นเจ้าของ Account ของตัวเอง ถือ Subscription ของ Account นั้น สร้าง Workspace ได้ไม่จำกัด
และเป็นหน่วยที่ใช้นับ Quota และ AI credits
_Avoid_: user (ถ้าหมายถึงเจ้าของ), member, customer (ในโค้ดและ issue)

**Member**:
User ที่ถูกเชิญเข้า Workspace ของ Owner คนอื่นด้วย Invite
_Avoid_: guest, collaborator

## การเข้าระบบ

**Signup code**:
โค้ดที่ System admin ออกให้ ใช้สมัครเป็น Owner ได้ครั้งเดียว ใช้เฉพาะตอนระบบรันในโหมด self-host
_Avoid_: invite code, invitation code, referral code

**Invite**:
คำเชิญของ TryPost เดิม ใช้เชิญคนเข้า Workspace และผูกกับอีเมล ไม่ใช่สิ่งเดียวกับ Signup code
_Avoid_: ใช้คำนี้เรียก Signup code

## การขาย

**Plan**:
แพ็กเกจที่ Owner ซื้อ มี 4 ระดับ (Mini, Standard, Plus, Max) ต่างกันที่ Quota และ AI credits เท่านั้น
ฟีเจอร์อื่นเท่ากันทุก Plan
_Avoid_: package, tier, pricing tier

**Subscription**:
การจ่ายเงินรายเดือนของ Owner สำหรับ Plan หนึ่ง ต่ออายุอัตโนมัติจนกว่าจะยกเลิก
_Avoid_: membership, license

**Trial**:
ช่วง 7 วันแรกของ Subscription ที่ยังไม่ถูกเก็บเงิน Owner ต้องใส่บัตรก่อนเริ่ม และได้ครั้งเดียวต่อ Account
_Avoid_: free plan, demo

**Launch price**:
ราคาพิเศษของ Plan สำหรับคนที่สมัครในช่วง 3 เดือนแรกหลังเปิดขาย คงอยู่ตลอดไปตราบที่ Subscription ไม่ขาด
และไม่ย้ายไปกับ Plan ใหม่เมื่อเปลี่ยน Plan
_Avoid_: early bird, discount, grandfathered price

**Promotion code**:
โค้ดที่ Owner กรอกตอนจ่ายเงินเพื่อรับส่วนลด System admin ออกให้ได้ เช่นโค้ดลด 100% ใช้ได้ครั้งเดียว
_Avoid_: coupon (นั่นคือส่วนลดที่อยู่เบื้องหลังโค้ด), Signup code

## พื้นที่

**Quota**:
ขนาดรวมสูงสุดของไฟล์ media ในทุก Workspace ที่ Owner เป็นเจ้าของ มาจาก Plan และ System admin
ปรับรายคนได้ ไฟล์ที่ Member อัปโหลดนับเข้า Quota ของเจ้าของ Workspace
_Avoid_: storage limit, allowance

**Storage used**:
ขนาดรวมของไฟล์ media ที่ตอนนี้นับเข้า Quota ของ Owner
_Avoid_: usage (คลุมเครือ)

**Video policy**:
สิ่งที่ระบบทำกับวิดีโอของโพสต์หลังเผยแพร่สำเร็จ ตั้งต่อ Owner มี 3 แบบ: Keep, Delete หรือ Backup
_Avoid_: retention, cleanup

**Backup**:
การอัปโหลดวิดีโอขึ้น Google Drive ของ Owner แล้วลบออกจากที่จัดเก็บของเรา
_Avoid_: archive, sync

**Disk guard**:
ตัวกันส่วนกลางที่ปฏิเสธการอัปโหลดของทุกคน เมื่อพื้นที่ว่างบนดิสก์ของเซิร์ฟเวอร์ต่ำกว่าเกณฑ์ ทำงานแยกจาก Quota
_Avoid_: global quota

## AI

**AI credits**:
หน่วยที่ใช้นับการใช้ฟีเจอร์ AI ของ Owner ในแต่ละเดือน ได้ตาม Plan หมดแล้วใช้ AI ไม่ได้จนถึงรอบถัดไป
ลูกค้าไม่เห็นว่าเบื้องหลังใช้ผู้ให้บริการหรือโมเดลอะไร
_Avoid_: tokens, AI quota, AI usage

## โพสต์

**Cover**:
ภาพนิ่งที่เครือข่ายแสดงแทนวิดีโอก่อนกดเล่น ได้มาจากเฟรมในคลิปหรือรูปที่อัปโหลดเอง ตั้งที่ตัววิดีโอครั้งเดียวแล้วใช้กับทุกช่องที่โพสต์คลิปนั้น
_Avoid_: thumbnail, poster
