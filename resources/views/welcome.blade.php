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
    <meta name="description" content="{{ __('Personal and household budgeting: accounts in multiple currencies, monthly plans, imports, and shared budgets—sign in with email, no password.') }}">
    <title>{{ __('Budget Buddy') }} — {{ __('Plan money with confidence') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/budget-buddy-logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/budget-buddy-logo.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen overflow-x-hidden bg-base-100 font-sans antialiased text-base-content">
    <header class="navbar sticky top-0 z-50 min-h-16 border-b border-base-300/60 bg-base-100/85 px-4 shadow-sm backdrop-blur-md sm:px-6 lg:px-8">
        <div class="navbar-start flex-1 gap-2">
            <a href="{{ route('home') }}" class="btn btn-ghost gap-3 px-2 text-lg font-semibold normal-case hover:bg-base-200">
                <img
                    src="{{ asset('images/budget-buddy-logo.png') }}"
                    alt=""
                    width="40"
                    height="40"
                    class="h-9 w-9 shrink-0 rounded-xl shadow-sm"
                />
                <span class="hidden sm:inline">{{ __('Budget Buddy') }}</span>
            </a>
        </div>
        <div class="navbar-end gap-1 sm:gap-2">
            <details class="dropdown dropdown-end lg:hidden">
                <summary class="btn btn-ghost btn-square" aria-label="{{ __('Menu') }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </summary>
                <ul class="dropdown-content menu z-[60] mt-2 w-56 rounded-box border border-base-300/60 bg-base-100 p-2 shadow-lg" role="menu">
                    <li>
                        <a href="#features" class="justify-between" onclick="this.closest('details')?.removeAttribute('open')">{{ __('Features') }}</a>
                    </li>
                    @if (Route::has('login'))
                        @auth
                            <li>
                                <a href="{{ url('/dashboard') }}" class="justify-between font-medium" onclick="this.closest('details')?.removeAttribute('open')">{{ __('Dashboard') }}</a>
                            </li>
                        @else
                            <li>
                                <a href="{{ route('login') }}" class="justify-between font-medium" onclick="this.closest('details')?.removeAttribute('open')">{{ __('Log in') }}</a>
                            </li>
                        @endauth
                    @endif
                </ul>
            </details>
            <a href="#features" class="btn btn-ghost btn-sm hidden normal-case text-base-content/80 lg:inline-flex">{{ __('Features') }}</a>
            @if (Route::has('login'))
                @auth
                    <a href="{{ url('/dashboard') }}" class="btn btn-primary btn-sm hidden lg:inline-flex sm:btn-md">{{ __('Dashboard') }}</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary btn-sm hidden lg:inline-flex sm:btn-md">{{ __('Log in') }}</a>
                @endauth
            @endif
            @include('partials.theme-switcher')
        </div>
    </header>

    <main>
        <section class="relative overflow-hidden">
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-primary/[0.09] via-transparent to-transparent" aria-hidden="true"></div>
            <div class="relative mx-auto grid max-w-6xl items-center gap-12 px-4 pb-20 pt-12 sm:px-6 lg:grid-cols-2 lg:gap-16 lg:pb-28 lg:pt-16">
                <div class="flex flex-col text-center lg:text-start">
                    <p class="mb-4 text-sm font-semibold uppercase tracking-widest text-primary">
                        {{ __('Household money, one calm place') }}
                    </p>
                    <h1 class="text-balance text-4xl font-bold tracking-tight text-base-content sm:text-5xl lg:text-6xl">
                        {{ __('Budget Buddy') }}
                    </h1>
                    <p class="mx-auto mt-6 max-w-xl text-lg leading-relaxed text-base-content/70 lg:mx-0">
                        {{ __('Track accounts in multiple currencies, plan monthly category budgets, import bank files, and share selected accounts with a partner—without spreadsheet chaos.') }}
                    </p>
                    <div class="mt-10 flex flex-col items-stretch justify-center gap-3 sm:flex-row sm:justify-start lg:justify-start">
                        @auth
                            <a href="{{ url('/dashboard') }}" class="btn btn-primary btn-lg px-8">{{ __('Open your dashboard') }}</a>
                        @else
                            @if (Route::has('login'))
                                <a href="{{ route('login') }}" class="btn btn-primary btn-lg px-8">{{ __('Get started') }}</a>
                            @endif
                        @endauth
                        <a href="#features" class="btn btn-lg btn-outline border-base-300 px-8">{{ __('See what you can do') }}</a>
                    </div>
                    <p class="mt-8 text-sm text-base-content/55">
                        {{ __('Sign in with your email—we send a one-time code. No password to remember.') }}
                    </p>
                </div>
                <div class="relative mx-auto w-full max-w-md lg:mx-0 lg:max-w-none">
                    <div class="absolute -inset-4 rounded-[2rem] bg-gradient-to-br from-primary/15 via-base-200/50 to-secondary/10 blur-2xl lg:-inset-6" aria-hidden="true"></div>
                    <div class="card relative border border-base-300/80 bg-base-100 shadow-xl">
                        <div class="card-body gap-6 p-6 sm:p-8">
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-sm font-medium text-base-content/60">{{ __('Preview') }}</span>
                                <span class="badge badge-primary badge-outline badge-sm">{{ __('This month') }}</span>
                            </div>
                            <div class="stats stats-vertical shadow-sm sm:stats-horizontal">
                                <div class="stat place-items-start border-base-300 py-4 sm:border-e">
                                    <div class="stat-title">{{ __('Income') }}</div>
                                    <div class="stat-value text-lg text-success sm:text-2xl">—</div>
                                </div>
                                <div class="stat place-items-start border-base-300 py-4 sm:border-e">
                                    <div class="stat-title">{{ __('Expenses') }}</div>
                                    <div class="stat-value text-lg sm:text-2xl">—</div>
                                </div>
                                <div class="stat place-items-start py-4">
                                    <div class="stat-title">{{ __('Net') }}</div>
                                    <div class="stat-value text-lg text-primary sm:text-2xl">—</div>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <div class="flex items-center justify-between text-xs font-medium uppercase tracking-wide text-base-content/50">
                                    <span>{{ __('Categories') }}</span>
                                    <span>{{ __('Planned') }}</span>
                                </div>
                                <div class="space-y-2">
                                    <div class="h-2.5 w-full overflow-hidden rounded-full bg-base-200">
                                        <div class="h-full w-3/5 rounded-full bg-primary/40"></div>
                                    </div>
                                    <div class="h-2.5 w-full overflow-hidden rounded-full bg-base-200">
                                        <div class="h-full w-2/5 rounded-full bg-secondary/40"></div>
                                    </div>
                                    <div class="h-2.5 w-full overflow-hidden rounded-full bg-base-200">
                                        <div class="h-full w-4/5 rounded-full bg-accent/50"></div>
                                    </div>
                                </div>
                            </div>
                            <p class="text-center text-xs text-base-content/50">
                                {{ __('Your real numbers appear after you sign in.') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="features" class="scroll-mt-20 border-t border-base-300 bg-base-200/80">
            <div class="mx-auto max-w-6xl px-4 py-20 sm:px-6 lg:py-24">
                <header class="mx-auto mb-14 max-w-2xl text-center lg:mb-16">
                    <h2 class="text-3xl font-bold tracking-tight text-base-content sm:text-4xl">
                        {{ __('Built for real budgets') }}
                    </h2>
                    <p class="mt-4 text-lg text-base-content/70">
                        {{ __('Everything you need to see the month ahead—not just the month behind.') }}
                    </p>
                </header>
                <ul class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 lg:gap-6">
                    <li class="card card-border bg-base-100 shadow-sm transition-shadow hover:shadow-md">
                        <div class="card-body gap-4 p-6 sm:p-7">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/12 text-primary" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold leading-snug">{{ __('Multi-currency accounts') }}</h3>
                                <p class="mt-2 text-sm leading-relaxed text-base-content/70">{{ __('Connect liquid and credit accounts with the right currency and rates for your workspace.') }}</p>
                            </div>
                        </div>
                    </li>
                    <li class="card card-border bg-base-100 shadow-sm transition-shadow hover:shadow-md">
                        <div class="card-body gap-4 p-6 sm:p-7">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/12 text-primary" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold leading-snug">{{ __('Categories & monthly planner') }}</h3>
                                <p class="mt-2 text-sm leading-relaxed text-base-content/70">{{ __('Assign income and spending to categories and shape each month before it spends you.') }}</p>
                            </div>
                        </div>
                    </li>
                    <li class="card card-border bg-base-100 shadow-sm transition-shadow hover:shadow-md">
                        <div class="card-body gap-4 p-6 sm:p-7">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/12 text-primary" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold leading-snug">{{ __('Bank imports') }}</h3>
                                <p class="mt-2 text-sm leading-relaxed text-base-content/70">{{ __('Bring in transactions in bulk so your ledger stays current without manual typing.') }}</p>
                            </div>
                        </div>
                    </li>
                    <li class="card card-border bg-base-100 shadow-sm transition-shadow hover:shadow-md">
                        <div class="card-body gap-4 p-6 sm:p-7">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/12 text-primary" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold leading-snug">{{ __('Shared budgets') }}</h3>
                                <p class="mt-2 text-sm leading-relaxed text-base-content/70">{{ __('Invite a partner to view chosen accounts while everyone keeps a private personal budget.') }}</p>
                            </div>
                        </div>
                    </li>
                    <li class="card card-border bg-base-100 shadow-sm transition-shadow hover:shadow-md">
                        <div class="card-body gap-4 p-6 sm:p-7">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/12 text-primary" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold leading-snug">{{ __('Activity & trends') }}</h3>
                                <p class="mt-2 text-sm leading-relaxed text-base-content/70">{{ __('Review recent transactions and charts so patterns surface before they become surprises.') }}</p>
                            </div>
                        </div>
                    </li>
                    <li class="card border-primary/25 bg-base-100 shadow-md ring-1 ring-primary/15 transition-shadow hover:shadow-lg sm:col-span-2 lg:col-span-1">
                        <div class="card-body gap-4 p-6 sm:p-7">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/15 text-primary" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold leading-snug">{{ __('A peek inside') }}</h3>
                                <p class="mt-2 text-sm leading-relaxed text-base-content/70">{{ __('Dashboard summaries, rolling averages, privacy blur for shared screens, and optional tax helpers—ready when you are.') }}</p>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>
        </section>

        <section class="border-t border-base-300 bg-base-100 px-4 py-20 sm:px-6 lg:py-24">
            <div class="mx-auto max-w-3xl text-center">
                <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ __('Ready when you are') }}</h2>
                <p class="mx-auto mt-4 max-w-lg text-lg text-base-content/70">
                    {{ __('Create nothing until you sign in—then build your budget workspace step by step.') }}
                </p>
                <div class="mt-10">
                    @guest
                        @if (Route::has('login'))
                            <a href="{{ route('login') }}" class="btn btn-primary btn-lg min-w-[14rem] px-10">{{ __('Continue with email') }}</a>
                        @endif
                    @else
                        <a href="{{ url('/dashboard') }}" class="btn btn-primary btn-lg min-w-[14rem] px-10">{{ __('Go to dashboard') }}</a>
                    @endguest
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-base-300 bg-base-200 py-10 text-center">
        <p class="text-sm text-base-content/55">&copy; {{ date('Y') }} {{ config('app.name') }}</p>
    </footer>
</body>
</html>
