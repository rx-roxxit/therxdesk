{{-- A public page's meta (`App\Support\PublicPages\PublicPageMeta`), in the
     HTML so crawlers and link previews read it without running JavaScript.
     Each tag carries the `data-inertia` key `PublicPageHead.vue` gives it,
     so a visit inside the site replaces these tags instead of adding more. --}}
<title data-inertia>{{ $meta['document_title'] }}</title>
<meta name="description" content="{{ $meta['description'] }}" data-inertia="description">
<link rel="canonical" href="{{ $meta['canonical'] }}" data-inertia="canonical">
<meta property="og:type" content="website" data-inertia="og:type">
<meta property="og:site_name" content="{{ $meta['site_name'] }}" data-inertia="og:site_name">
<meta property="og:title" content="{{ $meta['document_title'] }}" data-inertia="og:title">
<meta property="og:description" content="{{ $meta['description'] }}" data-inertia="og:description">
<meta property="og:url" content="{{ $meta['canonical'] }}" data-inertia="og:url">
@if (filled($meta['image']))
    <meta property="og:image" content="{{ $meta['image']['url'] }}" data-inertia="og:image">
    <meta property="og:image:width" content="{{ $meta['image']['width'] }}" data-inertia="og:image:width">
    <meta property="og:image:height" content="{{ $meta['image']['height'] }}" data-inertia="og:image:height">
@endif
<meta name="twitter:card" content="summary_large_image" data-inertia="twitter:card">
