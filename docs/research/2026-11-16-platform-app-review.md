# ผลวิจัย: การตรวจสอบแอปของ Meta, TikTok และ Google สำหรับ The RX Desk

**วันที่ตรวจเอกสาร: 2026-11-16** (นโยบายเหล่านี้เปลี่ยนบ่อย ให้ตรวจซ้ำก่อนยื่นจริง)

ขอบเขต: กลุ่มเพื่อนเล็ก ๆ แบบ invite-only ใช้เองแบบไม่แสวงหากำไร บนโดเมน therxdesk.com แหล่งอ้างอิงใช้เฉพาะเอกสารทางการของ developers.facebook.com, developers.tiktok.com, developers.google.com และ support.google.com

## สรุปด่วน

| แพลตฟอร์ม | เพื่อนใช้ได้โดยไม่ต้องรีวิว? | รีวิวอะไรถึงจะปลดข้อจำกัด | ข้อจำกัดหลัก |
| --- | --- | --- | --- |
| Meta (Facebook Page + Instagram) | ได้ ถ้าเพื่อนแต่ละคนมี role บนแอป (tester ฯลฯ) และยอมรับคำเชิญ | App Review + Business Verification เพื่อให้ได้ Advanced Access | Development mode ใช้ได้เฉพาะ role users; tester ของแอปที่ไม่ได้ผูก Business Manager ที่ verify แล้วมักได้ไม่เกิน 50 |
| TikTok | ใช้ได้แต่โพสต์ได้เฉพาะ `SELF_ONLY` (private) | Content Posting API audit | unaudited client: private เท่านั้น, ผู้ใช้โพสต์ได้ไม่เกิน 5 คนต่อ 24 ชม., บัญชีต้องเป็น private ตอนโพสต์ |
| YouTube Data API | ได้ และ**ตามเอกสารปัจจุบันไม่ล็อก private แล้ว** (ยกเลิกเมื่อ 2026-11-02) | Compliance audit ยัง "แนะนำอย่างยิ่ง" แต่ไม่บังคับสำหรับอัปโหลด | OAuth consent ยังมีข้อจำกัดของ Google (ดูข้างล่าง) |
| Google OAuth (consent screen) | ได้ใน Testing mode (test user สูงสุด 100) | OAuth app verification (ถ้ามี sensitive/restricted scope) | Testing mode: authorization/refresh token หมดอายุใน 7 วัน; unverified app จำกัด 100 ผู้ใช้ใหม่ตลอดอายุโปรเจกต์ |
| Google Drive picker (`drive.file`) | ได้ ไม่ต้อง verify (non-sensitive) | ไม่ต้อง | ไม่มี |

## Scope ที่ TryPost ขอจริงในโค้ด

| ช่องทาง | Scope | ที่มาในโค้ด |
| --- | --- | --- |
| Facebook Page | `public_profile`, `pages_show_list`, `pages_read_engagement`, `pages_manage_posts`, `read_insights`, `business_management` | `app/Http/Controllers/Auth/FacebookController.php:27-34` |
| Instagram (Instagram Login) | `instagram_business_basic`, `instagram_business_content_publish`, `instagram_business_manage_insights` | `app/Http/Controllers/Auth/InstagramController.php:23-27` |
| Instagram ผ่าน Facebook | `public_profile`, `pages_show_list`, `pages_read_engagement`, `business_management`, `instagram_basic`, `instagram_content_publish`, `instagram_manage_insights` | `app/Http/Controllers/Auth/InstagramFacebookController.php:41-49` |
| TikTok | ค่าเริ่มต้น `user.info.basic,user.info.profile,user.info.stats,video.publish,video.upload,video.list` (ปรับได้ด้วย env `TIKTOK_SCOPES`) | `config/trypost.php:526`, `app/Http/Controllers/Auth/TikTokController.php:28-31` |
| YouTube (connect) | `youtube.upload`, `youtube.readonly`, `youtube.force-ssl`, `yt-analytics.readonly` | `app/Http/Controllers/Auth/YouTubeController.php:24-29` |
| YouTube (analytics เพิ่มเติม) | รายการใน `YouTubeScopes` มี `youtube`, `yt-analytics-monetary.readonly` ด้วย | `app/Support/Analytics/YouTubeScopes.php:21-33` |
| Google Drive picker | `https://www.googleapis.com/auth/drive.file` | `app/Services/Media/Sources/GoogleMediaOAuth.php:22` |
| Google Photos picker (มีในโค้ด ไม่ได้ถามมา) | `photospicker.mediaitems.readonly` | `app/Services/Media/Sources/GoogleMediaOAuth.php:23` |
| Google login (ล็อกอินเข้าแอป) | ไม่มีรายการ scope ใน `GoogleController.php` (ไม่พบคำว่า scope) จึงใช้ค่าเริ่มต้นของ Socialite google driver (ปกติ openid/profile/email) ต้องยืนยันตอนตั้งค่า | `app/Http/Controllers/Auth/GoogleController.php` (ไม่มีการกำหนด scope) |
| Google Business (ไม่อยู่ในขอบเขต แต่ใช้ Google Cloud ตัวเดียวกันได้) | `userinfo.profile`, `userinfo.email`, `business.manage` | `app/Http/Controllers/Auth/GoogleBusinessController.php:24-28` |

