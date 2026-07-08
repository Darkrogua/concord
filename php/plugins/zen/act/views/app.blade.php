<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#5288c1">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Акт">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="manifest" href="/plugins/zen/act/assets/manifest.webmanifest">
    <link rel="icon" href="/plugins/zen/act/assets/pwa/favicon.ico" sizes="48x48">
    <link rel="icon" href="/plugins/zen/act/assets/pwa/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="/plugins/zen/act/assets/pwa/favicon-96x96.png" type="image/png" sizes="96x96">
    <link rel="apple-touch-icon" href="/plugins/zen/act/assets/pwa/apple-touch-icon.png">
    <title>{{ $og['title'] ?? 'Акт' }}</title>
    @if (!empty($og))
        <meta name="description" content="{{ e($og['description']) }}">
        <meta property="og:title" content="{{ e($og['title']) }}">
        <meta property="og:description" content="{{ e($og['description']) }}">
        <meta property="og:image" content="{{ e($og['image']) }}">
        <meta property="og:image:width" content="{{ (int) $og['image_width'] }}">
        <meta property="og:image:height" content="{{ (int) $og['image_height'] }}">
        <meta property="og:url" content="{{ e($og['url']) }}">
        <meta property="og:type" content="{{ e($og['type']) }}">
        <meta property="og:site_name" content="{{ e($og['site_name']) }}">
        <meta property="og:locale" content="{{ e($og['locale']) }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ e($og['title']) }}">
        <meta name="twitter:description" content="{{ e($og['description']) }}">
        <meta name="twitter:image" content="{{ e($og['image']) }}">
        <link rel="canonical" href="{{ e($og['url']) }}">
    @endif
</head>
<body>
    <div id="act-app"></div>
    {!! \Zen\Act\Classes\Support\Vite::tags([
        'act_app/js/act_app.js',
    ]) !!}
</body>
</html>
