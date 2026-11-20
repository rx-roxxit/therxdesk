/** The Open Graph image the public pages share, from the Vite build. */
export interface PublicPageImage {
    url: string;
    /** Pixels, as the tag content Open Graph reads. */
    width: string;
    height: string;
}

/**
 * What a public page tells search engines and link previews, built by
 * `App\Support\PublicPages\PublicPageMeta` and rendered in the HTML by the
 * root view; `PublicPageHead.vue` keeps the head in step on later visits.
 */
export interface PublicPageMeta {
    /** The page's own title; empty shows the app name alone. */
    title: string;
    /** The full title, as the app's title callback writes it. */
    document_title: string;
    site_name: string;
    description: string;
    canonical: string;
    /** Null when the build does not carry the image. */
    image: PublicPageImage | null;
}
