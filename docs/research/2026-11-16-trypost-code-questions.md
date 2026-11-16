# ผล research: คำถามในโค้ด TryPost สำหรับ The RX Desk

วันที่: 2026-11-16 (commit ฐาน `262e6d0f`)
ที่มา: หมวด "ต้อง research ในโค้ด" ของ `idea.md`

หมายเหตุวิธีทำ
- อ่านจากโค้ดใน repo นี้เป็นหลัก อ้างอิงเป็น `path:line`
- โฟลเดอร์ `vendor/` ไม่ได้ติดตั้งในเครื่องที่ research จึงรัน `php artisan route:list` / `config:show` ไม่ได้ และอ่านโค้ดของแพ็กเกจไม่ได้ ส่วนที่ขึ้นกับแพ็กเกจ (Cashier, Socialite, laravel/mcp, Nightwatch) ใช้เอกสาร/ซอร์สบน GitHub ตามเวอร์ชันใน `composer.lock` และระบุไว้ตรงที่ใช้
- แต่ละหัวข้อแยก "โค้ดแสดงว่า" (fact) ออกจาก "ข้อสรุปของผู้ทำ research" (inference)
- ไม่มี credentials, IP หรือข้อมูลส่วนตัวในไฟล์นี้

## สรุปรวม

| # | คำถาม | คำตอบสั้น | ความมั่นใจ |
| --- | --- | --- | --- |
| 1 | เก็บ media ที่ไหน ลบเมื่อไร | Laravel Storage disk เริ่มต้น `public` (local, volume `storage`); `FILESYSTEM_DISK` สลับเป็น s3/r2 ได้; ไม่ใช้ medialibrary ลบเมื่อลบโพสต์/ไอเดีย/workspace/ตัด channel, prune upload ที่ไม่ถูกใช้ 24 ชม., และลบโพสต์ที่ publish แล้วเกิน 730 วัน ไม่มีการลบหลัง publish สำเร็จ | สูง |
| 2 | รู้ได้อย่างไรว่าโพสต์สำเร็จ | `FinalizePostPublication::handle()` เปลี่ยน `posts.status` เป็น `published` แล้ว `PostObserver::saved` ยิง `PostStatusChanged` หลัง commit จุด hook ที่สะอาดสุดคือ listener ของ `PostStatusChanged` (แบบเดียวกับ `SendPostStatusWebhook`) | สูง |
| 3 | ไฟล์เดียวใช้ร่วมหลายโพสต์ได้ไหม | ไม่ได้ในระดับข้อมูล: `medias` หนึ่งแถวมีเจ้าของได้ 1 ราย (บังคับด้วย `Media::saving`) โพสต์ที่สอง ฯลฯ ได้ไฟล์ที่ copy ใหม่ (path ใหม่) ดังนั้น "รอทุกโพสต์ที่ใช้ไฟล์เดียวกัน" แทบไม่จำเป็น แต่ต้องคิดเรื่อง "ไฟล์หนึ่งชุดต่อ post_group" ถ้าอยาก backup ครั้งเดียว | สูง |
| 4 | Google/GitHub ข้ามโค้ดเชิญได้ไหม | ตอนนี้ (`SELF_HOSTED=true`) ทั้ง `/register` และ OAuth signup ต้องมี workspace invite อยู่แล้ว (ไม่มี invite = 404) แต่เป็น "invite ของ workspace" (ผูกอีเมล, ไม่สร้าง workspace ใหม่) ไม่ใช่ "โค้ดเชิญระบบ" ต้องเขียน gate ใหม่ที่ครอบทั้ง 3 ทางสมัคร ปิด Google/GitHub ได้ด้วยการไม่ตั้ง `GOOGLE_AUTH_ENABLED` / `GITHUB_AUTH_ENABLED` (ค่าเริ่มต้น false) แต่ route callback ยังอยู่ | สูง |
| 5 | self-host ปิด Stripe/telemetry เองไหม | Stripe/billing ปิดตาม `SELF_HOSTED` (ค่าเริ่มต้น `true`) PostHog ปิดเองด้วย `POSTHOG_ENABLED=false` (ไม่ผูกกับ self-host) Nightwatch config เริ่มต้น `enabled=true` ควรตั้ง `NIGHTWATCH_ENABLED=false` เอง ยังมี outbound อื่นอีก (favicon, ChatGPT ranges, ลิงก์ trypost.it, fonts) | กลาง-สูง |
| 6 | "TryPost" อยู่ตรงไหน | ไฟล์ภาษา: `TryPost` (case-sensitive) en 52, ทุกภาษา 832 ตรงกับที่ idea.md เดา (ไม่เกี่ยว case: 54 ต่อภาษา, 864 รวม) นอกจากนั้นอยู่ใน UI, อีเมล (2 component), config, env, docker, MCP, changelog | สูง |
| 7 | ใช้ Google OAuth client เดิมสำหรับ Drive backup ได้ไหม | client เดิมเทคนิคใช้ได้ แต่ flow Drive เดิมเป็น "online token ส่งให้ browser ใช้กับ Picker เท่านั้น" ไม่เก็บ ไม่มี refresh token จึงใช้ backup ฝั่ง server ไม่ได้ ต้องเพิ่ม flow `access_type=offline` ที่เก็บ refresh token (ตาราง `media_source_connections` มีคอลัมน์รองรับ) `drive.file` อนุญาตสร้างไฟล์โดยแอปเอง | สูง (โค้ด) / สูง (เอกสาร Google) |
| 8 | `TRUSTED_PROXIES` | ค่าเริ่มต้นเป็นช่วง IP ของ Cloudflare ซึ่งไม่ครอบ Caddy; ควรตั้งเป็น subnet ที่กำหนดเองของ Docker network (หรือ IP คงที่ของ Caddy) และเลิก publish พอร์ต 8000/8080 ออกสู่ host | กลาง (ขึ้นกับ topology จริงบน VPS) |

---

## 1. ที่เก็บไฟล์ media และเวลาที่ลบ

### 1.1 ที่เก็บ (โค้ดแสดงว่า)

- ไม่ได้ใช้ `spatie/laravel-medialibrary` เลย ไม่มี `config/media-library.php` และไม่มี conversions ในความหมายของ medialibrary ตารางคือ `medias` (model `App\Models\Media`, `app/Models/Media.php:37`) เก็บ `path`, `mime_type`, `size`, `type`, `collection`, เจ้าของ
- ใช้ Laravel `Storage` ของ default disk ทั้งหมด: `Storage::put` (`app/Models/Traits/HasMedia.php:100`), `Storage::copy` (`app/Actions/Media/SyncOwnedMedia.php:198`), `Storage::url` (`app/Models/Media.php:167`)
- Default disk: `FILESYSTEM_DISK`, ค่าเริ่มต้น `public` (`config/filesystems.php:18`) disk `public` = local `storage/app/public` URL = `APP_URL/storage` (`config/filesystems.php:43-50`) มี disk `s3`, `spaces`, `r2` ให้สลับ (`config/filesystems.php:52-85`, env keys `AWS_*`, `SPACES_*`, `R2_*`)
- ชื่อไฟล์: `medias/<uuid>.<ext>` (`HasMedia.php:97`, `SyncOwnedMedia.php:195`, `ChunkedCloudUploader.php:189`)
- `.env.example:84` ตั้ง `FILESYSTEM_DISK=local` แต่ `compose.prod.yaml:65` ตั้ง `FILESYSTEM_DISK: public` (ตัวหลังคือที่ production ใช้)
- Docker prod: volume ชื่อ `storage` ผูกที่ `/var/www/html/storage/app` (`compose.prod.yaml:138`, ประกาศที่ `:201`) ครอบทั้ง `public/` และ `private/` (รวม `private/chunks` ของการอัปโหลดแบบ chunk, `app/Services/Media/ChunkedAssetReceiver.php:25`) entrypoint สร้าง symlink `public/storage` ถ้ายังไม่มี (`docker/entrypoint.sh:83-86`) nginx ใน container ไม่มี location พิเศษสำหรับ `/storage` จึงให้ PHP/ไฟล์ static ตามปกติ (`docker/nginx.conf`)
- ไฟล์ต้อง "เข้าถึงจากอินเทอร์เน็ตได้ด้วย URL": Instagram/Threads ส่ง `image_url`/`video_url` (`InstagramPublisher.php:109,130`, `ThreadsPublisher.php:145,188`), Facebook ส่ง `file_url` (`FacebookPublisher.php:242,412`), TikTok ใช้ `PULL_FROM_URL` (`TikTokPublisher.php:257`) ส่วน YouTube, X, Pinterest ดาวน์โหลดจาก URL ของเราไปที่ temp แล้วอัปโหลดต่อ (`YouTubePublisher.php:126,150`, `XPublisher.php:318`, `PinterestPublisher.php:140`)
- ข้อมูลซ้ำใน JSON: `posts.media` เก็บ `id`, `path`, `url` ของไฟล์ไว้ด้วย (`app/Dto/MediaItem.php:48-59`) ถ้าลบไฟล์โดยไม่แตะ JSON นี้ URL ในประวัติโพสต์จะชี้ไฟล์ที่ไม่มีแล้ว (inference)

### 1.2 Derivatives / ไฟล์ชั่วคราวที่อยู่นอก `medias`

