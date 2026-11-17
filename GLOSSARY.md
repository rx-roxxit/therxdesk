# The RX Desk

TryPost แบบ self-host หนึ่งชุด ที่เจ้าของ VPS แชร์ให้กลุ่มเพื่อนใช้ แต่ละ Owner ใช้งานเหมือนลง TryPost ไว้ใช้คนเดียว โดยมี System admin คอยจัดสรรพื้นที่ให้

## คน

**System admin**:
ผู้ดูแล VPS ที่จัดการระบบผ่าน artisan CLI เท่านั้น ไม่มีหน้า admin บนเว็บ
_Avoid_: root admin, super admin

**Owner**:
User ที่เป็นเจ้าของ Account ของตัวเอง (สมัครด้วย Signup code) สร้าง Workspace ได้ไม่จำกัด และเป็นหน่วยที่ใช้นับ Quota
_Avoid_: user (ถ้าหมายถึงเจ้าของ), member

**Member**:
User ที่ถูกเชิญเข้า Workspace ของ Owner คนอื่นด้วย Invite
_Avoid_: guest, collaborator

## การเข้าระบบ

**Signup code**:
โค้ดที่ System admin ออกให้ ใช้สมัครเป็น Owner ได้ครั้งเดียว
_Avoid_: invite code, invitation code, referral code

**Invite**:
คำเชิญของ TryPost เดิม ใช้เชิญคนเข้า Workspace และผูกกับอีเมล ไม่ใช่สิ่งเดียวกับ Signup code
_Avoid_: ใช้คำนี้เรียก Signup code

## พื้นที่

**Quota**:
ขนาดรวมสูงสุดของไฟล์ media ในทุก Workspace ที่ Owner เป็นเจ้าของ System admin เป็นคนกำหนด ไฟล์ที่ Member อัปโหลดนับเข้า Quota ของเจ้าของ Workspace
_Avoid_: storage limit, allowance

**Storage used**:
ขนาดรวมของไฟล์ media ที่ตอนนี้นับเข้า Quota ของ Owner
_Avoid_: usage (คลุมเครือ)

**Video policy**:
สิ่งที่ระบบทำกับวิดีโอของโพสต์หลังเผยแพร่สำเร็จ ตั้งต่อ Owner มี 3 แบบ: Keep, Delete หรือ Backup
_Avoid_: retention, cleanup

**Backup**:
การอัปโหลดวิดีโอขึ้น Google Drive ของ Owner แล้วลบออกจาก VPS
_Avoid_: archive, sync

**Disk guard**:
ตัวกันส่วนกลางที่ปฏิเสธการอัปโหลดของทุกคน เมื่อพื้นที่ว่างบนดิสก์ของ VPS ต่ำกว่าเกณฑ์ ทำงานแยกจาก Quota
_Avoid_: global quota

## โพสต์

**Cover**:
ภาพนิ่งที่เครือข่ายแสดงแทนวิดีโอก่อนกดเล่น ได้มาจากเฟรมในคลิปหรือรูปที่อัปโหลดเอง ตั้งที่ตัววิดีโอครั้งเดียวแล้วใช้กับทุกช่องที่โพสต์คลิปนั้น
_Avoid_: thumbnail, poster
