# The RX Desk: Idea

> เอกสารตั้งต้นของโปรเจกต์ ใช้เป็นจุดเริ่มของ /grill-with-docs และ /to-spec
> repo นี้เป็น public ห้ามใส่ credentials, IP ของ VPS หรือข้อมูลส่วนตัว

## มันคืออะไร

แพลตฟอร์ม Social Media Management แบบ SaaS ภายใต้แบรนด์ The RX Desk สร้างจาก TryPost (AGPL-3.0)
ขายทั่วโลก เริ่มที่ตลาดไทย โดเมน therxdesk.com

- ทุก Plan ใช้ฟีเจอร์ได้ครบเท่ากัน ไม่จำกัด Workspace, Channel, Member และจำนวนโพสต์
- Plan ต่างกันที่พื้นที่จัดเก็บ (Quota) และ AI credits เท่านั้น
- ราคาเป็น USD เก็บเงินรายเดือนผ่าน Stripe

### เฟส 1 (เสร็จแล้ว, live 17 พฤศจิกายน 2026)
TryPost แบบ self-host หนึ่งชุดบน VPS ที่เจ้าของกับกลุ่มเพื่อนใช้ร่วมกัน สมัครด้วย Signup code ไม่มี billing
(`SELF_HOSTED=true`) งานของเฟส 1 ดูหัวข้อ "เฟส 1: งานที่ทำแล้ว" ด้านล่าง

### เฟส 2 (กำลังทำ): เปิดขายเป็น SaaS
ตัดสินใจเมื่อ 18 พฤศจิกายน 2026 ดูหัวข้อ "เฟส 2" ด้านล่าง

## หลักการ

- ใช้ของเดิมของ TryPost ให้มากที่สุด ไม่แก้ OAuth, การเผยแพร่โพสต์ หรือการตั้งเวลา
- ฝั่ง social API ทำแค่ตั้ง credentials, ปรับ callback URL ให้เป็นโดเมนของเรา และทดสอบ
- โค้ดที่เพิ่มต้องแยกออกจากโค้ดเดิม เพื่อให้ดึงอัปเดตจาก upstream ได้ง่าย
- ทำตาม AGENTS.md และ .ai/rules ยกเว้นข้อที่ CLAUDE.md override ไว้
- ทำตาม AGPL: เก็บ LICENSE และ copyright เดิมไว้ มีลิงก์ไปที่ source code ให้ผู้ใช้เข้าถึงได้
  และ push โค้ดทุกเวอร์ชันที่ deploy ขึ้น repo สาธารณะ
- **ระหว่างพัฒนาเฟส 2 ตัวที่ deploy อยู่ต้องใช้ได้ปกติตลอด** (ดู `docs/adr/0005-production-stays-self-hosted-until-launch.md`)

## เฟส 2: เปิดขายเป็น SaaS

### Plan และราคา (USD ต่อเดือน)

| Plan | Quota | AI credits/เดือน | ราคาจริง | Launch price |
| --- | --- | --- | --- | --- |
| Mini | 2 GB | 2,500 | $11.9 | $7.9 |
| Standard | 5 GB | 6,250 | $17.9 | $11.9 |
| Plus | 20 GB | 25,000 | $29.9 | $19.9 |
| Max | 50 GB | 62,500 | $49.9 | $29.9 |

- AI credits เพิ่มตามสัดส่วน GB (1,250 credits ต่อ GB)
- **Launch price**: คนที่สมัครใน 3 เดือนแรกนับจากวันเปิดขาย ได้ราคานี้ตลอดไปตราบที่ subscription ไม่ขาด
  ล็อกเฉพาะ Plan ที่สมัครไว้ เปลี่ยน Plan เมื่อไรได้ราคาจริงของ Plan ใหม่ (ดู ADR 0002)
- **Trial**: ทดลองใช้ฟรี 7 วัน ต้องใส่บัตรผ่าน Stripe ก่อน ทดลองได้ครั้งเดียวต่อ Account
- เพื่อนจากเฟส 1: แอดมินออก Promotion code ลด 100% ใช้ได้ครั้งเดียว ให้กรอกตอน checkout