ไม่มี thumbnail/conversion ถาวร ที่มีคือไฟล์ที่ publisher สร้างชั่วคราว บน disk เดียวกัน:

| ไฟล์ | โฟลเดอร์ | ใครลบ |
| --- | --- | --- |
| ภาพ crop ของ Instagram | `social-crops/` (`FitsImageToCanvas.php:15,40`) | `PruneTemporaryUploads::pruneCrops` ลบที่เก่ากว่า 7 วัน (`PruneTemporaryUploads.php:21,60-74`) |
| รูปย่อของ TikTok photo | `social-tiktok-photos/<uuid>.jpg` (`TikTokPhotoDerivativeCleaner.php:52`) | `TikTokPhotoDerivativeCleaner` หลัง publish/ล้มเหลว (ยกเว้นมี `publish_id` ที่ยังรอ, `:67-74`) |
| JPEG ของ Google Business | `google-business-derivatives/<post id>.jpg` (`GoogleBusinessDerivativeCleaner.php:14,21`) | cleaner เดียวกัน เรียกจาก publisher (`GoogleBusinessPublisher.php:66`), ลบโพสต์ (`DeletePost.php:57`), ตัด channel (`DeleteChannelPosts.php:54`), purge workspace (`PurgeWorkspace.php:24`), ล้มเหลว/timeout/reconcile (`PublishToSocialPlatform.php:394,510`, `RecoverStuckPosts.php:43,97`, `ReconcileGoogleBusinessPost.php:254`) |
| ไฟล์ chunk ที่อัปโหลดค้าง | `storage/app/private/chunks` (local เสมอ) | `PruneTemporaryUploads::pruneChunks` เก่ากว่า `upload_retention_hours` (`:76-96`) |

HEIC แปลงเป็น JPEG ตอนอัปโหลด (`HeicConverter`, `config/trypost.php:179-188`) ไม่เก็บต้นฉบับ

### 1.3 เวลาที่ระบบลบไฟล์ (โค้ดแสดงว่า)

กลไกกลาง: ทุกการลบแถว media ผ่าน `DeleteOwnedMedia` (ลบแถวในทรานแซกชันของผู้เรียก แล้ว dispatch job `DeleteMediaFiles` หลัง commit, `app/Actions/Media/DeleteOwnedMedia.php:98-180`) job เรียก `DeleteOrphanedMediaFiles` ซึ่ง "ลบไฟล์เฉพาะ path ที่ไม่มีแถว `medias` อ้างอิงอีก" (`DeleteOrphanedMediaFiles.php:67-75`) job retry 5 ครั้ง backoff 10/60/300/900 วินาที (`app/Jobs/Media/DeleteMediaFiles.php:15,23-26`) `Media::delete()` เดี่ยวก็ทำแบบเดียวกัน (`Media.php:198-205`)

| เหตุการณ์ | ที่โค้ด |
| --- | --- |
| ลบโพสต์ (ผู้ใช้) | `DeletePost.php:53` (`PostStatusRules::blocksDeletion` กันโพสต์ที่ publish แล้ว ถ้า `respectStatus`, `:45`) |
| ตัดการเชื่อมต่อ channel | `SocialController` เรียก `DeleteChannelPosts::forAccount` (`SocialController.php:70`) → `DeleteOwnedMedia::forPosts` (`DeleteChannelPosts.php:34`) ลบโพสต์ทุกสถานะของ channel พร้อมไฟล์ |
| ลบ workspace / ลบ user | `PurgeWorkspace::execute` (`DeleteWorkspace.php:56`, `DeleteUser.php:85`) → `DeleteOwnedMedia::forWorkspace` (`PurgeWorkspace.php:26`) |
| ลบไอเดีย | `DeleteIdeas.php:35` |
| ลบ RSS feed / รายการที่ถูก prune | `DeleteRssFeed.php:20`, `SyncRssFeedItems.php:137` |
| ลบโพสต์ที่ publish แล้วเกินกำหนด | `posts:prune-history` รายวัน (`routes/console.php:35`) ลบโพสต์ `published` ที่ `published_at` เก่ากว่า `POST_HISTORY_RETENTION_DAYS` ค่าเริ่มต้น 730 วัน พร้อม media (`PruneExpiredPostHistory.php:20-38`, `config/trypost.php:252`) |
| upload ชั่วคราวที่ไม่มีเจ้าของ | `media:prune-uploads` รายชั่วโมง (`routes/console.php:36`) ลบ `collection='uploads'` ที่ไม่มี post/idea/feed เกิน `MEDIA_UPLOAD_RETENTION_HOURS` ค่าเริ่มต้น 24 (`PruneTemporaryUploads.php:40-58`, `config/trypost.php:179`) |
| ผู้ใช้เอา media ออกจากโพสต์ตอนแก้ไข | `SyncOwnedMedia.php:127` ปล่อยแถวที่ไม่อยู่ใน payload |
| save ล้มเหลวกลางทาง | `MediaCopyBatch::discardCopies` ลบไฟล์ที่ copy ไปแล้ว (`MediaCopyBatch.php:42,97-104`) |
| โพสต์ที่นำเข้าจากเครือข่ายแล้วถูกแทนที่ | `UpsertAnalyticsPublication.php:235` |
| repurpose | `ProcessRepurposeItem.php:141,203` |

ที่ไม่มี: ไม่มีโค้ดที่ลบไฟล์หลัง publish สำเร็จ (ยืนยันจากรายการ caller ของ `DeleteOwnedMedia` ข้างต้น ไม่มีตัวไหนอยู่ใน flow publish) ไฟล์ของโพสต์ที่ publish แล้วอยู่ต่อจนครบ 730 วัน หรือจนถูกลบ

เครื่องมือตรวจ: `AuditMedia` ตรวจแถวที่ไฟล์หาย และไฟล์กำพร้าใน `medias/` (`AuditMedia.php:25,48,85`)

### Implications for The RX Desk

- ฟีเจอร์ "ลบวิดีโอหลังโพสต์" ต้องเรียก `DeleteOwnedMedia::forRows(...)` (หรือ `Media::delete()`) แล้วจัดการ `posts.media` JSON เอง ไม่มีตัวเดิมให้ใช้
- ถ้าลบเฉพาะไฟล์ ต้องแก้ JSON ใน `posts.media` ด้วย (หรือยอมรับ URL ตาย) และดู `ContentTypeCompatibleWithMedia.php:425` ที่อ่านไฟล์ด้วย `Storage::get` ตอน save และ `PublishToSocialPlatform::failForInvalidMedia` (`:263`) ที่ตรวจ media ตอน publish/retry (ข้ามถ้ามี Instagram workflow หรือ TikTok publish id, `:271`) เพราะโพสต์ที่ retry แล้วจำเป็นต้องมีไฟล์
- การนับโควตา: `medias.size` มีอยู่แล้ว (`Media.php` fillable) แต่ไม่มี thumbnail/ไฟล์แปลงถาวรให้ต้องนับ ไฟล์ชั่วคราว (`social-crops`, `social-tiktok-photos`, `google-business-derivatives`, `private/chunks`) อยู่นอกตาราง `medias` จึงไม่ถูกนับ ถ้านับจาก `medias.size` ตัวกันดิสก์เต็มของ VPS ต้องวัดจาก filesystem แยก
- ถ้าใช้ disk `public` ไฟล์เข้าถึงได้โดยทุกคนที่รู้ URL (UUID เดา ยาก) และเครือข่ายต้องดึงได้ ห้ามเปลี่ยนเป็น disk private โดยไม่ทำ signed URL
- retention 730 วันลบโพสต์ที่ publish แล้ว ซึ่งขัดกับข้อตัดสินใจ "เก็บประวัติโพสต์" ของ idea.md ต้องตั้ง `POST_HISTORY_RETENTION_DAYS` ให้ยาวมาก หรือกำหนดเอง (config ต้องเป็นอย่างน้อย 1 ตาม `config/trypost.php:247`)

---

## 2. ระบบรู้ว่าโพสต์สำเร็จได้อย่างไร และจุด hook

### 2.1 เส้นทาง (โค้ดแสดงว่า)

1. `PublishPost` (job) ตั้ง `Publishing` แล้ว dispatch `PublishToSocialPlatform` (`app/Jobs/PublishPost.php:21-31`)
2. `PublishToSocialPlatform::handle()` เรียก publisher ของเครือข่าย แล้ว `recordPublishResult()` (`:190-217`) ซึ่งเขียน `posts.publish_status`:
   - สำเร็จ → `markPublicationPublished()` (`Post.php:579`)
   - Google Business ที่ยังรีวิว → `markPublicationPendingReview()` (`Post.php:600`, ใช้เฉพาะ Google Business, `PublishToSocialPlatform.php:211-213`)
   - ล้มเหลว → `markPublicationFailed()` (`Post.php:659`)
   - limit/ไม่พร้อม → `Retrying` (`Post.php:555`, `PublishToSocialPlatform.php:339-345`)
