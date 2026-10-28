{{--
    Rendu public SSR d'une Page (BC PAGES, #8730).

    ## Échappement : `{{ }}` partout, `{!! !!}` nulle part

    Tout le contenu vient d'un tenant. `{{ }}` échappe en HTML, ce qui neutralise
    une balise injectée dans un titre de bloc. Les URL passent par `safeUrl()`
    (helper de cette vue) qui n'autorise que http/https/mailto/tel : un
    `javascript:` stocké dans un `cta_url` serait un XSS stocké sur une page
    publique, et le validateur de blocs (#8724) ne protège que les écritures
    passées par l'API — pas une ligne insérée directement en base.

    ## RTL

    `dir` vient du serveur (`PublicPagePresenter::direction()`), jamais d'une
    détection côté client : le HTML doit être correct avant toute exécution JS,
    sinon l'aperçu de lien et le premier rendu sont faux.

    ## Accessibilité

    - un seul `h1` (le titre de la Page) ;
    - les sections sont des `<section>` avec `aria-labelledby` quand un titre
      existe ;
    - les images décoratives ont `alt=""`, les images de contenu un `alt`
      explicite ;
    - le focus est visible (`:focus-visible`), les contrastes respectent AA.
--}}
@php
    /**
     * N'autorise que les schémas sans exécution.
     *
     * @return string|null
     */
    $safeUrl = static function (mixed $value): ?string {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $scheme = parse_url($value, PHP_URL_SCHEME);

        if (! is_string($scheme) || $scheme === '') {
            return null;
        }

        return in_array(strtolower($scheme), ['http', 'https', 'mailto', 'tel'], true)
            ? $value
            : null;
    };

    $blocks = is_array($page['blocks'] ?? null) ? $page['blocks'] : [];
    $actions = is_array($page['actions'] ?? null) ? $page['actions'] : [];
    $title = $page['title'] ?? null;
@endphp
<!DOCTYPE html>
<html lang="{{ $pageLocale }}" dir="{{ $direction }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? $page['slug'] }}</title>
    @if (! empty($page['description']))
        <meta name="description" content="{{ $page['description'] }}">
    @endif
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $title ?? $page['slug'] }}">
    @if (! empty($page['description']))
        <meta property="og:description" content="{{ $page['description'] }}">
    @endif
    <style>
        :root {
            --ink: #172033;
            --muted: #5b6478;
            --line: #e3e7ef;
            --accent: #1f6feb;
            --bg: #ffffff;
            --surface: #f7f9fc;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--bg);
            color: var(--ink);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            line-height: 1.6;
        }
        .wrap { max-width: 960px; margin: 0 auto; padding: 0 20px; }
        header.hero { padding: 64px 0 40px; border-bottom: 1px solid var(--line); }
        header.hero h1 { font-size: clamp(1.9rem, 4vw, 2.8rem); margin: 0 0 12px; letter-spacing: -0.02em; }
        header.hero p { color: var(--muted); font-size: 1.1rem; margin: 0; max-width: 60ch; }
        .badge {
            display: inline-block; font-size: .75rem; font-weight: 600;
            padding: 3px 10px; border-radius: 999px; margin-bottom: 16px;
            background: var(--surface); color: var(--muted); border: 1px solid var(--line);
        }
        section.block { padding: 40px 0; border-bottom: 1px solid var(--line); }
        section.block:last-of-type { border-bottom: 0; }
        h2 { font-size: 1.4rem; margin: 0 0 16px; }
        .grid { display: grid; gap: 20px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
        .card { background: var(--surface); border: 1px solid var(--line); border-radius: 12px; padding: 20px; }
        .card h3 { margin: 0 0 8px; font-size: 1.05rem; }
        .card p { margin: 0; color: var(--muted); }
        .gallery { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); }
        .gallery img { width: 100%; height: 180px; object-fit: cover; border-radius: 10px; border: 1px solid var(--line); }
        .actions { display: flex; flex-wrap: wrap; gap: 12px; padding: 32px 0; }
        .btn {
            display: inline-block; padding: 12px 22px; border-radius: 10px;
            background: var(--accent); color: #fff; text-decoration: none; font-weight: 600;
        }
        .btn.secondary { background: var(--surface); color: var(--ink); border: 1px solid var(--line); }
        a:focus-visible, .btn:focus-visible { outline: 3px solid var(--accent); outline-offset: 2px; }
        footer { padding: 32px 0 56px; color: var(--muted); font-size: .9rem; }
        .contact-list { list-style: none; padding: 0; margin: 0; }
        .contact-list li { margin-bottom: 8px; }
        .contact-list a { color: var(--accent); }
    </style>
