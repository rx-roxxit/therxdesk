<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Post\CreatePosts;
use App\Actions\Workspace\PurgeWorkspace;
use App\Enums\Post\PublishStatus;
use App\Enums\Post\ScheduleMode;
use App\Enums\Post\Status as PostStatus;
use App\Enums\PostPlatform\ContentType;
use App\Models\Account;
use App\Models\Post;
use App\Models\SocialAccount;
use App\Models\User;
use App\Models\Workspace;
use App\Support\PostingSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The demo desk the landing's screenshots are taken from: three made-up
 * brands, their channels and a few weeks of posts, one of them waiting for
 * approval. Run by hand on the dev database only, never in production:
 * `php artisan db:seed --class=LandingDemoSeeder`. Running it again replaces
 * the previous demo desk; `forget()` removes it. See `resources/js/assets/landing/CAPTURE.md`.
 */
class LandingDemoSeeder extends Seeder
{
    /** The demo owner signs in with this email and the factory password, `password`. */
    public const string OWNER_EMAIL = 'landing-demo@example.com';

    public const string MEMBER_EMAIL = 'landing-demo-member@example.com';

    public function run(): void
    {
        self::forget();

        $owner = User::factory()->create([
            'name' => 'Dana Reyes',
            'email' => self::OWNER_EMAIL,
            'timezone' => 'UTC',
        ]);

        $bakery = $this->workspace($owner, 'Juniper Bakery');
        $cycles = $this->workspace($owner, 'Northfield Cycles');
        $yoga = $this->workspace($owner, 'Lumen Yoga');
        $owner->update(['current_workspace_id' => $bakery->id]);

        $member = User::factory()->create([
            'name' => 'Sam Okafor',
            'email' => self::MEMBER_EMAIL,
            'account_id' => $owner->account_id,
            'timezone' => 'UTC',
            'current_workspace_id' => $bakery->id,
        ]);
        $bakery->members()->attach($member->id, ['is_admin' => false, 'requires_approval' => true]);

        $bakeryInstagram = $this->channel($bakery, 'instagram', 'juniperbakery');
        $bakeryFacebook = $this->channel($bakery, 'facebook', 'juniperbakery');
        $this->channel($bakery, 'youtube', 'juniperbakery');

        $this->bakeryPosts($owner, $bakeryInstagram, $bakeryFacebook);
        $this->approvalRequests($bakery, $member, $bakeryFacebook);

        $this->sidePosts($owner, $this->channel($cycles, 'instagram', 'northfieldcycles'), [
            'Spring tune-ups are open. Book a slot at the counter.',
            'Gravel loop this Sunday, 40 km, coffee at the halfway point.',
        ], ['#1F4E5F', '#E9C46A']);
        $this->channel($cycles, 'facebook', 'northfieldcycles');

        $this->sidePosts($owner, $this->channel($yoga, 'instagram', 'lumenyoga'), [
            'New early class: 6:30 flow, every weekday.',
            'Bring a friend to any class this week, on us.',
        ], ['#5E548E', '#F2E9E4']);
        $this->channel($yoga, 'youtube', 'lumenyoga');
    }

    /**
     * Removes the demo desk. Run it once the screenshots are taken, so the dev
     * scheduler never tries to publish the demo's posts:
     * `php artisan tinker --execute 'Database\\Seeders\\LandingDemoSeeder::forget();'`.
     */
    public static function forget(): void
    {
        $previous = User::query()->whereIn('email', [self::OWNER_EMAIL, self::MEMBER_EMAIL])->get();

        Workspace::query()
            ->whereIn('account_id', $previous->pluck('account_id'))
            ->get()
            ->each(fn (Workspace $workspace) => PurgeWorkspace::execute($workspace));

        $accountIds = $previous->pluck('account_id')->unique();
        User::query()->whereIn('id', $previous->pluck('id'))->delete();
        Account::query()->whereIn('id', $accountIds)->delete();
    }

    private function workspace(User $owner, string $name): Workspace
    {
        $workspace = Workspace::factory()->create([
            'name' => $name,
            'user_id' => $owner->id,
            'account_id' => $owner->account_id,
        ]);
        $workspace->members()->attach($owner->id, ['is_admin' => true, 'requires_approval' => false]);

        return $workspace;
    }

    private function channel(Workspace $workspace, string $network, string $username): SocialAccount
    {
        $schedule = PostingSchedule::empty();

        foreach (range(0, 6) as $day) {
            $schedule = $schedule->withTime($day, '09:00')->withTime($day, '17:30');
        }

        // Without events: a created channel queues analytics reads, which
        // would call the network with the demo's made-up token.
        return SocialAccount::withoutEvents(fn (): SocialAccount => SocialAccount::factory()->{$network}()->create([
            'workspace_id' => $workspace->id,
            'position' => $workspace->socialAccounts()->count(),
            'display_name' => $workspace->name,
            'username' => $username,
            'timezone' => 'UTC',
            'posting_schedule' => $schedule,
        ]));
    }