3. จบ handle เรียก `updatePostStatus()` → `FinalizePostPublication::handle()` (`PublishToSocialPlatform.php:179,583-585`)
4. `FinalizePostPublication` (`app/Actions/Post/FinalizePostPublication.php:26-83`) ล็อกแถว, ถ้า `publish_status->isFinished()` (published/failed/rejected/deleted, `PublishStatus.php:21-27`) และ `wentOut()` (published หรือ deleted, `:30-36`) จะเรียก `markAsPublished()` (`Post.php:438-444`: `status=published`, `published_at`) ไม่งั้น `markAsFailed()` แล้ว `ScheduleNextOccurrence` (โพสต์ซ้ำ) หลัง commit ส่งอีเมล `PostPublished`/`PostPublishFailed` ถึงสมาชิกทุกคน (`:89-97`) และ PostHog ถ้าเปิด (`:77-82`)
5. `PostObserver::saved` เห็น `status` เปลี่ยน → `DB::afterCommit(PostStatusChanged::dispatch($post, $previousStatus))` (`PostObserver.php:37-44`) listener `SendPostStatusWebhook` ส่ง webhook `post.published`/`post.failed` (`app/Listeners/Webhook/SendPostStatusWebhook.php`, `EventType.php:15,18`) ตัว listener ถูกค้นเจออัตโนมัติ (ไม่มี `Event::listen` สำหรับมัน ใน `AppServiceProvider`)
6. Broadcast ไป frontend: `PostStatusUpdated` (`PublishToSocialPlatform.php:123,180,570`; `PostObserver.php:73-75`)
7. `PostObserver::updated` ทำงานเมื่อ `publish_status` เป็น `Published`: ซิงก์ analytics + PostHog (`PostObserver.php:50-67`)

`FinalizePostPublication` ถูกเรียกจาก 4 ที่: `PublishToSocialPlatform` (`:584`), `PublishPost` (`:27,42`), `RecoverStuckPosts` (`:64`), `ReconcileGoogleBusinessPost` (`:255`) ทุกทางจบที่ `markAsPublished()` เดียว

### 2.2 ผลของ retry / checkpoint / async

- **Retrying**: `publish_status=Retrying` ยังไม่ finished → ไม่ finalize จนกว่า job ถัดไปจะจบ (`RecoverStuckPosts.php:58-62`) ไฟล์ต้องคงอยู่ (publisher ดึงจาก URL อีกครั้ง)
- **posts:retry** (`RetryFailedPost.php`): รับเฉพาะ `status=failed` + `publish_status=failed` ตั้งกลับ `Pending`/`Publishing` และ `published_at=null` (`:107-120`) → ถ้าลบไฟล์ตอนล้มเหลวจะ retry ไม่ได้ idea.md ตัดสินใจเก็บไฟล์ตอนล้มเหลวอยู่แล้ว ตรงกัน
- **Thread**: root + replies ถูก publish ใน publisher เดียวกัน (`PublishesThreads`) โดยมี checkpoint ใน `posts.error_context.thread_progress` (`CLAUDE.md` หัวข้อ Thread replies; โค้ด `app/Support/Social/ThreadProgress.php`) `markPublicationPublished` ถูกเรียกหลัง `publish()` คืนค่า (inference: หลังทุก segment) Media ของ reply เป็นของโพสต์เดียวกัน (`SyncOwnedMedia.php:69-70,119-125`)
- **Instagram/Threads**: publisher รอ container `FINISHED` ก่อน `media_publish` (`InstagramPublisher.php:411-422,476-516`, `ThreadsPublisher.php:384-406`) ถ้ายังไม่เสร็จโยน `PlatformUnavailableException` → Retrying พร้อม checkpoint ดังนั้นเมื่อเป็น `Published` เครือข่ายดึงไฟล์เสร็จแล้ว
- **TikTok**: init แล้ว poll `post/publish/status/fetch` จนได้ `PUBLISH_COMPLETE` (`TikTokPublisher.php:439-480`) ยังไม่เสร็จ → Retrying (`:530-545`) ไม่ใช้ PendingReview ภายหลังมี job `ResolveTikTokVideoId` หาไอดีสาธารณะ (ไม่ต้องใช้ไฟล์ของเรา)
- **Facebook**: Reels รอสถานะอัปโหลด (`FacebookPublisher.php:343-345,451-522`) แต่ `publishVideoPost` ส่ง `file_url` ไป `{page}/videos` แล้วอ่าน id เลย (`:238-253`) ไม่มีการรอ (inference: ไม่ยืนยันว่า Meta ดึงไฟล์เสร็จเมื่อโพสต์ถูกบันทึกเป็น published เพราะไม่มีเอกสาร Meta ใน research รอบนี้) ควรหน่วงลบสักช่วงหรือทดสอบ
- **YouTube / X / Pinterest**: ดาวน์โหลดไฟล์มาที่ temp แล้วอัปโหลด ไฟล์ของเราไม่ถูกเครือข่ายดึงภายหลัง
- **PendingReview** (Google Business เท่านั้น): `status` ยังไม่เป็น published จนกว่า reconcile (`ReconcileGoogleBusinessPost.php:64,132,255`) หรือถูกตีเป็น rejected หลังเพดานรีวิว (`RecoverStuckPosts.php:79-98`)
- **โพสต์ซ้ำ (recurrence)**: `ScheduleNextOccurrence` ทำสำเนา media ให้โพสต์รอบถัดไปภายในทรานแซกชันของ Finalize (`ScheduleNextOccurrence.php:82`) → ลบต้นฉบับหลังจากนั้นปลอดภัย แต่ทุกรอบสร้างไฟล์ใหม่
- **ถูกลบบนเครือข่ายทีหลัง**: `publish_status=Deleted` ยัง `wentOut()` (`PublishStatus.php:30`) ไม่ควรมีผลกับไฟล์

### Implications for The RX Desk

- จุด hook ที่แนะนำ: listener ของ `PostStatusChanged` เมื่อ `status` เปลี่ยนเป็น `Published` (หลัง commit ของ `FinalizePostPublication`) เหมือน `SendPostStatusWebhook` ครอบทั้ง 4 ทางเข้า และเกิดครั้งเดียวต่อโพสต์ (Finalize ข้ามถ้า `isSettled()`, `FinalizePostPublication.php:36`) listener ควร dispatch queued job (ไม่ทำงานหนักใน listener) และตรวจซ้ำว่า `status=Published` ก่อนลบ
- ทางเลือกที่ไม่แนะนำ: `PostObserver::updated` (ยิงตอน `publish_status=Published` ก่อนที่ `status` จะ settle และก่อน ScheduleNextOccurrence) หรือ webhook `post.published` (เป็น integration ภายนอก)
- ห้ามลบเมื่อ `publish_status` เป็น `Retrying`/`Pending`/`PendingReview`/`Failed`; `Failed` ต้องเก็บไว้ให้ `posts:retry`
- ควรหน่วงลบเล็กน้อยสำหรับ Facebook video (ดูข้างบน) หรือทดสอบก่อน
- ตัว backup ต้องทำก่อนลบ และถ้า backup พัง ให้คงไฟล์ไว้ตามที่ idea.md ระบุ

---

## 3. ไฟล์เดียวใช้ร่วมหลายโพสต์ได้หรือไม่

### 3.1 โมเดลข้อมูล (โค้ดแสดงว่า)

- ตาราง `medias` (ไม่ใช่ pivot) มีคอลัมน์เจ้าของ `workspace_id`, `post_id`, `idea_id`, `rss_feed_item_id`, และ morph `mediable_*` (migration `2026_10_01_000001_add_owners_to_medias_table.php`; FK เป็น `restrictOnDelete`)
- กฎ: ต้องมีเจ้าของพอดี 1 ราย `Media::saving` โยน `MediaOwnershipViolation` ถ้า `ownerCount() !== 1` (`Media.php:77-79`, `ownerCount` ที่ `:126-134`)
- `posts.media` (JSON) เป็นสำเนาแสดงผลที่ `SyncOwnedMedia` เขียนทับจากแถว `medias` เท่านั้น ("The only writer of `posts.media` / `ideas.media`", `SyncOwnedMedia.php:19-24`)
- การผูกไฟล์กับโพสต์ใน `SyncOwnedMedia::execute` (`:105-109`):
  1. แถวเป็นของโพสต์นี้อยู่แล้ว → คงไว้
  2. `MediaCopyBatch::canMove()` (เป็น upload ชั่วคราว `collection='uploads'` หรือเจ้าของเดิมกำลังถูกลบใน batch เดียวกัน, `MediaCopyBatch.php:62-79`) → ย้าย (adopt) แถวเดิมมาเป็นของโพสต์นี้ (`:173-186`)
  3. อย่างอื่น → `copy()` สร้างไฟล์ใหม่ `medias/<uuid>.<ext>` และแถวใหม่ `group_id` ใหม่ `meta.copied_from=<id ต้นทาง>` (`:192-222`)
