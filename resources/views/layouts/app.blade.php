<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <script>
        (function () {
            try {
                var k = 'budgetbuddy-theme';
                var ls = localStorage.getItem(k);
                var ss = sessionStorage.getItem(k);
                var t = null;
                if (
                    (ls === 'dracula' || ls === 'cupcake') &&
                    (ss === 'dracula' || ss === 'cupcake') &&
                    ls !== ss
                ) {
                    t = ss;
                } else if (ls === 'dracula' || ls === 'cupcake') {
                    t = ls;
                } else if (ss === 'dracula' || ss === 'cupcake') {
                    t = ss;
                }
                if (t) {
                    document.documentElement.setAttribute('data-theme', t);
                }
            } catch (e) {}
        })();
    </script>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen overflow-x-hidden bg-base-200 font-sans antialiased text-base-content">
    @guest
        <div class="fixed end-3 top-3 z-50 sm:end-4 sm:top-4">
            @include('partials.theme-switcher')
        </div>
    @endguest
    @auth
        @include('partials.app-navbar')
        @include('partials.smart-mode-banner')
    @endauth
    @isset($slot)
        {{ $slot }}
    @else
        @yield('body')
    @endisset
    @livewireScripts
</body>
</html>
