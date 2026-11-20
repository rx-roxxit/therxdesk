/** A network the landing lists, as the server reads it from the enabled platforms. */
export interface LandingNetwork {
    value: string;
    label: string;
}

/** The app screens the landing shows, captured from the demo desk. */
export type LandingScreenshotKey =
    | 'calendar_list'
    | 'composer'
    | 'calendar_month'
    | 'approvals'
    | 'assistant';

/** One capture of a screen: its file and its size in CSS pixels (the file is 2×). */
export interface LandingScreenshotImage {
    src: string;
    width: number;
    height: number;
}

/** A screen captured at desktop width and, separately, at phone width. */
export interface LandingScreenshot {
    desktop: LandingScreenshotImage;
    phone: LandingScreenshotImage;
}

/** The sections of the landing that pair a short argument with a screen. */
export type LandingFeatureSection =
    | 'composer'
    | 'calendar'
    | 'workspaces'
    | 'assistant';
