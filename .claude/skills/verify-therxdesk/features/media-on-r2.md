# Media on R2

Every upload, read, copy and delete goes through the app's default disk. When that disk is `r2`, media lives on
object storage and is served from the bucket's public URL (`R2_URL`). The one exception is the media editor, which
streams an image through the app (`GET /media/{id}/file`, `Storage::response`) so its canvas stays untainted.
The Disk guard keeps watching the VPS storage volume, which holds temporary files, chunks, logs and framework caches.

## Sub-features

- The composer uploads an image and a video through the chunked endpoint (`POST /media/chunked`).
- A file is split into 5 MiB requests. On the `s3`/`r2` disk a video (or PDF) completes as an S3 multipart object on
  the bucket, not as assembled local bytes; the type and disk choose that, not the file's size (a small video also
  completes as a one-part multipart object). An image is assembled in a temp file and written to the disk.
- The upload answer's `url` is on the media host, and the composer previews the stored image from that URL.
- The Insights share card draws a channel avatar that was fetched from the media host with CORS. A media host
  without CORS makes the card fall back to the network logo, so the card is the check that CORS works.
- Under the Disk guard's threshold a chunked upload is still refused before any bytes land, even with media on
  object storage.
- `rxdesk:quota` prints the Quotas handed out without comparing them with the VPS disk when media is remote.

## How to get to it (user POV)

Sign in, open a new post, and add an image and a video from the composer's media tray. Insights → Share on the
followers chart opens the share card.

## Driving it with Playwright

1. `launch.sh`, then `selling.sh up` (the journey needs a subscribed Owner), then `media.sh up`: it recreates `app`
   with `FILESYSTEM_DISK=r2` through `compose.r2.yaml`, without editing `.env`.
2. `fixture.sh up` for a fresh Owner.
3. `drive.sh welcome-checkout` once, so the fixture Owner has a subscription and reaches the Schedule.
4. `media.sh seed`: it creates an analytics-included Instagram channel on the fixture, uploads its avatar to the
   default disk, and writes two follower readings.
5. `drive.sh media-on-r2`.
6. `media.sh clear`, then `media.sh down`, then `selling.sh down`.

The journey checks:

- The share card's `data-state` is `ready` and `window.__images` holds the channel avatar with `cors=1`; its host is
  the media host.
- An image upload and a 6 MiB video upload both complete, and both answered URLs share that host.
- The video was sent in more than one chunk (two request totals of 6 MiB).
- The image `<img>` in the composer has `naturalWidth > 0` from its media URL.
- Fetching the video URL returns the same byte length, starting with the `ftypmp42` box the test wrote.

Then, from the host (not a journey check):

- `db.sh "select path, collection, type, size from medias order by created_at desc limit 5"` shows the two new
  `uploads` rows (image and video) under `medias/`.

## Gotchas

- `media.sh seed` refuses to run while the default disk is `local` or `public`, so a pass cannot come from the
  wrong disk (a stack configured with `s3` or `spaces` would pass the guard and is not a supported dev state).
  It generates a fresh random avatar path on every call and points the channel at it without deleting the previous
  object, so start from a fresh fixture rather than treating `seed` as object-idempotent.
  `media.sh up` and `media.sh down` switch the disk; the marker stacks on the selling overlay, so any later
  `selling.sh up` keeps r2 on.
- The dev bucket's public URL is its `r2.dev` address; the production bucket is served from a public custom domain
  set in `R2_URL`.
- The bucket must answer `GET` with CORS from the app's origin (`http://localhost:8000` in dev). Without it the
  avatar does not load, the card draws the network logo instead, and the first check fails.
- The uploaded temporary media is never saved to a post. The fixture purge (`fixture.sh up`) removes the rows; the
  upload retention prunes abandoned files.