### งานที่ต้องทำ

0. **งานที่คนต้องทำเอง (ใช้ /wizard)** เริ่มทันที เพราะใช้เวลาหลายสัปดาห์
   - Meta: Business Verification + App Review (Advanced Access) ตอนนี้จำกัด tester 50 คน
   - Google: OAuth verification ของ YouTube scopes + YouTube API quota audit
   - TikTok: App Review + Content Posting audit + domain verification (รวมโดเมนของ R2)
   - ธุรกิจ: นิติบุคคลสำหรับ Stripe, ภาษี (VAT ไทย, ภาษีบริการดิจิทัลต่างประเทศ), นโยบายคืนเงิน
   - Stripe: 4 Product, แต่ละ Plan มี Price ราคาจริงและ Launch price, coupon 100% สำหรับ Promotion code
1. **เอกสาร** (ข้อนี้) idea.md, GLOSSARY.md, CLAUDE.md, ADR
2. **Backup Postgres** ตอนนี้ยังไม่มีเลย ใช้ `laravel-database-backup` ของ upstream ส่งไป R2 bucket แยก (private)
   ต้องเปิดก่อนงานอื่นที่แตะข้อมูล production
3. **ย้าย media ไป R2 ทั้งหมด** ก่อนเปิดขาย (ADR 0001)
4. **หน้าแอดมิน** ที่ `admin.therxdesk.com` (ADR 0004): ลูกค้า, Plan, สถานะ subscription, Storage used,
   AI credits, override Quota/credits, ระงับบัญชี, ออก Promotion code, ภาพรวมระบบ, offers
5. **Billing และ Plan**: Quota และ AI credits มาจาก Plan, Welcome แสดง 4 Plan, Trial 7 วันเป็นค่าตั้งต้น,
   Launch price, เปิด Stripe webhook, ไล่ทุกจุดที่ `SELF_HOSTED` คุม
6. **AI credits** (ADR 0003): เปิด AI ผ่าน OpenCode, นับเครดิตต่อ Account ต่อเดือน, ไม่เปิดเผย provider/โมเดล
7. **กฎหมาย**: เขียน Terms และ Privacy ใหม่สำหรับบริการเชิงพาณิชย์ (ค่าบริการ, ต่ออายุอัตโนมัติ, คืนเงิน,
   AI credits, ระงับบัญชี, ลบข้อมูลหลังเลิกใช้, PDPA + GDPR, sub-processors โดยเรียก AI ว่า "ผู้ให้บริการ AI ภายนอก")
8. **หน้าเว็บสาธารณะ**: landing + pricing (TikTok review ต้องมี public homepage), README ของเราเอง
9. **บัญชีหลังเลิกจ่าย**: เตือนแล้วลบข้อมูลหลังจาก access หยุดไป N วัน
10. **วันเปิดขาย**: ออก Promotion code ให้เพื่อน → ตั้งค่า Stripe/Plan ใน production → `SELF_HOSTED=false`

ลำดับ: 0 (เริ่มยื่นทันที) → 1 → 2 → 3 → 4 → 5 + 6 → 7 → 8 → 9 → 10
แต่ละข้อแตกเป็น issue ผ่าน /to-spec → /to-tickets

### ตัดสินใจแล้ว (เฟส 2, 18 พฤศจิกายน 2026)