redirect ของแต่ละแพลตฟอร์มมาจาก env: `FACEBOOK_CLIENT_REDIRECT`, `INSTAGRAM_CLIENT_REDIRECT`, `TIKTOK_CLIENT_REDIRECT`, `GOOGLE_CLIENT_REDIRECT`, `GOOGLE_AUTH_CALLBACK`, `GOOGLE_MEDIA_CLIENT_REDIRECT` (`config/services.php:61,68,75,89,96,147`)

## Meta (Facebook Page + Instagram)

**1. Development mode**
- แอปใหม่เริ่มที่ Development mode: "All newly created apps start out in Development mode". ใช้ได้เฉพาะ role users, แอปค้นหาสาธารณะไม่ได้ ที่มา: https://developers.facebook.com/docs/development/build-and-test/app-modes
- เอกสารให้ "Standard Access" อัตโนมัติ: "Permissions with Standard Access can only be requested from app users who have a role on the requesting app." ที่มา: https://developers.facebook.com/docs/graph-api/overview/access-levels
- ไม่ต้อง App Review ถ้าผู้ใช้ทุกคนมี role: "If your app will only be used by app users who have a role on the app itself, App Review is not required." ที่มา: https://developers.facebook.com/docs/resp-plat-initiatives/individual-processes/app-review
- Tester: "Testers can grant the app any permission while it is in development" และใช้ฟีเจอร์ได้ทั้งหมด แต่แก้ตั้งค่าแอปไม่ได้ ผู้ที่ไม่มี role ต้องรับคำเชิญใน Facebook Settings > Apps and Websites > Requests และยอมรับ Meta Platform Terms ที่มา: https://developers.facebook.com/docs/development/build-and-test/app-roles
- จำนวน: "Most unlinked apps can have up to 50 testers"; แอปที่ผูก Business Manager ที่ผ่าน Business Verification มี analytics users + testers รวมได้ถึง 500 (ที่มาเดียวกัน)
- Page / IG ต้องมี role อะไร: **ไม่พบ** ข้อความทางการที่ระบุตรง ๆ ในหน้าที่ดึงมา (ดู "ยังไม่ยืนยัน") โดยหลักการ Instagram ต้องเป็น professional account (Business หรือ Creator): หน้า https://developers.facebook.com/docs/instagram-platform/instagram-api-with-instagram-login/get-started ระบุว่า `account_type` คือ Business หรือ Media_Creator และหน้า https://developers.facebook.com/docs/instagram-platform/instagram-api-with-instagram-login ระบุว่าไม่ต้องผูก Facebook Page. ส่วน Instagram ผ่าน Facebook ต้องมี Page ที่ผูก IG (โค้ดกรองเฉพาะ Page ที่มี `instagram_business_account`, `InstagramFacebookController.php:97`)
- แอปต้องเป็นประเภท Business: "If your current Meta app type is not a Business type app you will need to create a new app" (หน้า get-started ด้านบน)

**2. การรีวิวที่ปลดข้อจำกัด**
- Advanced Access ต้อง App Review รายสิทธิ์ และต้องมี Business Verification: "Advanced Access now requires Business Verification" และ "Business Verification is required to get Advanced Access." ที่มา: https://developers.facebook.com/docs/graph-api/overview/access-levels ; ประกาศ https://developers.facebook.com/blog/post/2023/02/01/developer-platform-requiring-business-verification-for-advanced-access/
- Business Verification เป็นกระบวนการแยกจาก App Review (หน้า app-review ข้างบน) ปกติต้องเป็นธุรกิจที่มีเอกสารยืนยัน ซึ่งโปรเจกต์ส่วนตัวแบบไม่ใช่นิติบุคคลอาจติดขัด
- ที่ต้องส่ง/ระยะเวลา: หน้าที่ดึงมาไม่ระบุระยะเวลา ดูรายละเอียดที่ https://developers.facebook.com/documentation/resp-plat-initiatives/individual-processes/app-review/content (ไม่ได้ดึงเนื้อหามา)

