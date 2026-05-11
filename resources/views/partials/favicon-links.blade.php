@php
    $path = ltrim((string) config('app.favicon_path', 'favicon.png'), '/');
    if (! is_file(public_path($path))) {
        $path = is_file(public_path('favicon.ico')) ? 'favicon.ico' : 'favicon.png';
    }
    $full = public_path($path);
    $v = is_file($full) ? '?v='.filemtime($full) : '';
    $href = asset($path).$v;
    $ext = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
    $type = match ($ext) {
        'ico' => 'image/x-icon',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'jpg', 'jpeg' => 'image/jpeg',
        default => 'image/png',
    };
    $icoFull = public_path('favicon.ico');
    $hasIco = is_file($icoFull);
    $icoHref = $hasIco ? asset('favicon.ico').'?v='.filemtime($icoFull) : null;
@endphp
{{-- ?v=filemtime 으로 브라우저 파비콘 캐시 무력화. shortcut icon 병기. --}}
<link rel="icon" type="{{ $type }}" href="{{ $href }}">
@if($ext !== 'ico' && $icoHref)
    <link rel="icon" href="{{ $icoHref }}" sizes="any">
@endif
<link rel="shortcut icon" href="{{ $href }}">
<link rel="apple-touch-icon" href="{{ $href }}">