| เรื่อง | ข้อสรุป |
| --- | --- |
| ตลาด | ขายทั่วโลก เริ่มที่ไทย |
| สกุลเงินและช่องทางจ่าย | USD, Stripe บัตร ตัดอัตโนมัติรายเดือน (โค้ด Cashier ของ upstream) PromptPay ไว้ทีหลัง (Stripe PromptPay ตัดอัตโนมัติไม่ได้) |
| Plan | Mini/Standard/Plus/Max ต่างกันแค่ Quota และ AI credits ฟีเจอร์อื่นไม่จำกัดเท่ากันหมด |
| Launch price | Stripe Price แยกของแต่ละ Plan ไม่ใช้ coupon ล็อกเฉพาะ Plan เดิม (ADR 0002) |
| Trial | 7 วัน ต้องใส่บัตร ทุก Owner ใหม่ได้ ทดลองได้ครั้งเดียวต่อ Account |
| การสมัคร | เปิดให้สมัครได้ทุกคนหลังวันเปิดขาย Signup code ใช้เฉพาะโหมด self-hosted |
| เพื่อนจากเฟส 1 | Promotion code ลด 100% ใช้ได้ครั้งเดียว แอดมินออกให้ |
| พื้นที่จัดเก็บ | ย้าย media ไป Cloudflare R2 ทั้งหมดก่อนเปิดขาย ไม่ทำแบบเก็บในเครื่องก่อนแล้วล้นไป R2 (ADR 0001) |
| AI | เปิดตามระบบเดิมของ TryPost ผ่าน API key ของ OpenCode คิดเป็น AI credits รายเดือนตาม Plan ไม่เปิดเผย provider หรือโมเดลให้ลูกค้าเห็น (ADR 0003) |
| ภาษา | ใช้ 16 ภาษาเดิมของ upstream ไม่เพิ่มภาษาไทย |
| แอดมิน | มีหน้าเว็บแอดมินที่ admin.therxdesk.com ใช้แพ็กเกจ open source (ADR 0004) CLI เดิมยังใช้ได้ |
| production ระหว่างพัฒนา | คง `SELF_HOSTED=true` จนถึงวันเปิดขาย โค้ดใหม่ไม่เปลี่ยนพฤติกรรมในโหมด self-hosted (ADR 0005) |

### คำถามที่ยังเปิดอยู่ (เฟส 2)

- **โควตา API ของแพลตฟอร์มที่ทุกลูกค้าใช้ร่วมกัน** จะเปิดรับคนทั่วโลกได้อย่างไร (YouTube upload 100 ครั้ง/วัน
  ต่อทั้งแอปจนกว่าจะผ่าน audit, X คิดเงินต่อโพสต์) รวมถึง fair use, captcha หน้าสมัคร และ throttle การอัปโหลด
  (เลื่อนไว้ คุยกันอีกรอบ)
- แพ็กเกจแอดมินตัวไหน (แนะนำ Filament, MIT) เป็นการเพิ่ม dependency ต้องยืนยันก่อนติดตั้ง
- ข้อความใหม่ที่ลูกค้าเห็น (ราคา, billing, AI credits) ยังเป็นอังกฤษอย่างเดียวตามกติกาเดิม หรือแปล 16 ภาษา
- รอบรีเซ็ต AI credits: ตามรอบบิลหรือเดือนปฏิทิน และมีการซื้อเติมไหม
- ลบข้อมูลหลังเลิกจ่ายกี่วัน (N)
- Owner guard ใน `AcceptInvite` (Owner ที่ยังมี workspace รับ Invite ไม่ได้) ยังเหมาะกับลูกค้าจ่ายเงินไหม
- cookie consent / PDPA ของหน้าเว็บ (Google Fonts โหลดจาก Google, GTM)
- วันเปิดขายจริง (เริ่มนับ 3 เดือนของ Launch price)

## เฟส 1: งานที่ทำแล้ว

1. **เปลี่ยนแบรนด์** เป็น The RX Desk ไม่เปลี่ยนพฤติกรรมของระบบ
2. **สมัครด้วย Signup code** ใช้ได้ครั้งเดียว ตรวจและทำเครื่องหมายใน transaction เดียวกับการสร้างบัญชี
3. **แอดมินผ่าน CLI**: `rxdesk:code`, `rxdesk:quota`, `rxdesk:usage`
4. **Quota** ต่อ Owner นับรวมทุก workspace, Disk guard, หลอด Storage used ในเมนูบัญชี
5. **Video policy**: Keep, Delete หรือ Backup ไป Google Drive ของ Owner
6. **Cover** ของวิดีโอบน Facebook, Instagram และ YouTube

### ตัดสินใจแล้ว (เฟส 1)

แถวที่มี ⟶ ถูกแทนที่ด้วยการตัดสินใจของเฟส 2 แล้ว