- กรณีใช้งาน:
  - โพสต์เดียวไปหลาย channel (`post_group_id` เดียวกัน, `CreatePosts.php:55`): การอัปโหลดครั้งเดียวถูก "ย้าย" ให้โพสต์แรก โพสต์ที่เหลือได้ "สำเนา" (คอมเมนต์ใน `spentTokenSource` เรียกว่า "one upload shared by several posts", `SyncOwnedMedia.php:153-162`) ผลคือไฟล์บน disk 1 ชุดต่อโพสต์ (inference จาก 3 ข้อด้านบน; ไม่ได้รันทดสอบ)
  - Duplicate โพสต์: `DuplicatePost.php:23` ใช้ `MediaCopyBatch` + `SyncOwnedMedia` → สำเนา
  - ไอเดีย: `ideas.media` มีแถว `idea_id` ของตัวเอง; ไอเดียซ้ำ/ไอเดีย→โพสต์ = สำเนา (`DuplicateIdea.php:35`)
  - Repurpose: อัปโหลดโฮสต์หนึ่งครั้งแล้วให้แต่ละโพสต์ (ย้าย/สำเนา) จากนั้นลบ upload ต้นทาง (`ProcessRepurposeItem.php:123-141`)
  - Templates: `PostTemplate` ไม่มี media (ค้นไม่พบ `media` ใน model/migration ของ `post_templates`)
  - Thread replies: media ของ reply เป็นแถวของโพสต์เดียวกัน (`post_id` เดียวกัน) (`SyncOwnedMedia.php:69-70`)
  - โพสต์ซ้ำตามรอบ: สำเนาต่อรอบ (`ScheduleNextOccurrence.php:82`)
- ร่องรอยของการแชร์ path แบบเก่า: `DeleteOrphanedMediaFiles` ตรวจว่ามีแถว `medias` อื่นอ้าง `path` เดียวกันหรือไม่ก่อนลบไฟล์ (`DeleteOrphanedMediaFiles.php:67-75`), มี index บน `path` (migration `2026_10_01_094245_add_path_index_to_medias_table.php`) ส่วน `AdoptWorkspaceLibrary` ทำสำเนาจากคลังเก่า (`COPY_PREFIX = 'medias/adopted-'`, `:53`) ดังนั้นข้อมูลเก่าอาจมีหลายแถวชี้ path เดียว แต่โค้ดใหม่ไม่สร้างแบบนั้น

### 3.2 "ทุกโพสต์ที่ใช้ไฟล์นี้จบแล้ว" แปลว่าอะไร

- สำหรับโค้ดปัจจุบัน: หนึ่งแถว `medias` = หนึ่งโพสต์ ดังนั้นเงื่อนไขลบต่อไฟล์คือ "โพสต์เจ้าของ `status=published`" (รวม media ของ thread replies ที่อยู่ใน `posts.meta.thread_replies` แต่เป็นแถวของโพสต์เดียวกัน)
- ถ้าต้องการ "backup วิดีโอต้นฉบับหนึ่งครั้งต่อการโพสต์หนึ่งครั้ง" ตอนโพสต์ไปหลาย channel จะมีหลายสำเนา ต้องตัดสินใจว่าจะ backup ทุกสำเนา (เปลืองพื้นที่ Drive) หรือจัดกลุ่มด้วย `posts.post_group_id` + `meta.copied_from`/ไฟล์เนื้อหาเหมือนกัน สำเนาที่ `copied_from` ชี้ได้ (inference) เกณฑ์ปลอดภัย: ลบสำเนาของโพสต์ใดเมื่อโพสต์นั้นจบ ส่วน backup ทำครั้งเดียวต่อ group เมื่อโพสต์แรกที่ publish สำเร็จ หรือรอทั้ง group จบก็ได้ (policy ต้องคุยตอน grill)
- ข้อควรระวัง: โพสต์ใน group หนึ่งอาจ `Failed` ขณะอีกโพสต์ `Published` (แต่ละ channel ล้มเหลวแยกกัน เพราะ "One destination per post" ใน CLAUDE.md) ดังนั้นการลบต่อโพสต์ถูกต้องกว่าลบต่อ group

---

## 4. สมัครด้วย Google/GitHub, การปิด OAuth login และเส้นทางสร้างบัญชีอื่น

### 4.1 สถานะปัจจุบัน (โค้ดแสดงว่า)

- `SELF_HOSTED` ค่าเริ่มต้น **`true`** (`config/trypost.php:25`, `compose.prod.yaml:23`)
- `/register` อยู่หลัง middleware `registration.enabled` (`routes/auth.php:21-24`, alias ที่ `bootstrap/app.php:38`) ซึ่งตอน self-hosted จะ **404 ถ้าไม่มี invite id ที่ถูกต้อง** (query `invite`, input `invite` หรือ session `pending_invite_id`) (`EnsureRegistrationEnabled.php:14-31`) `Invite` คือ invite เข้า workspace (`Invite::fromId`, ผูก `email` ด้วย `isFor`, `Invite.php:53-77`)
- `RegisterRequest` ตรวจอีเมลตรงกับ invite ถ้ามี invite (`RegisterRequest.php:56-67`) แต่ไม่ได้บังคับให้ต้องมี invite (ชั้นบังคับคือ middleware)
- Google: `GET /auth/google/redirect` (guest) `abort_unless(SocialAuthProvider::Google->isEnabled(), 404)` (`GoogleController.php:27`) เก็บ `invite` จาก query ใส่ session (`PreservesInvite.php:18-21`) `callback` เป็น route สาธารณะ (ไม่มี guard เปิด/ปิด) (`routes/auth.php:40-41`) ถ้าผู้ใช้ไม่มีอยู่และยังไม่ login → `registerNewUser` เรียก `resolveInviteForRegistration()` ซึ่งโยน 404 ถ้า self-hosted และไม่มี invite (`PreservesInvite.php:32-41`, `GoogleController.php:103`)
- GitHub: โครงสร้างเดียวกัน (`GitHubController.php:107-111`)
- ผู้ใช้เดิมล็อกอินด้วย OAuth ได้ โดยจับคู่ `google_id` **หรืออีเมล** (`GoogleController.php:49-51`) แล้วผูก `google_id` และ mark email verified (`:79-87`) (inference: ใครคุมอีเมลที่ Google ยืนยันแล้ว จะเข้าบัญชีที่มีอีเมลนั้นได้ ซึ่งเป็นพฤติกรรม upstream)
- เปิด/ปิดปุ่ม: `trypost.google_auth_enabled` / `github_auth_enabled` จาก `GOOGLE_AUTH_ENABLED` / `GITHUB_AUTH_ENABLED` ค่าเริ่มต้น **false** (`config/trypost.php:485,487`; `SocialAuthProvider::isEnabled`, `SocialAuthProvider.php:20-23`) แชร์ไปหน้าบ้านเป็น `googleAuthEnabled` / `githubAuthEnabled` (`HandleInertiaRequests.php:113-114`) ใช้ใน `components/auth/SocialLogin.vue`, `pages/auth/Register.vue`, `ProfileLoginSettings.vue` ปุ่ม "เชื่อมบัญชีใน settings" ก็ 404 เมื่อปิด (`ConnectedAccountController.php:19`)
- `.env.example:187,201` ตั้ง `GOOGLE_AUTH_ENABLED=false`, `GITHUB_AUTH_ENABLED=false`; `compose.prod.yaml` ไม่ตั้ง จึงเป็น false
- Client ที่ใช้: Google login ใช้ `GOOGLE_CLIENT_ID/SECRET` เดียวกับ YouTube แต่ redirect ต่างกัน (`config/services.php:65-76`) GitHub ใช้ `GITHUB_CLIENT_ID/SECRET`, redirect `GITHUB_AUTH_CALLBACK` (`:79-83`)

### 4.2 เส้นทางสร้างบัญชีทั้งหมด

`App\Actions\User\CreateUser::execute` เป็นที่เดียวที่เรียก `User::create` (`CreateUser.php:44`; `grep` ไม่พบที่อื่นใน `app/`) และมี caller 3 ตัว: `RegisteredUserController::store` (`:39`), `GoogleController::registerNewUser` (`:112`), `GitHubController::registerNewUser` (`:120`) ผลของ `is_invite`: ถ้าเป็น invite จะ **ไม่สร้าง workspace** และ `email_verified_at=now()` ถ้าไม่ใช่จะสร้าง `"<name>'s Workspace"` (`CreateUser.php:40-41,57-59`)

ทางอื่น:
- Invite ไป workspace: ผู้รับอีเมลที่ยังไม่มีบัญชีสมัครผ่าน `/register?invite=...` (ผ่าน gate ด้านบน) หรือ OAuth พร้อม invite; ผู้ที่มีบัญชีแล้ว `POST /invites/{invite}/accept` (`routes/auth.php:56-57`)
- API / MCP: ไม่สร้างผู้ใช้ MCP OAuth dynamic client registration (`POST /oauth/register`, `routes/ai.php:12` + limiter `mcp-oauth` ที่ `AppServiceProvider.php:192`) สร้างแถว `oauth_clients` เท่านั้น (ตามคำอธิบายใน CLAUDE.md "MCP OAuth rate limit"; โค้ด `laravel/mcp` ไม่มีในเครื่องจึงไม่ได้อ่านซอร์ส) การอนุมัติต้อง login เป็นผู้ใช้ที่มีอยู่แล้ว
- Seeders/factories ไม่ใช่เส้นทางของผู้ใช้จริง

### 4.3 ข้อสรุปของคำถาม