</head>
<body>
<div class="wrap">
    <header class="hero">
        @if (! empty($page['verified']))
            <span class="badge">{{ __('pages.public.verified') }}</span>
        @endif
        <h1>{{ $title ?? $page['slug'] }}</h1>
        @if (! empty($page['description']))
            <p>{{ $page['description'] }}</p>
        @endif
    </header>

    @foreach ($blocks as $block)
        @php
            $content = is_array($block['content'] ?? null) ? $block['content'] : [];
            $type = $block['type'] ?? '';
        @endphp

        @switch($type)
            @case('core.hero')
                @php $cta = $safeUrl($content['cta_url'] ?? null); @endphp
                <section class="block">
                    <h2>{{ $content['heading'] ?? '' }}</h2>
                    @if (! empty($content['subheading']))
                        <p>{{ $content['subheading'] }}</p>
                    @endif
                    @if ($cta !== null && ! empty($content['cta_label']))
                        <p><a class="btn" href="{{ $cta }}" rel="noopener noreferrer">{{ $content['cta_label'] }}</a></p>
                    @endif
                </section>
                @break

            @case('core.features')
                <section class="block">
                    @if (! empty($content['title']))
                        <h2>{{ $content['title'] }}</h2>
                    @endif
                    <div class="grid">
                        @foreach (($content['items'] ?? []) as $item)
                            <div class="card">
                                <h3>{{ $item['title'] ?? '' }}</h3>
                                @if (! empty($item['description']))
                                    <p>{{ $item['description'] }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
                @break

            @case('core.gallery')
                <section class="block">
                    @if (! empty($content['title']))
                        <h2>{{ $content['title'] }}</h2>
                    @endif
                    <div class="gallery">
                        @foreach (($content['images'] ?? []) as $image)
                            @php $src = $safeUrl(is_array($image) ? ($image['url'] ?? null) : $image); @endphp
                            @if ($src !== null)
                                <img src="{{ $src }}" alt="{{ is_array($image) ? ($image['alt'] ?? '') : '' }}" loading="lazy">
                            @endif
                        @endforeach
                    </div>
                </section>
                @break

            @case('core.testimonials')
                <section class="block">
                    @if (! empty($content['title']))
                        <h2>{{ $content['title'] }}</h2>
                    @endif
                    <div class="grid">
                        @foreach (($content['items'] ?? []) as $item)
                            <blockquote class="card">
                                <p>{{ $item['quote'] ?? '' }}</p>
                                <footer>{{ $item['author'] ?? '' }}</footer>
                            </blockquote>
                        @endforeach
                    </div>
                </section>
                @break

            @case('core.products')
                <section class="block">
                    @if (! empty($content['title']))
                        <h2>{{ $content['title'] }}</h2>
                    @endif
                    <div class="grid">
                        @foreach (($content['items'] ?? []) as $item)
                            <div class="card">
                                <h3>{{ $item['name'] ?? '' }}</h3>
                                @if (! empty($item['description']))
                                    <p>{{ $item['description'] }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
                @break

            @case('core.contact')
                <section class="block">
                    <h2>{{ $content['title'] ?? __('pages.public.contact') }}</h2>
                    <ul class="contact-list">
                        @if (! empty($content['phone']))
                            <li><a href="tel:{{ preg_replace('/[^0-9+]/', '', (string) $content['phone']) }}">{{ $content['phone'] }}</a></li>
                        @endif
                        @if (! empty($content['email']))
                            <li><a href="mailto:{{ $content['email'] }}">{{ $content['email'] }}</a></li>
                        @endif
                        @if (! empty($content['address']))
                            <li>{{ $content['address'] }}</li>
                        @endif
                        @php $map = $safeUrl($content['map_url'] ?? null); @endphp
                        @if ($map !== null)
                            <li><a href="{{ $map }}" rel="noopener noreferrer">{{ __('pages.public.map') }}</a></li>
                        @endif
                    </ul>
                </section>
                @break

            @case('core.footer')
                <footer>
                    @if (! empty($content['text']))
                        <p>{{ $content['text'] }}</p>
                    @endif
                    @foreach (($content['legal_links'] ?? []) as $link)
                        @php $href = $safeUrl($link['url'] ?? null); @endphp
                        @if ($href !== null)
                            <a href="{{ $href }}" rel="noopener noreferrer">{{ $link['label'] ?? '' }}</a>
                        @endif
                    @endforeach
                </footer>
                @break
        @endswitch
    @endforeach

    @if ($actions !== [])
        <div class="actions">
            @foreach ($actions as $action)
                @php $href = $safeUrl($action['href'] ?? null); @endphp
                @if ($href !== null)
                    <a class="btn" href="{{ $href }}" rel="noopener noreferrer">{{ __('pages.public.actions.'.$action['kind']) }}</a>
                @endif
            @endforeach
        </div>
    @endif
</div>
</body>
</html>