| เรื่อง | ข้อสรุป |
| --- | --- |
| แอดมิน | ⟶ เฟส 2 มีหน้าแอดมินที่ admin.therxdesk.com (เดิม: CLI เท่านั้น ไม่มีหน้า admin) |
| การสมัคร | ⟶ เฟส 2 เปิดสมัครทั่วไป (เดิม: ต้องใช้ Signup code ใช้ได้ครั้งเดียว) |
| โควตาของไฟล์ที่สมาชิกอัปโหลด | นับเข้าโควตาของเจ้าของ workspace |
| ภาษา | ข้อความที่เพิ่มใหม่เป็นภาษาอังกฤษอย่างเดียว อยู่ใน `lang/<locale>/rxdesk.php` (ดู CLAUDE.md) |
| ตัดการเชื่อมต่อ channel | ทำตามกฎเดิม คือลบโพสต์ของ channel นั้นทั้งหมด ประวัติจึงอยู่เฉพาะตอนที่ channel ยังเชื่อมต่ออยู่ |
| ติดตั้ง | Docker Compose ของเราเอง (`compose.rxdesk.yaml` + Caddy) แยกจาก `compose.prod.yaml` ของ upstream |
| คำศัพท์ | ใช้ตาม `GLOSSARY.md` |
| Signup code | ไม่ผูกอีเมล ไม่มีวันหมดอายุ ใช้ได้ครั้งเดียว แอดมินยกเลิกโค้ดที่ยังไม่ถูกใช้ได้ คนที่สมัครด้วยโค้ดจะเป็น Owner ของ Account ตัวเอง และสร้าง workspace ได้ไม่จำกัด ⟶ เฟส 2 ใช้เฉพาะโหมด self-hosted |
| Quota | ⟶ เฟส 2 มาจาก Plan (แอดมินยัง override รายคนได้) (เดิม: ค่าเริ่มต้น 5 GB ตั้งใน env) เมื่อเกิน Quota ระบบปฏิเสธการอัปโหลดใหม่ แต่โพสต์ที่ตั้งเวลาไว้แล้วยังเผยแพร่ตามปกติ และไม่มีอะไรถูกลบอัตโนมัติ |
| Disk guard | เมื่อพื้นที่ว่างบนดิสก์ต่ำกว่า 10 GB (ตั้งใน env) ระบบปฏิเสธการอัปโหลดของทุกคน โพสต์ที่ตั้งเวลาไว้แล้วยังเผยแพร่ได้ ⟶ เฟส 2 media อยู่ R2 Disk guard เฝ้าแค่ไฟล์ชั่วคราวบน VPS |
| ระดับของการตั้งค่า "ลบทิ้ง / backup" | ตั้งระดับ Owner ใช้กับทุก workspace ของ Owner และ backup ไปที่ Drive ของ Owner |
| อายุประวัติโพสต์ | ใช้ค่าเดิมของ upstream คือ 730 วัน (`POST_HISTORY_RETENTION_DAYS`) |
| env / proxy | ปิด Google/GitHub login, PostHog, Nightwatch และ Changelog บล็อก `/stripe/*` ที่ Caddy (⟶ เฟส 2 เปิดเมื่อตั้ง webhook secret) ไม่เปิดพอร์ตของแอปออกสู่ host และตั้ง `TRUSTED_PROXIES` ให้ชี้ไปที่ IP ของ Caddy |
| ไฟล์ที่ไม่นับเข้า Quota | ไม่นับไฟล์ชั่วคราว (ระบบลบเองภายใน 1–7 วัน) ตรวจ Quota ก่อนเริ่มอัปโหลด โดยคิดจาก Storage used บวกขนาดไฟล์ที่กำลังจะอัปโหลด |
| บีบอัดรูป | เลื่อนไปทีหลัง ดูจาก `rxdesk:usage` ว่ารูปกินพื้นที่จริงเท่าไร (มี `intervention/image` อยู่แล้ว) |
| Member | คนที่รับ Invite จะถูกย้ายเข้า Account ของผู้เชิญ (Account เปล่าของเขาถูกลบ) จึงสร้าง workspace เองไม่ได้ และไฟล์ที่เขาอัปโหลดนับเข้า Quota ของ Account นั้น |
| backup แต่ Drive ใช้ไม่ได้ | ต้องเชื่อม Drive สำเร็จก่อนจึงเลือก backup ได้ ถ้าภายหลัง Drive ใช้ไม่ได้ ระบบเก็บไฟล์ไว้ (นับเข้า Quota) ทำเครื่องหมายว่าล้มเหลว และแจ้งให้ Owner เชื่อม Drive ใหม่ |
| โพสต์เดียวไปหลาย channel | backup ครั้งเดียวต่อ `post_group_id` |
| เวลาที่เริ่มทำงาน | รอ 1 ชั่วโมงหลังเผยแพร่สำเร็จ ใช้กับทุกแพลตฟอร์ม |
| retry ของ backup | retry อัตโนมัติประมาณ 1 ชั่วโมง ถ้ายังล้มเหลว ส่งอีเมลหา Owner และแสดง "Backup failed" พร้อมปุ่ม Retry ในรายละเอียดโพสต์ ไฟล์เก็บไว้จนกว่า backup จะสำเร็จ |
| โฟลเดอร์บน Drive | `The RX Desk/<ชื่อ workspace>/<ปี-เดือน>/<วันที่>_<channel>_<post id>.<ext>` |
| ประวัติโพสต์หลังลบวิดีโอ | เอาวิดีโอออกจากโพสต์ แล้วเก็บสถานะแยก ("ลบแล้ว" หรือ "backup แล้ว" พร้อมลิงก์ Drive) แสดงเป็นบรรทัดเล็กในรายละเอียดโพสต์ |
| Video policy | Owner เลือกได้ 3 แบบ: Keep (ค่าเริ่มต้น), Delete และ Backup ลบไฟล์ผ่าน `DeleteOwnedMedia` ของเดิม |
| Owner รับ Invite ของ Owner คนอื่น | กันด้วยโค้ด: ถ้าผู้รับเป็น Owner ที่ยังมี workspace อยู่ ให้ปฏิเสธพร้อมข้อความบอกให้ใช้อีเมลอื่นสมัครเป็น Member (เฟส 2: เปิดเป็นคำถามอีกครั้ง) |
| แสดง Storage used | หลอด "ใช้ไป X จาก Y" ในเมนูบัญชี Owner เห็นคนเดียว |
| ที่วาง Video policy, Drive และ Retry | ท้ายหน้า Settings → Preferences, ปุ่ม Retry อยู่ที่บรรทัดสถานะในรายละเอียดโพสต์ |
| เปลี่ยน Video policy ระหว่างมีงานค้าง | ใช้ค่าที่ตั้งอยู่ตอนงานเริ่มทำจริง ถ้าเปลี่ยนเป็น Keep งานจะหยุดและไฟล์ยังอยู่ |
| โลโก้และลิงก์ภายนอก | โลโก้ "RX" บนสี่เหลี่ยมมุมมน ลิงก์ Terms/Privacy ชี้ไปหน้า `/terms` `/privacy` ของแอป เมนู Help มีลิงก์ "Source code" |
| แบรนด์ในไฟล์ภาษา | ใช้ `rxdesk:rebrand-lang` แทน `TryPost` → `The RX Desk` ใน `lang/*` ทุกภาษา รันซ้ำทุกครั้งหลังดึงอัปเดตจาก upstream |

## ขั้นต่อไป
1. ~~เฟส 1: /research, /grill-with-docs, /to-spec (#1), /to-tickets (#2–#14), /implement~~ live 17 พฤศจิกายน 2026
2. เฟส 2: ~~วิเคราะห์และตัดสินใจ (18 พฤศจิกายน 2026)~~ → เอกสาร (ข้อ 1) → คุยเรื่องโควตา API ของแพลตฟอร์ม
   → /to-spec → /to-tickets → /implement ตามลำดับในหัวข้อ "งานที่ต้องทำ"