- "ข้ามช่องโค้ดเชิญได้ไหม": ถ้าเพิ่มช่องโค้ดเชิญใหม่เฉพาะที่ `RegisterRequest`/`register` แล้วปล่อย OAuth ไว้ การสมัครผ่าน Google/GitHub **จะข้ามช่องใหม่ได้** (เพราะ gate ของ OAuth ตรวจแค่ workspace invite) เว้นแต่ปิดด้วย `GOOGLE_AUTH_ENABLED`/`GITHUB_AUTH_ENABLED` เป็น false
- "ปิดด้วยการไม่ใส่ config พอไหม": ปุ่มหาย + route `redirect` 404 เมื่อปิด flag (ค่าเริ่มต้นคือปิดอยู่แล้ว) แต่ route `callback` ยังลงทะเบียนอยู่ ถ้า `redirect` ไม่เคยถูกเรียก callback จะไม่มี state/code ที่ใช้ได้และ `Socialite::...->user()` โยน exception → redirect ไป `login` (`GoogleController.php:38-42`) (inference จากโครงสร้าง; พฤติกรรมภายในของ Socialite 5.27 ไม่ได้อ่านซอร์ส) การปล่อย `GOOGLE_CLIENT_ID` ไว้ (ใช้กับ YouTube) ไม่ทำให้ login เปิด เพราะ flag แยกกัน
- สังเกต: เพราะ `SELF_HOSTED=true` ระบบนี้ "ต้องมี invite" อยู่แล้ว ฟีเจอร์ "สมัครด้วยโค้ดเชิญ" ของ idea.md ต้องปรับ 3 จุดให้สอดคล้อง: `EnsureRegistrationEnabled` (ปัจจุบัน 404 ถ้าไม่มี workspace invite), `RegisterRequest` (เพิ่มกฎโค้ด), และตัว consume โค้ดใน transaction เดียวกับ `CreateUser` (`CreateUser.php:29` เปิด transaction อยู่แล้ว) การสมัครด้วยโค้ดระบบจะเป็น `is_invite=false` → ได้ workspace ของตัวเอง (ตรงกับ "แต่ละคนมี workspace ของตัวเอง")

### Implications for The RX Desk

- ปิด Google/GitHub login ด้วยการไม่ตั้ง `GOOGLE_AUTH_ENABLED`/`GITHUB_AUTH_ENABLED` (หรือตั้ง false ชัด ๆ ใน compose) และเขียน test ว่า `/register` + callback ทุกทางไม่สร้างผู้ใช้โดยไม่มีโค้ด
- ถ้าอนาคตจะเปิด OAuth ต้องผ่าน gate โค้ดเดียวกัน (วางไว้ใน `CreateUser` หรือ action ที่ครอบ) ไม่ใช่เฉพาะ form
- ส่วนต่างจาก upstream ที่ควรแยกไฟล์: middleware/request ใหม่ + migration ตาราง invite code

---

## 5. Self-host ปิด Stripe / PostHog / telemetry ให้เองหรือไม่

### 5.1 ตรวจจับ self-host

`config('trypost.self_hosted')` ← `SELF_HOSTED` ค่าเริ่มต้น `true` (`config/trypost.php:25`) ไม่มี flag อื่น แชร์ให้ frontend เป็น `selfHosted` (`HandleInertiaRequests.php:49,81`)

### 5.2 สิ่งที่ `SELF_HOSTED=true` ปิด/เปลี่ยน (โค้ดแสดงว่า)

- สิทธิ์ใช้แอป: `Account::hasAppAccess()` = true เสมอ (`Account.php:218-224`), `hasActiveSubscription()` = true (`:172-176`), `workspaceLimit()` = null (ไม่จำกัด, `:227-232`), `canCreateWorkspace()` = true (`:240-245`), `isPastDue()` = false (`:255-259`), policy ก็ข้าม (`AccountPolicy.php:26`)
- ข้ามขั้นตอน Welcome checkout: `EnsureAccountReady` ข้ามทั้งหมด (`EnsureAccountReady.php:24`)
- หน้า billing: `index`, `processing` ฯลฯ redirect ไป calendar (`BillingController.php:32,47,74,91,108`)
- ไม่แชร์ `plans`, `usage`, `features`, `referralSources` (`HandleInertiaRequests.php:70-74,99,178-181`)
- ลบ workspace สุดท้ายได้ (`DeleteWorkspace.php:44`), API ไม่ตรวจ app access (`LoadWorkspaceFromToken.php:66`)
- การสมัครต้องมี invite (หัวข้อ 4)

### 5.3 สิ่งที่ **ไม่** ผูกกับ self-host (ต้องตั้งเอง)