    /**
     * Five weeks of bakery posts either side of today, so any month's grid is
     * full: sent ones behind today, queued ones ahead.
     */
    private function bakeryPosts(User $owner, SocialAccount $instagram, SocialAccount $facebook): void
    {
        $posts = [
            [$instagram, 'Sourdough Saturday is back. First loaves come out of the oven at 8.'],
            [$facebook, 'Our rye with caraway and orange is on the shelf from tomorrow.'],
            [$instagram, 'Behind the counter: how our croissants get their 27 layers.'],
            [$facebook, 'Cinnamon buns are back for the weekend. Get there early.'],
            [$instagram, 'Meet Rosa, who lights the ovens at 4 every morning.'],
            [$facebook, 'Our winter hours start next week: 7 to 3, every day.'],
            [$instagram, 'Three new loaves this month. Which one should stay?'],
            [$facebook, 'Thank you for 500 pre-orders. The stollen is in the oven.'],
        ];

        $today = CarbonImmutable::now('UTC')->startOfDay();
        $palette = [['#F3E3C8', '#C0763B'], ['#E8D9C4', '#7A4A2A'], ['#F6EEE3', '#D9A066']];

        foreach (range(-35, 35) as $offset) {
            if ($offset % 3 === 2) {
                continue;
            }

            [$channel, $text] = $posts[($offset + 35) % count($posts)];
            $at = $today->addDays($offset)->setTimeFromTimeString($offset % 2 === 0 ? '09:00' : '17:30');

            $this->post($owner, $channel, $text, $at, $palette[($offset + 35) % count($palette)]);
        }
    }

    /**
     * @param  array{0: string, 1: string}  $colors
     */
    private function post(User $owner, SocialAccount $channel, string $text, CarbonImmutable $at, array $colors): Post
    {
        $isInstagram = $channel->platform->network() === 'instagram';
        $hasPassed = $at->isPast();

        return Post::factory()->forAccount($channel, $isInstagram ? ContentType::InstagramFeed : ContentType::FacebookPost)->create([
            'user_id' => $owner->id,
            'status' => $hasPassed ? PostStatus::Published : PostStatus::Scheduled,
            'publish_status' => $hasPassed ? PublishStatus::Published : PublishStatus::Pending,
            'schedule_mode' => $hasPassed ? ScheduleMode::Custom : ScheduleMode::Queue,
            'scheduled_at' => $at,
            'published_at' => $hasPassed ? $at : null,
            'content' => $text,
            'media' => $isInstagram ? [$this->graphic($colors)] : [],
        ]);
    }

    /**
     * What a teammate who needs approval asked to publish: one post saved on
     * an open posting time, which the queue holds for it until it is
     * approved, and one at a time of its own.
     */
    private function approvalRequests(Workspace $workspace, User $member, SocialAccount $facebook): void
    {
        // Tomorrow's 09:00 is a posting time the bakery's Facebook channel
        // leaves open: the bakery's one post that day goes out at 17:30.
        $openTime = CarbonImmutable::now('UTC')->addDay()->setTimeFromTimeString('09:00')->toIso8601String();

        CreatePosts::execute($workspace, $member, [
            'status' => 'scheduled',
            'scheduled_at' => $openTime,
            'queue_slot' => $openTime,
            'content' => 'Pre-order your holiday stollen before Friday. Pick it up warm on Saturday morning.',
            'media' => [],
            'label_ids' => [],
            'destinations' => [['social_account_id' => $facebook->id, 'content_type' => ContentType::FacebookPost->value, 'meta' => []]],
        ]);

        CreatePosts::execute($workspace, $member, [
            'status' => 'scheduled',
            'scheduled_at' => CarbonImmutable::now('UTC')->addDays(3)->setTimeFromTimeString('12:00')->toIso8601String(),
            'content' => 'The pour-over bar opens Monday. Come taste the first batch.',
            'media' => [],
            'label_ids' => [],
            'destinations' => [['social_account_id' => $facebook->id, 'content_type' => ContentType::FacebookPost->value, 'meta' => []]],
        ]);
    }

    /**
     * @param  list<string>  $texts
     * @param  array{0: string, 1: string}  $colors
     */
    private function sidePosts(User $owner, SocialAccount $channel, array $texts, array $colors): void
    {
        foreach ($texts as $index => $text) {
            $this->post($owner, $channel, $text, CarbonImmutable::now('UTC')->addDays($index + 1)->setTimeFromTimeString('09:00'), $colors);
        }
    }

    /**
     * A plain brand graphic (a color field, a disc and a rule), drawn here so
     * the demo needs no stock image.
     *
     * @param  array{0: string, 1: string}  $colors
     * @return array<string, mixed>
     */
    private function graphic(array $colors): array
    {
        $image = imagecreatetruecolor(540, 540);
        [$background, $shape] = array_map(fn (string $hex): int => (int) imagecolorallocate(
            $image,
            (int) hexdec(substr($hex, 1, 2)),
            (int) hexdec(substr($hex, 3, 2)),
            (int) hexdec(substr($hex, 5, 2)),
        ), $colors);

        imagefill($image, 0, 0, $background);
        imagefilledellipse($image, 270, 250, 300, 300, $shape);
        imagefilledrectangle($image, 90, 450, 450, 470, $shape);

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();

        return [
            'id' => (string) Str::uuid(),
            'type' => 'image',
            'mime_type' => 'image/png',
            'path' => 'demo/landing.png',
            'url' => 'data:image/png;base64,'.base64_encode($png),
            'size' => strlen($png),
            'meta' => ['width' => 540, 'height' => 540],
        ];
    }
}