**3. อยู่ใน Development mode ตลอดไปได้ไหม**
ได้ตามเอกสาร ตราบที่ทุกคนที่ใช้มี role บนแอป (แนวคิด: เพื่อนทุกคนเป็น tester). ข้อแลกเปลี่ยน: ต้องเชิญและรอเพื่อนรับคำเชิญทีละคน, เพดาน 50 tester, เพื่อนที่ไม่รับเชิญใช้ไม่ได้, ยังไม่ยืนยันว่า permission ที่ใช้ไม่ได้ใช้ต้อง Live mode เพื่อให้ publish ได้จริง (ดู "ยังไม่ยืนยัน")

**4. Redirect/โดเมน**
- ต้องตั้ง Valid OAuth Redirect URIs ในแอป (ตรวจรูปแบบ HTTPS ไม่ได้ยืนยันจากเอกสารที่ดึงมา, ใช้ https อยู่แล้ว). Live mode ต้องมี privacy policy URL ตามหน้า App settings: ไม่ได้ยืนยันจากหน้าที่ดึงมา (หน้า app-modes ไม่ระบุ)

## TikTok

**1. Unaudited client**
- "All content posted by unaudited clients will be restricted to private viewing mode." ที่มา: https://developers.tiktok.com/doc/content-posting-api-get-started
- ที่มา: https://developers.tiktok.com/doc/content-sharing-guidelines : unaudited client โพสต์ได้เฉพาะ `SELF_ONLY`; "Unaudited API Clients can allow up to 5 users to post in a 24 hour window."; บัญชีที่โพสต์ต้องเป็น private ตอนโพสต์ ผลลัพธ์: โพสต์ที่ลงแล้วจะเป็นสาธารณะได้เมื่อเจ้าของบัญชีเปลี่ยนบัญชีเป็นสาธารณะและเปลี่ยนความเป็นส่วนตัวของแต่ละโพสต์เอง
- ต้องได้รับอนุมัติ scope `video.publish` และผู้ใช้เป้าหมายต้องอนุญาตแอป (get-started ข้างบน) ; ภาพ/วิดีโอแบบ `PULL_FROM_URL` ต้องมาจาก verified domain หรือ URL prefix