| สิ่ง | สถานะเริ่มต้น | ข้อสังเกต |
| --- | --- | --- |
| Stripe / Cashier | ไม่มีคีย์ → ไม่ใช้งาน | route webhook ของ Cashier (`path` = `stripe`, `config/cashier.php`) ยังถูกลงทะเบียนโดยแพ็กเกจ และถูกยกเว้น CSRF (`bootstrap/app.php:43`) ตามซอร์ส Cashier v16.5.3 `WebhookController::__construct` ใส่ `VerifyWebhookSignature` **เฉพาะเมื่อ `cashier.webhook.secret` ตั้งไว้** (https://raw.githubusercontent.com/laravel/cashier-stripe/v16.5.3/src/Http/Controllers/WebhookController.php) → เมื่อไม่มี `STRIPE_WEBHOOK_SECRET` endpoint รับ event ที่ไม่ลงนามได้ ควรบล็อก path `/stripe/*` ที่ Caddy หรือตั้ง secret ปลอมที่เดายาก (inference; ผลกระทบจริงขึ้นกับ `StripeEventListener` ซึ่งไม่ได้อ่านละเอียด) |
| PostHog (backend) | ปิด: `POSTHOG_ENABLED` เริ่มต้น false และต้องมี `POSTHOG_API_KEY` (`config/services.php:133-137`, `PostHogService::isEnabled`, `PostHogService.php:15-19`) | ไม่ผูกกับ `SELF_HOSTED` (`.env.example:20` และ `:368`) `shouldTrack()` จะ true ใน `local` เพื่อ log เท่านั้น (`:27-30`) |
| PostHog (frontend) | ปิด: init เฉพาะ `VITE_POSTHOG_ENABLED === 'true'` และมี key (`resources/js/posthog.ts:6-12`) | ค่านี้ถูก bake ตอน build (`docker/Dockerfile:112-114,126-128`, ค่าเริ่มต้น false) |
| Nightwatch | `config/nightwatch.php:6` `enabled => env('NIGHTWATCH_ENABLED', true)` ค่าเริ่มต้น **true** และ `compose.prod.yaml` ไม่ตั้ง; ต้องมี `NIGHTWATCH_TOKEN` ถึงจะส่งข้อมูล (`:7`) | พฤติกรรมของแพ็กเกจเมื่อไม่มี token ไม่ได้ตรวจ (ไม่มี vendor) ควรตั้ง `NIGHTWATCH_ENABLED=false` ให้ชัด (`AppServiceProvider.php:111-115` ก็เช็ก flag นี้) `.env.example:291` ตั้ง false |
| Sentry/Bugsnag/Flare | ไม่พบใน `composer.json`/config | — |
| GTM | `services.gtm.id` (`config/services.php:130`) | ว่างจะไม่ฝัง (ไม่ได้ตรวจ blade) |
| Changelog "What's New" | เปิดเป็นค่าเริ่มต้น (`CHANGELOG_ENABLED`, `config/trypost.php:38`) | อ่านจากไฟล์ใน repo (`php artisan changelog:sync` ตอน boot, `docker/entrypoint.sh:80-81`) ไม่เรียกเครือข่าย แต่เนื้อหาเป็นข่าว TryPost; ปิดได้ด้วย `CHANGELOG_ENABLED=false` (CLAUDE.md: ปิดแล้วไม่มี card/menu/route) |
| ดึง IP range ChatGPT | เรียก `https://openai.com/chatgpt-connectors.json` เฉพาะตอนมีคำขอ MCP ที่ต้องเช็ก (cache 1 ชม., timeout 3 วินาที) (`ListChatGptEgressRanges.php:12-60`, `config/trypost.php:103`) | ถ้าไม่ใช้ MCP ก็ไม่เรียก; ไม่มี flag ปิด |
| Favicon proxy | เรียก `icons.duckduckgo.com` ฝั่ง server เมื่อมี request (`ResolveFavicon.php:68`, `config/trypost.php:344-346`) | override ด้วย `FAVICON_API` |
| Google Fonts | `app.blade.php:49-51` โหลดจาก fonts.googleapis.com (browser ของผู้ใช้) | — |
| ลิงก์/badge ไป trypost.it | `WorkspaceMenuContent.vue:163-181,494-501` (docs, discord, GitHub, `status.trypost.it/badge` เป็น `<img>` ที่โหลดจากเบราว์เซอร์ผู้ใช้) | ควรถอดออกใน fork (เป็นการแจ้ง IP ผู้ใช้ให้บริการภายนอก) |
| User-Agent | `TryPost.it/1.0 (+https://trypost.it)` ใน webhook ออก (`config/trypost.php:311`, `WebhookService.php:67`) และ `TryPostBot/1.0` (`SafeHttpFetcher.php:25`) | `TRYPOST_USER_AGENT` เปลี่ยนได้; ค่าคงที่ใน SafeHttpFetcher ไม่ได้ผูก env |
| ตัวเลือก AI/อื่น ๆ | `aiEnabled` ตามคีย์ (`HandleInertiaRequests.php:~120`) | ไม่ใส่คีย์ = ไม่เรียก |

ไม่พบโค้ด "update check" หรือ phone-home ไปยัง trypost.it ฝั่ง server (grep `trypost.it` ใน `app/` พบเฉพาะ user-agent) (ค้นด้วย grep ไม่ใช่การพิสูจน์ว่าไม่มี)

### Implications for The RX Desk

- compose ควรตั้งชัด: `SELF_HOSTED=true`, `POSTHOG_ENABLED=false`, `NIGHTWATCH_ENABLED=false`, `CHANGELOG_ENABLED=false` (ถ้าไม่ต้องการข่าว upstream), `GOOGLE_AUTH_ENABLED=false`, `GITHUB_AUTH_ENABLED=false` และไม่ตั้ง `STRIPE_*`
- Caddy: บล็อก `/stripe/*`
- ตรวจ `public/` และ `WorkspaceMenuContent.vue` ตอนทำแบรนด์ (หัวข้อ 6)
- docker image: ปัจจุบันดึง `ghcr.io/trypostit/trypost:latest` (`compose.prod.yaml:13`) ซึ่ง bake ค่า `VITE_*` (Reverb host = localhost) มาให้ ต้อง build image เองด้วย `--build-arg VITE_APP_NAME`, `VITE_REVERB_HOST` ฯลฯ (`Dockerfile:107-114`, `compose.prod.yaml:42-45`) ซึ่งสอดคล้องกับการเปลี่ยนแบรนด์อยู่แล้ว

---

## 6. คำว่า "TryPost" อยู่ที่ไหน

### 6.1 ไฟล์ภาษา (`lang/`)

นับด้วย `grep -o` (จำนวนครั้งที่ปรากฏ ไม่ใช่จำนวนบรรทัด)

| เงื่อนไข | en | ต่อภาษา (16 ภาษา เท่ากันหมด) | รวมทุกภาษา |
| --- | --- | --- | --- |
| `TryPost` (case-sensitive) | **52** | 52 | **832** |
| ไม่สนตัวพิมพ์ (`trypost`) | 54 | 54 | 864 |

ตรงกับที่ idea.md เดา (52 / 832) ส่วนต่าง 2 ต่อภาษาคือรูป `trypost` ตัวเล็กใน **ชื่อ key**: `by_trypost` (`lang/en/create.php:105`) และ `published_via_trypost` (`lang/en/repurposes.php:136`) (ตัว value ยังเป็น "TryPost") ดังนั้น key เหล่านี้ไม่ใช่ข้อความที่ผู้ใช้เห็น

การกระจายใน `lang/en` (จำนวนบรรทัดที่มีคำนี้ ไม่สนตัวพิมพ์; 12 ไฟล์ ซ้ำกันทุกภาษา): mcp 10, accounts 6, channels 6, repurposes 6, auth 5, mail 4, posts 4, settings 4, billing 2, integrations 2, analytics 1, create 1

ตามกฎ CLAUDE.md ของ fork (ข้อความใหม่เขียนใน `lang/<locale>/rxdesk.php` อังกฤษอย่างเดียว) การเปลี่ยนแบรนด์จะต้องแก้ไฟล์ upstream (832 จุด) หรือหาวิธี override (ดู "Still open")

### 6.2 จัดกลุ่มตามชนิด

**ผู้ใช้เห็น (user-visible)**

| ชนิด | ที่อยู่ |
| --- | --- |
| ข้อความ UI ที่แปล | `lang/*/*.php` ดู 6.1 |
| โลโก้ในแอป | `resources/js/components/AppLogo.vue` (SVG inline, `aria-label="TryPost"` `:15`, wordmark `trypost.it` `:32`); `lib/shareCard/frame.ts:109` วาดมาร์กเดียวกันลงการ์ดแชร์ (+ ลายเซ็น "Made with TryPost" ผ่านแปล, `types.ts:97`) |
| Favicon / ไอคอน | `public/favicon.ico`, `favicon.svg` (มี id `trypost-mark`), `apple-touch-icon.png` (`app.blade.php:44-46`) |
| รูปแบรนด์ | `public/images/trypost/{icon,logo-dark,logo-light}.png` (`icon.png` ใช้เป็นไอคอนของ MCP server `TryPostServer.php:103`); `public/images/emails/logo-header.png`; ไอคอนโซเชียลใน `public/images/emails/social` |
| ชื่อหน้า | `<title>` = `config('app.name','TryPost.it')` (`app.blade.php:42`); ฝั่ง Inertia ใช้ `VITE_APP_NAME` ค่าสำรอง `'TryPost.it'` (`resources/js/app.ts:18`) |
| `APP_NAME` / `MAIL_FROM_NAME` | `config/app.php:18` (`TryPost.it`), `config/mail.php:116-117` (`no-reply@mail.trypost.it`, `TryPost`), `.env.example:1,99,374`, `compose.prod.yaml:18` (`APP_NAME: TryPost`, `MAIL_FROM_NAME: TryPost`), `docker/Dockerfile:107` (`VITE_APP_NAME=TryPost`) |
| URL ค่าเริ่มต้น | `APP_URL` ค่าสำรอง `https://app.trypost.it` (`config/app.php:57,70`); ลิงก์กฎหมาย `trypost.it/terms|privacy` (`config/trypost.php:70-71`) |
| เมนู/ลิงก์ภายนอก | `WorkspaceMenuContent.vue:163-181,494-501` (docs, discord, GitHub org, status badge); `lib/docs.ts:3` (`DOCS_URL`); `lib/reviews.ts:1,8` (G2 reviews); `lib/mcpClients.ts:20`; `TopPosts.vue:175-177` (ป้าย "TryPost" ของโพสต์ที่ publish จากระบบ) |
| อีเมล | เทมเพลต Maizzle `maizzle/components/header.html` (โลโก้ + alt `TryPost` + ลิงก์ `trypost.it`) และ `maizzle/components/footer.html` (ลิงก์ `trypost.it`, GitHub, X, YouTube, Discord, Instagram ของ trypostit, `© TryPost.it`, `:brand` → `TryPost`) ทั้งสองไฟล์ถูก inline เข้า 14 view ที่ compile แล้ว `resources/views/mail/*.blade.php` (9 จุดต่อไฟล์) + `resources/views/vendor/mail/html/header.blade.php:5` ข้อความในตัวอีเมลมาจาก `lang/*/mail.php` (4 จุด/ภาษา) ห้ามแก้ไฟล์ blade ตรง ๆ (CLAUDE.md: เป็น build output ต้อง `cd maizzle && npm run build`) |
| MCP | ชื่อเซิร์ฟเวอร์ `#[Name('TryPost')]` และ Instructions ที่ AI เห็น (`app/Mcp/Servers/TryPostServer.php:101-105`) แสดงในแอป AI ของผู้ใช้ |
| Changelog "What's New" | `resources/changelog/*/entry.md` (เช่น `2026-10-14-trypost-2-0`, title "TryPost 2.0 is here") และปกใน `resources/changelog/**/cover.png` (เทมเพลตใน `.claude/release-assets/changelog-covers/`) ภาษาอังกฤษล้วน ไม่ผ่าน lang |
| Docs/README | `README.md`, `LICENSE.md`, `releases/*` (รายละเอียดการวิเคราะห์ไม่ได้ลงลึก) |
| ไม่พบ | PWA manifest (ไม่มี `manifest*` ใน `public/` หรือ `<link rel=manifest>` ใน `app.blade.php`), ภาพ OG/Twitter card (ไม่มี `og:` meta ใน `app.blade.php`) |

**ตัวระบุภายใน (internal)** ควรคงไว้เพื่อ merge upstream ง่าย

| ชนิด | ตัวอย่าง |
| --- | --- |
| namespace/ชื่อคลาส/ไฟล์ | `TryPostServer`, `TryPostPublicationIdentity`, `SyncTryPostPublication` ฯลฯ (22 ไฟล์ใน `resources/js`, ~48 ใน `app/Services`, ฯลฯ ตามการนับไฟล์) |
| config | ไฟล์ `config/trypost.php` และ key `trypost.*` (915 จุดใน `app`, `resources`, `routes`, `tests`) |
| env | `TRYPOST_TARGET`, `TRYPOST_DOCKER_BOOTSTRAP`, `TRYPOST_SKIP_BOOTSTRAP`, `TRYPOST_USER_AGENT`, `TRYPOST_ALLOW_PRIVATE_NETWORK` (`docker/entrypoint.sh`, `Dockerfile:170,210`) |
| storage ในเบราว์เซอร์ | key `trypost:<user id>` (`resources/js/lib/browserStorage.ts:8`), `trypost.media-source-popup` (`lib/mediaSources/popup.ts:15`), legacy keys (`lib/legacyBrowserStorage.ts`) |
| ค่า enum | `'trypost'` ใน `types/post.ts:33` (origin) |
| ชื่อ package/docker | `trypost-it/trypost` (`composer.json:3`), image `trypost-app:dev` และ DB/user `trypost` (`compose.yaml:10,46,55`), `compose.prod.yaml` (container `trypost`, `trypost-pgsql` ฯลฯ, DB `trypost`), ไฟล์ ini `99-trypost.ini` (`Dockerfile:164,189`), REVERB key `trypost-reverb-key` |
| ชื่อไฟล์ดาวน์โหลด | `trypost-<metric>-<date>.png` (`lib/shareCard/index.ts:109`) ผู้ใช้เห็นชื่อไฟล์ จึงกึ่ง visible |
| `.ai/`, `.claude/`, `.cursor/`, `.github/` | เอกสารสำหรับนักพัฒนา/CI |

### Implications for The RX Desk

- แกนที่ผู้ใช้เห็นและควรเปลี่ยน: `APP_NAME`/`MAIL_FROM_*`/`VITE_APP_NAME` (env/build arg ไม่ต้องแก้โค้ด), `AppLogo.vue`, favicon/รูปใน `public/`, ลิงก์ใน `WorkspaceMenuContent.vue`, `maizzle/components/{header,footer}.html` (แล้ว build ใหม่), `TryPostServer` attributes, ลิงก์กฎหมาย (ตั้ง `LEGAL_*_URL`), changelog (ปิดหรือเขียนใหม่)
- งานหนักสุดคือ `lang/*` 832 จุด: ไฟล์ภาษาเป็นของ upstream ทุกภาษา การแก้ตรง ๆ ทำให้ merge ชน ทางเลือกอยู่ในหมวด "Still open"
- ต้องเก็บ LICENSE/copyright เดิมและลิงก์ซอร์สตาม AGPL (idea.md): ระวังไม่ลบเครดิต upstream ในไฟล์ license

---

## 7. ใช้ Google OAuth client เดิมสำหรับ Drive backup ได้หรือไม่

### 7.1 Flow Google ที่มีอยู่ (โค้ดแสดงว่า)

| Flow | env / client | redirect | scopes | token |
| --- | --- | --- | --- | --- |
| Login/สมัคร (`google-auth`) | `GOOGLE_CLIENT_ID/SECRET` (`config/services.php:72-76`) | `GOOGLE_AUTH_CALLBACK` | ค่า default ของ Socialite (ไม่ระบุใน repo) | ไม่เก็บ เก็บแค่ `users.google_id` |
| YouTube (`google`) | `GOOGLE_CLIENT_ID/SECRET` เดียวกัน (`:65-69`) | `GOOGLE_CLIENT_REDIRECT` = `/accounts/youtube/callback` (`.env.example:190`) | `youtube.upload`, `youtube.readonly`, `youtube.force-ssl`, `yt-analytics.readonly` (`YouTubeController.php:24-29`) | `access_type=offline`, `prompt=select_account consent` (`:94-103`) เก็บใน `social_accounts` |
| Google Business (`google-business`) | `GOOGLE_BUSINESS_CLIENT_ID/SECRET` แยก project (`:123-127`, เหตุผลในคอมเมนต์ `:120-122`) | `GOOGLE_BUSINESS_CLIENT_REDIRECT` | `userinfo.profile`, `userinfo.email`, `business.manage` (`GoogleBusinessController.php:24-28`) | `access_type=offline` (`:41`) เก็บใน `social_accounts` |
| Composer: Drive Picker (`google_media`) | `GOOGLE_MEDIA_CLIENT_ID/SECRET`, `GOOGLE_MEDIA_API_KEY`, `GOOGLE_MEDIA_APP_ID` (`config/services.php:144-150`) เป็น client **แยก** ไม่ fall back ไป `GOOGLE_CLIENT_ID` (`.env.example:309-310`) | `GOOGLE_MEDIA_CLIENT_REDIRECT` = `/integrations/google/callback` (`routes/app.php:324-327`) | `drive.file` สำหรับ Drive, `photospicker.mediaitems.readonly` สำหรับ Photos (`GoogleMediaOAuth.php:21-24`) | **online only**: `access_type=online`, PKCE S256 (`:40-43`), ไม่ขอ refresh token, ไม่มี `include_granted_scopes` (docblock `:13-17`) |

วิธีที่ Drive token ถูกใช้: callback แลก code → เก็บ access token เข้ารหัสใน **cache** (TTL ≤ `RETURN_TTL_SECONDS` และ ≤ อายุ token) → composer `claim` ครั้งเดียวคืน token ให้ browser เพื่อเปิด Google Picker → ลบจาก cache (`GoogleMediaController.php:127-178`) ไม่มีการเขียนลง `media_source_connections` สำหรับ Google (ตารางนี้ใช้กับ Canva: `ConnectCanva.php`, `CanvaClient.php`) ตาราง `media_source_connections` มี `access_token` (encrypted), `refresh_token` (encrypted), `expires_at`, unique `(user_id, source)` (migration `2026_10_01_035621_create_media_source_connections_table.php`, `MediaSourceConnection.php:38-45`) จึงรองรับการเก็บ refresh token ได้ แต่ปัจจุบัน Drive ไม่ได้ใช้

ปุ่ม Drive ในคอมโพเซอร์แสดงเมื่อ `GOOGLE_DRIVE_ENABLED` (ค่าเริ่มต้น true) + มี client/secret/redirect + `GOOGLE_MEDIA_API_KEY` + `GOOGLE_MEDIA_APP_ID` ครบ (`Source.php:26-33`)

คำเตือนใน CLAUDE.md เรื่อง `include_granted_scopes`: "Google connects (YouTube, Google Business) never send `include_granted_scopes`" เพราะ Google ปฏิเสธการรวม `drive.file` กับ scope ของ YouTube ("scopes that cannot be requested together") โค้ดของเราไม่มีที่ส่งพารามิเตอร์นี้ (`grep include_granted` ใน `app/` ไม่พบ) (ไม่ได้ตรวจค่า default ของ Socialite provider เพราะไม่มี vendor)

### 7.2 เอกสาร Google (ภายนอก)

- https://developers.google.com/workspace/drive/api/guides/api-specific-auth : `drive.file` อยู่ใน non-sensitive scopes ที่แนะนำ ให้สิทธิ์ "Create new Drive files, or modify existing files, that you open with an app or that the user shares with an app" และเข้าถึงไฟล์ที่ผู้ใช้เปิดผ่าน Picker/ตัวเลือกไฟล์ของแอป (สิทธิ์เป็นราย ไฟล์) ดังนั้นแอปอัปโหลดไฟล์ใหม่ที่ **แอปสร้างเอง** ได้ และเข้าถึงต่อได้ แต่ไม่เห็นไฟล์อื่นใน Drive ของผู้ใช้ ซึ่งเหมาะกับ backup

### 7.3 ข้อสรุป

- ใช้ OAuth client เดิมได้ในทางเทคนิค (client เป็น Web application และ `drive.file` ไม่ต้องรีวิวเข้มเพราะ non-sensitive) แต่ **flow เดิมใช้ไม่ได้** เพราะ `access_type=online` และไม่เก็บ token ฝั่ง server ต้องเพิ่ม flow ใหม่แบบ offline ที่ขอ `access_type=offline` + `prompt=consent` และเก็บ refresh token ใน `media_source_connections` (หรือที่ใหม่)
- ควรใช้ client `google_media` (ผ่านขั้นยืนยัน `drive.file` อยู่แล้วถ้า Cloud เปิดใช้) ไม่ใช่ `GOOGLE_CLIENT_ID` ซึ่งผูกกับ scope ของ YouTube การรวม scope ใน client เดียวกันไม่ใช่ปัญหา ตราบที่ไม่ใช้ `include_granted_scopes` และไม่ขอ scope ที่ขัดกันใน request เดียวกัน (inference จากคำเตือนใน CLAUDE.md)
- Redirect URI ใหม่ต้องลงทะเบียนที่ Google Cloud ของ client นั้น
- สำหรับ client ที่ยังอยู่โหมด Testing บน Google Cloud: refresh token ของแอปในสถานะ Testing มีอายุจำกัด (ข้อนี้ไม่ได้ตรวจกับเอกสารล่าสุดในรอบนี้ ต้องยืนยัน) ผลคือ backup อาจหยุดโดยไม่มีสัญญาณ ถ้าไม่ publish consent screen ต้องออกแบบการแจ้งเตือนเมื่อ token หมดอายุ

### Implications for The RX Desk

- งานเพิ่ม: controller/action ใหม่สำหรับเชื่อม Drive แบบ offline, job อัปโหลด (resumable upload สำหรับวิดีโอใหญ่), การ refresh token ที่จัดการ `invalid_grant` และตั้งสถานะ "ต้องเชื่อมใหม่" (ตรงกับ idea.md "ถ้า backup ล้มเหลว เก็บไฟล์ไว้")
- ใช้ `MediaSourceConnection` เป็นที่เก็บได้ แต่ `source` เป็น enum `Media\Source` (`Source.php`) ต้องเพิ่ม case หรือใช้ตารางแยก เพื่อไม่ปน composer
- ตั้ง scope เป็น `drive.file` อย่างเดียว

---

## 8. `TRUSTED_PROXIES` สำหรับ Caddy

### 8.1 เส้นทางเครือข่าย (โค้ดแสดงว่า)

- Caddy: `reverse_proxy app:80` (`Caddyfile:4-5`) container `caddy` (profile `proxy`, `compose.prod.yaml:178-196`) คุยกับ container `app` ผ่านชื่อ service บน default network ของ compose project (ไม่มี `networks:` หรือ `ipam` ใน `compose.prod.yaml` → เป็น bridge เริ่มต้นที่ Docker เลือก subnet เอง)
- ใน container `app`: nginx ฟังพอร์ต 80 (`docker/nginx.conf:2-3`) ส่ง `.php` ไป php-fpm ที่ `127.0.0.1:9000` ด้วย `fastcgi_params` มาตรฐาน (`:46-50`) ไม่มี `set_real_ip_from`/`real_ip_header` (`grep real_ip` ไม่พบ) ดังนั้น `REMOTE_ADDR` ที่ PHP เห็น = IP ของ container Caddy และ header `X-Forwarded-*` ที่ Caddy ใส่ผ่านเข้า PHP ตรง ๆ
- Reverb: `/app/` และ `/apps/` ถูก proxy ภายใน container ไป `127.0.0.1:8080` (`nginx.conf:11-35`) ผ่านพอร์ต 80 เดียวกัน จึงผ่าน Caddy ได้
- `compose.prod.yaml:134-136` **publish** `8000:80` และ `8080:8080` ออกสู่ host ด้วย (แม้ใช้ Caddy)
- Laravel อ่าน `trustedproxy.proxies` จาก `TRUSTED_PROXIES` (`config/trustedproxy.php:24`) ค่าเริ่มต้นเป็นช่วง IP ของ Cloudflare ทั้งหมด (`:24-47`) `bootstrap/app.php` ไม่ได้เรียก `trustProxies()` เอง (`grep` ไม่พบ ตรงกับ CLAUDE.md) และมี `TrustedProxiesTest.php` ใน `tests/Feature`
- Caddy (ตามเอกสาร https://caddyserver.com/docs/caddyfile/directives/reverse_proxy): `reverse_proxy` ตั้ง/ต่อ `X-Forwarded-For` และตั้ง `X-Forwarded-Proto`/`Host` และ **ทิ้งค่าที่ client ส่งมา** เว้นแต่ client มาจาก `trusted_proxies` ของ Caddy ที่ระบุไว้ Caddyfile ของ repo ไม่ได้ตั้ง `trusted_proxies` จึงปลอมไม่ได้ผ่าน Caddy

### 8.2 ผลถ้าตั้งผิด

- ปล่อยค่าเริ่มต้น (Cloudflare) ขณะที่ Caddy เป็นด่านแรก: Laravel ไม่เชื่อ XFF จาก Caddy → `$request->ip()` เป็น IP ภายในของ container Caddy ทุกคำขอ ผลคือ rate limit ต่อ IP ทุกตัวรวมเป็นก้อนเดียว (limiter `mcp-oauth` `AppServiceProvider.php:192`, `signed_upload_per_ip_per_minute` `config/trypost.php:~175`) และ `users.registration_ip` (`CreateUser.php:50`) ผิด และ scheme อาจถูกมองเป็น http (inference: ถ้า proto ไม่ถูกเชื่อ URL ที่สร้างอาจเป็น http; ตรวจจริงก่อนใช้) (CLAUDE.md หัวข้อ "Trusted proxies" อธิบายผลต่อ rate limit และช่วง ChatGPT/Claude)
- ตั้ง `*`: client ปลอม XFF ได้ถ้าเข้าถึง app ได้โดยไม่ผ่าน Caddy (config comment `trustedproxy.php:14-17`)

### 8.3 คำแนะนำที่เป็นรูปธรรม

1. **กำหนด subnet ของ network เองใน compose** (ไม่พึ่ง subnet ที่ Docker สุ่ม) เช่น ประกาศ network ชื่อ `edge` ด้วย `ipam` ที่ subnet เฉพาะ (ตัวอย่างค่า placeholder: `172.30.0.0/24` ซึ่งเป็นช่วง private ของ Docker ที่ผู้ใช้เลือกเองได้ ไม่ใช่ IP จริงของเซิร์ฟเวอร์) ใส่ทั้ง `app` และ `caddy` ใน network นั้น แล้วตั้ง `TRUSTED_PROXIES` เป็น subnet นั้น หรือเข้มกว่านั้นคือให้ `caddy` มี `ipv4_address` คงที่ แล้วตั้ง `TRUSTED_PROXIES` เป็น `<ip>/32` ของ Caddy ตัวเดียว (เข้มสุด)
2. **เลิก publish `8000:80` และ `8080:8080` ออกสู่ host** (ลบ `ports` ของ `app` หรือผูกกับ `127.0.0.1`) เมื่อใช้ Caddy เหตุผล: ถ้า publish ไว้ คำขอที่เข้า `host:8000` โดยตรงจะถูก Docker NAT และ `REMOTE_ADDR` ที่ container เห็นมักเป็น gateway ของ bridge ซึ่งอยู่ใน subnet เดียวกับ Caddy; ถ้า `TRUSTED_PROXIES` เป็นทั้ง subnet ผู้ไม่หวังดีที่ยิงพอร์ตตรงจะปลอม `X-Forwarded-For` ได้ (inference จากพฤติกรรม NAT ของ Docker; ควรทดสอบบน VPS จริง) การใช้ `/32` ของ Caddy ตัวเดียวช่วยปิดช่องนี้ด้วย
3. ตั้ง firewall ของ VPS ให้เปิดเฉพาะ 80/443 สู่ภายนอก
4. ไม่ใช้ `*`
5. ตรวจหลังตั้ง: เรียก endpoint ที่แสดง IP ผ่านโดเมน ต้องเห็น IP ผู้ใช้ ไม่ใช่ IP ภายใน และเรียกผ่านพอร์ต 8000 (ถ้ายังเปิด) ต้องไม่ถูกเชื่อ XFF
6. ถ้าวันหน้าใส่ Cloudflare ไว้หน้า Caddy: Caddy ต้องตั้ง `trusted_proxies` เป็นช่วง Cloudflare (global option `servers { trusted_proxies static ... }`) และ Laravel ต้องเชื่อ Caddy (ไม่ใช่ Cloudflare โดยตรง) เหมือนข้างบน

(ไม่มี IP จริงของ VPS ในเอกสารนี้ ตัวเลขข้างบนเป็นตัวอย่าง)

---

## Still open

1. **ไฟล์ภาษา 832 จุด**: จะแก้ upstream `lang/*` ตรง ๆ (ชน merge) หรือล็อกผู้ใช้เป็นอังกฤษแล้วแก้เฉพาะ `lang/en` หรือ override แบบอื่นที่ไม่แตะไฟล์ upstream เรื่องนี้เป็นการตัดสินใจ ไม่ใช่ข้อเท็จจริงในโค้ด (อยู่ในหมวด "ต้องตัดสินใจ" ของ idea.md)
2. **Facebook video แบบไม่ใช่ Reels**: เมื่อ `markPublicationPublished` แล้ว Meta ดึง `file_url` เสร็จหรือยัง ไม่ได้ยืนยันกับเอกสาร Meta ใน research นี้
3. **Nightwatch ไม่มี token**: ไม่ได้อ่านโค้ดแพ็กเกจ (ไม่มี vendor) ว่า `enabled=true` + ไม่มี token แล้วทำอะไร ควรตั้ง false ไว้
4. **Endpoint `/stripe/*`**: ยังไม่ได้อ่าน `StripeEventListener` ว่าอันตรายแค่ไหนเมื่อรับ event ไม่ลงนาม และไม่ได้ยืนยันด้วย `route:list` ว่า path ถูกลงทะเบียนจริง
5. **Socialite 5.27**: พารามิเตอร์ default ของ provider `google`/`google-auth` (เช่น `include_granted_scopes`) ไม่ได้ตรวจ
6. **ความเป็นเจ้าของโควตา**: ไฟล์ที่สมาชิกอัปโหลดเข้า workspace คนอื่น: `medias.workspace_id` บอก workspace ได้ และ `Workspace`→`Account.owner_id` บอกเจ้าของ แต่ไม่มีคอลัมน์ผู้อัปโหลดใน `medias` (fillable ไม่มี `user_id`, `Media.php:44-62`) จึงนับ "ตามเจ้าของ workspace" ได้โดยไม่ต้องรู้ผู้อัปโหลด (ตรงกับข้อตัดสินใจ) แต่ยังไม่ได้ออกแบบ query/index (`medias.workspace_id` มี index) และต้องรวมกรณีของ avatar/โลโก้ที่ `workspace_id` เป็น null
7. **Google Cloud ในโหมด Testing**: อายุ refresh token และข้อกำหนดการ verify สำหรับ `drive.file` ที่ผู้ใช้หลายคนใช้ ต้องยืนยันกับเอกสารล่าสุดของ Google
8. **TikTok/YouTube ที่ยังไม่ผ่านการตรวจแอป** (privacy ถูกจำกัด) และ Meta tester: เป็นข้อกำหนดของแพลตฟอร์ม ไม่ได้ตรวจในรอบนี้ (idea.md ระบุให้ยืนยันกับเอกสารล่าสุด)
9. **Docker network จริงบน VPS**: subnet/พฤติกรรม NAT ของพอร์ตที่ publish ต้องทดสอบบน host จริงก่อนปักค่า `TRUSTED_PROXIES`
10. **Retention 730 วัน** ขัดกับการเก็บประวัติถาวร ต้องตัดสินใจค่า `POST_HISTORY_RETENTION_DAYS`
11. **ขอบเขต backup**: วิดีโออย่างเดียวหรือรวมรูป, backup ต่อโพสต์หรือต่อ `post_group_id` (หัวข้อ 3.2), และโฟลเดอร์บน Drive ยังรอ grill
