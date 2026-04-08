<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @auth
        <script>
            (function () {
                try {
                    var v = localStorage.getItem('budgetbuddy-sidebar-collapsed');
                    if (v === '1') {
                        document.documentElement.classList.add('bb-sidebar-collapsed');
                    } else {
                        document.documentElement.classList.remove('bb-sidebar-collapsed');
                    }
                } catch (e) {}
            })();
        </script>
    @endauth
    <script>
        (function () {
            try {
                var k = 'budgetbuddy-theme';
                var ls = localStorage.getItem(k);
                var ss = sessionStorage.getItem(k);
                var t = null;
                if (ls === 'dracula' || ls === 'cupcake') {
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
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/budget-buddy-logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/budget-buddy-logo.png') }}">
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
        <div class="flex min-h-screen flex-col">
            @persist('budgetbuddy-navbar')
                @include('partials.app-navbar')
            @endpersist
            <div class="drawer lg:drawer-open min-h-0 w-full flex-1">
                <input id="bb-mobile-nav" type="checkbox" class="drawer-toggle" />
                <div class="drawer-content flex min-h-0 min-w-0 flex-1 flex-col bg-base-200">
                    <div id="bb-app-shell" class="flex min-h-0 min-w-0 flex-1 flex-col">
                        <div
                            id="bb-toast-host"
                            class="toast toast-end toast-top z-[100] w-auto max-w-[min(100vw-1rem,24rem)] gap-2 p-3 sm:p-4"
                            aria-live="polite"
                            aria-relevant="additions"
                        ></div>
                        @include('partials.smart-mode-banner')
                        @isset($slot)
                            {{ $slot }}
                        @else
                            @yield('body')
                        @endisset
                    </div>
                </div>
                <div class="drawer-side z-50 border-e border-base-300 bg-base-200">
                    <label for="bb-mobile-nav" aria-label="{{ __('Close menu') }}" class="drawer-overlay lg:hidden"></label>
                    @persist('budgetbuddy-sidebar')
                        @include('partials.app-sidebar')
                    @endpersist
                </div>
            </div>
        </div>
    @else
        @isset($slot)
            {{ $slot }}
        @else
            @yield('body')
        @endisset
    @endauth
    @livewireScripts
</body>
</html>
