@php
    $isAdmin = request()->is('admin*');
    $seo = $isAdmin ? null : seo()->toArray();
    $gtm = $isAdmin ? null : \App\Models\Setting::get('gtm_container_id');
    $ga = $isAdmin ? null : \App\Models\Setting::get('ga_measurement_id');
    $pixel = $isAdmin ? null : \App\Models\Setting::get('facebook_pixel_id');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @if ($seo)
            <title inertia>{{ $seo['title'] }}</title>
            @foreach ($seo['tags'] as $t)
                @if ($t['tag'] === 'script')
                    <script inertia="{{ $t['key'] }}" type="application/ld+json">{!! $t['html'] !!}</script>
                @else
                    <{{ $t['tag'] }} inertia="{{ $t['key'] }}" @foreach ($t['attrs'] as $name => $value){{ $name }}="{{ $value }}" @endforeach>
                @endif
            @endforeach
        @else
            <title inertia>{{ \App\Models\Setting::get('site_name', config('app.name')) }}</title>
            <meta name="robots" content="noindex, nofollow">
        @endif
        <meta name="theme-color" content="#0f3d5c">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        @unless ($isAdmin)
            <link rel="manifest" href="/manifest.webmanifest">
            <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
            <meta name="mobile-web-app-capable" content="yes">
            <meta name="apple-mobile-web-app-status-bar-style" content="default">
        @endunless

        @if ($gtm)
            <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer',@json($gtm));</script>
        @endif
        @if ($ga)
            <script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga }}"></script>
            {{-- Page views are sent on every Inertia navigation (resources/js/lib/analytics.js). --}}
            <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config',@json($ga),{send_page_view:false});</script>
        @endif
        @if ($pixel)
            <script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init',@json($pixel));</script>
        @endif

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
        @inertiaHead
    </head>
    <body class="antialiased">
        @if ($gtm)
            <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $gtm }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        @endif
        @if ($pixel)
            <noscript><img height="1" width="1" style="display:none" alt="" src="https://www.facebook.com/tr?id={{ $pixel }}&ev=PageView&noscript=1"></noscript>
        @endif
        @inertia
    </body>
</html>
