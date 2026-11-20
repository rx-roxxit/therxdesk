import approvalsDesktop from '@/assets/landing/approvals-desktop.webp';
import approvalsPhone from '@/assets/landing/approvals-phone.webp';
import assistantDesktop from '@/assets/landing/assistant-desktop.webp';
import assistantPhone from '@/assets/landing/assistant-phone.webp';
import calendarListDesktop from '@/assets/landing/calendar_list-desktop.webp';
import calendarListPhone from '@/assets/landing/calendar_list-phone.webp';
import calendarMonthDesktop from '@/assets/landing/calendar_month-desktop.webp';
import calendarMonthPhone from '@/assets/landing/calendar_month-phone.webp';
import composerDesktop from '@/assets/landing/composer-desktop.webp';
import composerPhone from '@/assets/landing/composer-phone.webp';
import sizes from '@/assets/landing/screenshots.json';
import type { LandingScreenshot, LandingScreenshotKey } from '@/types/landing';

/**
 * The app screens on the landing: real captures of the demo desk, taken by
 * `tests/fixtures/landing-screenshots-harness.ts` (steps in
 * `resources/js/assets/landing/CAPTURE.md`). The sizes come from the same
 * capture, so a new capture updates them with the images.
 */
export const LANDING_SCREENSHOTS: Record<
    LandingScreenshotKey,
    LandingScreenshot
> = {
    calendar_list: {
        desktop: { src: calendarListDesktop, ...sizes.calendar_list.desktop },
        phone: { src: calendarListPhone, ...sizes.calendar_list.phone },
    },
    composer: {
        desktop: { src: composerDesktop, ...sizes.composer.desktop },
        phone: { src: composerPhone, ...sizes.composer.phone },
    },
    calendar_month: {
        desktop: { src: calendarMonthDesktop, ...sizes.calendar_month.desktop },
        phone: { src: calendarMonthPhone, ...sizes.calendar_month.phone },
    },
    approvals: {
        desktop: { src: approvalsDesktop, ...sizes.approvals.desktop },
        phone: { src: approvalsPhone, ...sizes.approvals.phone },
    },
    assistant: {
        desktop: { src: assistantDesktop, ...sizes.assistant.desktop },
        phone: { src: assistantPhone, ...sizes.assistant.phone },
    },
};
