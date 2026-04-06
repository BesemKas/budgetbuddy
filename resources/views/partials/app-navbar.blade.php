@php
    $navUser = auth()->user();
    $currentBudget = $navUser ? app(\App\Services\CurrentBudget::class)->current() : null;
    $switcherBudgets = $navUser && $currentBudget
        ? $navUser->budgets()->with('owner')->get()->sortBy(function ($b) use ($navUser) {
            $isOwn = (int) $b->owner_user_id === (int) $navUser->id;

            return [$isOwn ? 0 : 1, $b->displayNameFor($navUser)];
        })->values()
        : collect();
@endphp
<div class="navbar sticky top-0 z-40 min-h-14 flex-wrap gap-y-2 border-b border-base-300/40 bg-base-100/95 px-2 shadow-sm backdrop-blur-sm sm:px-4">
    <div class="navbar-start flex min-w-0 flex-1 flex-col items-stretch gap-1 sm:max-w-none sm:flex-row sm:items-center sm:gap-2">
        <a class="btn btn-ghost h-auto min-h-11 max-w-full shrink gap-2 px-2 text-base font-semibold tracking-tight sm:text-lg" href="{{ route('dashboard') }}">
            <img
                src="{{ asset('images/budget-buddy-logo.png') }}"
                alt="{{ config('app.name') }}"
                class="h-9 w-auto max-w-[min(100%,11rem)] object-contain object-left sm:h-10 sm:max-w-[13rem]"
                width="208"
                height="80"
                loading="eager"
                decoding="async"
            />
        </a>
        @auth
            @if ($navUser->budgets()->count() > 1)
                <div class="dropdown dropdown-end sm:dropdown-bottom">
                    <button tabindex="0" type="button" class="btn btn-ghost btn-sm max-w-full truncate font-normal sm:max-w-[14rem]" aria-label="{{ __('Budget') }}">
                        {{ $currentBudget->displayNameFor($navUser) }}
                    </button>
                    <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box z-50 mt-2 w-56 border border-base-300/60 p-2 shadow">
                        @foreach ($switcherBudgets as $budget)
                            <li>
                                @if ($budget->id === $currentBudget->id)
                                    <span class="menu-active pointer-events-none">{{ $budget->displayNameFor($navUser) }}</span>
                                @else
                                    <form method="POST" action="{{ route('budget.switch', $budget) }}" class="w-full">
                                        @csrf
                                        <button type="submit" class="w-full text-left">{{ $budget->displayNameFor($navUser) }}</button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @elseif ($currentBudget)
                <span class="text-base-content/70 hidden px-2 text-xs sm:inline sm:text-sm">{{ $currentBudget->displayNameFor($navUser) }}</span>
            @endif
        @endauth
    </div>
    <div class="navbar-center hidden min-w-0 md:flex">
        <ul class="menu menu-horizontal flex-nowrap gap-0 px-0 text-sm lg:gap-1 lg:px-1 lg:text-base">
            <li>
                <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'menu-active' : '' }}" wire:navigate>
                    {{ __('Dashboard') }}
                </a>
            </li>
            <li>
                <a href="{{ route('accounts.index') }}" class="{{ request()->routeIs('accounts.*') ? 'menu-active' : '' }}" wire:navigate>
                    {{ __('Accounts') }}
                </a>
            </li>
            <li>
                <a href="{{ route('categories.index') }}" class="{{ request()->routeIs('categories.*') ? 'menu-active' : '' }}" wire:navigate>
                    {{ __('Categories') }}
                </a>
            </li>
            <li>
                <a href="{{ route('budget.activity') }}" class="{{ request()->routeIs('budget.activity') ? 'menu-active' : '' }}" wire:navigate>
                    {{ __('Activity') }}
                </a>
            </li>
            <li>
                <a href="{{ route('budget.planner') }}" class="{{ request()->routeIs('budget.planner') ? 'menu-active' : '' }}" wire:navigate>
                    {{ __('Planner') }}
                </a>
            </li>
            <li>
                <a href="{{ route('budget.history') }}" class="{{ request()->routeIs('budget.history') ? 'menu-active' : '' }}" wire:navigate>
                    {{ __('History') }}
                </a>
            </li>
            <li>
                <a href="{{ route('transactions.import') }}" class="{{ request()->routeIs('transactions.import') ? 'menu-active' : '' }}" wire:navigate>
                    {{ __('Import') }}
                </a>
            </li>
            <li>
                <a href="{{ route('tools.tax') }}" class="{{ request()->routeIs('tools.tax') ? 'menu-active' : '' }}" wire:navigate>
                    {{ __('Tax') }}
                </a>
            </li>
            <li>
                <a href="{{ route('settings') }}" class="{{ request()->routeIs('settings') ? 'menu-active' : '' }}" wire:navigate>
                    {{ __('Settings') }}
                </a>
            </li>
            @can('invite', $currentBudget)
                <li>
                    <a href="{{ route('budget.team') }}" class="{{ request()->routeIs('budget.team') ? 'menu-active' : '' }}" wire:navigate>
                        {{ __('Team') }}
                    </a>
                </li>
            @endcan
        </ul>
    </div>
    <div class="navbar-end shrink-0 gap-1 sm:gap-2">
        <livewire:notification-bell />
        @include('partials.theme-switcher')
        <div class="dropdown dropdown-end md:hidden">
            <button type="button" tabindex="0" class="btn btn-ghost btn-square min-h-11 min-w-11" aria-label="{{ __('Menu') }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
            </button>
            <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box z-50 mt-2 max-h-[min(70dvh,24rem)] w-[min(100vw-1rem,18rem)] overflow-y-auto overscroll-contain p-2 shadow">
                <li><a href="{{ route('dashboard') }}" wire:navigate>{{ __('Dashboard') }}</a></li>
                <li><a href="{{ route('accounts.index') }}" wire:navigate>{{ __('Accounts') }}</a></li>
                <li><a href="{{ route('categories.index') }}" wire:navigate>{{ __('Categories') }}</a></li>
                <li><a href="{{ route('budget.activity') }}" wire:navigate>{{ __('Activity') }}</a></li>
                <li><a href="{{ route('budget.planner') }}" wire:navigate>{{ __('Planner') }}</a></li>
                <li><a href="{{ route('budget.history') }}" wire:navigate>{{ __('History') }}</a></li>
                <li><a href="{{ route('transactions.import') }}" wire:navigate>{{ __('Import') }}</a></li>
                <li><a href="{{ route('tools.tax') }}" wire:navigate>{{ __('Tax') }}</a></li>
                <li><a href="{{ route('settings') }}" wire:navigate>{{ __('Settings') }}</a></li>
                @can('invite', $currentBudget)
                    <li><a href="{{ route('budget.team') }}" wire:navigate>{{ __('Team') }}</a></li>
                @endcan
            </ul>
        </div>
        <form method="POST" action="{{ route('logout') }}" class="inline">
            @csrf
            <button type="submit" class="btn btn-ghost btn-sm min-h-11 px-2 sm:px-3">{{ __('Log out') }}</button>
        </form>
    </div>
</div>