**2. การรีวิว**
- ต้องผ่าน audit: "your API client must undergo an audit to verify compliance with our Terms of Service." (FAQ https://developers.tiktok.com/doc/getting-started-faq)
- App review ทั่วไป: "App review may take several days to two weeks after submission." (FAQ เดียวกัน) แต่ระยะเวลา audit ของ Content Posting ไม่ระบุ
- สิ่งที่ App Review Guidelines ขอ (https://developers.tiktok.com/doc/app-review-guidelines): เว็บไซต์จริงที่พัฒนาแล้ว (หน้า landing/login อย่างเดียวไม่ผ่าน) พร้อมลิงก์ Privacy Policy และ Terms of Service ที่เห็นได้ชัด, วิดีโอเดโมอย่างน้อย 1 ชิ้น (สูงสุด 5 ชิ้น ชิ้นละไม่เกิน 50 MB) แสดง flow ครบ, โดเมนในวิดีโอต้องตรงกับเว็บไซต์ที่ยื่น
- **ข้อควรระวังสำคัญ:** guidelines ระบุว่า client "must not be limited to test applications and should be intended for a wide audience, not limited to internal groups/private use" (พบจากผลค้นหา https://developers.tiktok.com/docs/en/content-sharing-guidelines ; ประโยคนี้ไม่ได้ยืนยันซ้ำด้วยการดึงหน้าเต็ม) และ FAQ ว่า "Beta or development versions, incomplete apps, and test versions are not encouraged and will not be approved" จึงมีความเสี่ยงสูงที่แอปกลุ่มเพื่อนแบบ private จะไม่ผ่าน audit

**3. อยู่ใน unaudited ตลอดไปได้ไหม**
ได้ แต่ทุกโพสต์ต้องเป็น `SELF_ONLY` และบัญชีเพื่อนต้อง private ตอนโพสต์ ถ้าจุดประสงค์คือ "ตั้งเวลาโพสต์สาธารณะ" ใช้ไม่ได้จริง เพราะเพื่อนต้องเข้าแอป TikTok เปลี่ยนเป็น public เองทีละโพสต์ ตัวเลือกจริง ๆ: ใช้ TikTok แค่เป็น "ส่งร่างเข้า private" หรือข้าม TikTok

**4. Redirect/โดเมน** ที่มา https://developers.tiktok.com/doc/login-kit-web : ไม่เกิน 10 URI, ยาว < 512 ตัวอักษร, ต้องเป็น absolute ขึ้นต้น `https`, ห้ามมี query parameter, ห้ามมี fragment (#). ต้อง verify domain/URL prefix สำหรับสื่อแบบ URL (ในโหมด Sandbox: ผลค้นหาระบุว่าต้อง URL verification เฉพาะ Content Posting API)

## YouTube Data API

**1. สถานะปัจจุบัน**
- ประวัติเดิม (2020-07-28): uploads จากโปรเจกต์ unverified ที่สร้างหลังวันนั้นถูกล็อกเป็น private "To lift this restriction, each project must undergo an audit to verify compliance."
- **เปลี่ยนแล้ว:** revision history ลงวันที่ **2026-11-02**: "Videos uploaded through the videos.insert method from unverified API projects are no longer restricted" (กลับนโยบาย 2020) และหน้า videos.insert ระบุ "Videos uploaded from unverified API projects are not restricted to private viewing mode." ที่มา: https://developers.google.com/youtube/v3/revision_history , https://developers.google.com/youtube/v3/docs/videos/insert . ถ้าไม่ระบุ privacy status ค่าเริ่มต้นเป็น public (บางกรณีขึ้นกับตั้งค่าบัญชีผู้สร้าง) จึงควรให้โค้ดส่ง `privacyStatus` ชัดเจนเสมอ
- ผลการค้นหายังระบุว่า Google "strongly recommend" ให้ทำ compliance audit เพื่อรักษาการเข้าถึง API (ไม่ได้ยืนยันถ้อยคำเต็มจากหน้าต้นทางที่ดึงมาตรง ๆ) หน้า audit form เดิมให้ 404 จึงไม่ได้อ้างถึง
- Quota: ไม่ได้ตรวจในรอบนี้ (ดู "ยังไม่ยืนยัน")

**2. Google OAuth ส่วนของ YouTube scope**
- `youtube.upload`, `youtube.force-ssl`, `yt-analytics.readonly`: ไม่ได้ยืนยันระดับ sensitive/restricted จากเอกสารที่ดึงมา (หน้า scopes ถูกตัด) ต้องดูป้ายใน Cloud Console ตอนเพิ่ม scope: "The Google Cloud Console labels each scope's category automatically when you add it to your project." (https://support.google.com/cloud/answer/13463073)
- แอปที่ขอ sensitive/restricted ต้องผ่าน OAuth verification ก่อนใช้แบบสาธารณะ

## Google OAuth app verification / Drive / Google login

- Testing mode: จำกัด test users 100 คน, "Authorizations by test users expire seven days after consent" รวม refresh token ด้วย ที่มา: https://support.google.com/cloud/answer/15549945 . **ผลกระทบจริง:** เพื่อนต้อง reconnect YouTube ทุก 7 วัน ถ้าอยู่ใน Testing และคิวโพสต์ที่ตั้งไว้ล่วงหน้าจะล้มเมื่อ token ตาย
- Unverified app (Production แต่ยังไม่ verify): "100 new users in total" ตลอดอายุโปรเจกต์ แก้/รีเซ็ตไม่ได้ (หน้าเดียวกัน) และผู้ใช้เห็นหน้าจอ "unverified app"
- แอปที่ขอ sensitive/restricted ต้อง verify; restricted ต้อง re-verify ทุกปี; แอปที่ใช้เฉพาะ non-sensitive ไม่ต้อง verify (ขอ brand verification ถ้าอยากให้ชื่อ/โลโก้ขึ้น) ที่มา https://support.google.com/cloud/answer/13463073
- **`drive.file` เป็น non-sensitive** ("Non-sensitive scopes" table): https://developers.google.com/workspace/drive/api/guides/api-specific-auth
- `userinfo.email`, `userinfo.profile`, `openid` ไม่มีป้าย sensitive ในเอกสาร scopes (https://developers.google.com/identity/protocols/oauth2/scopes) แต่หน้านั้นไม่ได้ประกาศ "non-sensitive" ตรง ๆ
- ข้อกำหนด verification (https://support.google.com/cloud/answer/13464321 และผลค้นหาจากหน้าเดียวกัน): homepage บนโดเมนที่ verify แล้ว (ไม่ใช่หน้า login อย่างเดียว) พร้อมลิงก์ privacy policy ที่โดเมนเดียวกัน, ยืนยันความเป็นเจ้าของโดเมนทุกตัวผ่าน Google Search Console, วิดีโอเดโม (ภาษาอังกฤษ แสดง consent flow เต็มและการใช้แต่ละ scope, อัปโหลด YouTube), ระยะเวลาโดยประมาณจากผลค้นหา: brand verification 2-3 วันทำการ, sensitive ~10 วัน, restricted + security assessment หลายสัปดาห์ (ตัวเลขนี้มาจากสรุปผลค้นหา ไม่ได้ยืนยันกับหน้าต้นทางโดยตรง)
- Redirect: "Redirect URIs must use the HTTPS scheme, not plain HTTP" (localhost ยกเว้น), ห้าม raw IP, ต้องตรงกับที่ลงทะเบียนทุกตัวอักษร, ห้าม fragment และ wildcard ที่มา: https://developers.google.com/youtube/v3/guides/auth/server-side-web-apps

## ลำดับที่แนะนำให้เจ้าของเริ่มทำ

1. ตั้ง DNS + HTTPS ให้ therxdesk.com และทำหน้า homepage จริง + Privacy Policy + Terms (ทุกแพลตฟอร์มต้องใช้ และ Google ต้องให้อยู่โดเมนเดียวกัน)
2. Google Cloud: สร้างโปรเจกต์ เปิด YouTube Data API และ Drive/Picker, ตั้ง consent screen, เพิ่มเพื่อนเป็น test users (<= 100) เพื่อเริ่มใช้ได้ทันที; พิจารณายื่น verification เพื่อพ้นกำหนด token 7 วัน (ข้อนี้กระทบการใช้งานจริงมากที่สุด)
3. Meta: สร้างแอปประเภท Business เปิด Development mode ตั้ง redirect URIs แล้วเชิญเพื่อนเป็น tester (ไม่เกิน 50) ให้รับคำเชิญ ใช้ได้โดยไม่ต้อง review ตามเอกสาร
4. TikTok: สร้างแอป เพิ่ม Content Posting API ลองใน Sandbox ตัดสินใจก่อนว่ารับข้อจำกัด private-only ได้หรือไม่ ก่อนลงแรงยื่น audit ซึ่งมีความเสี่ยงไม่ผ่านเพราะเป็นแอปกลุ่มปิด
5. ตัวเลือกเสริม: ถ้าต้องการให้ Meta/Google ผ่านการรีวิวจริง ให้เตรียมวิดีโอเดโมครั้งเดียวใช้ได้หลายที่

## ยังไม่ยืนยัน

- role ที่ Facebook Page / Instagram account ต้องมี (admin ของ Page? Task ที่ต้องมี?) ในเอกสาร Meta ทางการ ไม่พบประโยคตรง ๆ ในหน้าที่ดึง
- Instagram Login: การเพิ่ม Instagram Tester และข้อกำหนดรายละเอียดใน Development mode (หน้าที่ดึงไม่ครอบคลุม)
- Meta: ระยะเวลา App Review/Business Verification; ว่า Business Verification ทำได้ไหมสำหรับบุคคลธรรมดา; ข้อกำหนด privacy policy URL ตอนสลับเป็น Live
- Meta: `business_management`, `read_insights` ต้องการ Advanced Access/ตรวจพิเศษหรือไม่
- TikTok: ระยะเวลาและเนื้อหาแบบฟอร์ม audit, ทางเลือก Sandbox target users, ประโยค "not limited to internal groups/private use" (มาจากผลค้นหา ไม่ได้ดึงหน้าเต็ม)
- YouTube: ระดับ sensitive/restricted ของ `youtube.upload`, `youtube.force-ssl`, `yt-analytics.readonly`; quota; และเนื้อหาหน้าทางการของ compliance audit (URL เดิมให้ 404)
- Google login ใช้ scope อะไรจริง (ต้องดูจาก Socialite google driver ตอนรัน) ; การรายงาน 7-day token expiry กับ scope ที่ non-sensitive อย่างเดียว (ถ้า Testing mode ใช้เฉพาะ non-sensitive เช่น drive.file ยังต้องตรวจว่า 7 วันใช้ไหม)
- ตัวเลขระยะเวลา verification ของ Google (2-3 วัน / ~10 วัน / หลายสัปดาห์) มาจากสรุปผลค้นหา ไม่ได้ยืนยันจากหน้าต้นทาง
