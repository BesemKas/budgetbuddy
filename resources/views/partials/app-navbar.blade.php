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
<div class="navbar bg-base-100 shadow-sm">
    <div class="navbar-start flex flex-col items-stretch gap-1 sm:flex-row sm:items-center">
        <a class="btn btn-ghost text-lg font-semibold tracking-tight" href="{{ route('dashboard') }}">
            {{ config('app.name') }}
        </a>
        @auth
            @if ($navUser->budgets()->count() > 1)
                <div class="dropdown dropdown-end sm:dropdown-bottom">
                    <button tabindex="0" type="button" class="btn btn-ghost btn-sm max-w-[14rem] truncate font-normal" aria-label="{{ __('Budget') }}">
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
    <div class="navbar-center hidden md:flex">
        <ul class="menu menu-horizontal gap-1 px-1">
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
                <a href="{{ route('transactions.import') }}" class="{{ request()->routeIs('transactions.import') ? 'menu-active' : '' }}" wire:navigate>
                    {{ __('Import') }}
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
    <div class="navbar-end gap-2">
        <div class="dropdown dropdown-end md:hidden">
            <button type="button" tabindex="0" class="btn btn-ghost btn-square" aria-label="{{ __('Menu') }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
            </button>
            <ul tabindex="0" class="dropdown-content menu bg-base-100 rounded-box z-50 mt-2 w-52 p-2 shadow">
                <li><a href="{{ route('dashboard') }}" wire:navigate>{{ __('Dashboard') }}</a></li>
                <li><a href="{{ route('accounts.index') }}" wire:navigate>{{ __('Accounts') }}</a></li>
                <li><a href="{{ route('categories.index') }}" wire:navigate>{{ __('Categories') }}</a></li>
                <li><a href="{{ route('budget.activity') }}" wire:navigate>{{ __('Activity') }}</a></li>
                <li><a href="{{ route('transactions.import') }}" wire:navigate>{{ __('Import') }}</a></li>
                <li><a href="{{ route('settings') }}" wire:navigate>{{ __('Settings') }}</a></li>
                @can('invite', $currentBudget)
                    <li><a href="{{ route('budget.team') }}" wire:navigate>{{ __('Team') }}</a></li>
                @endcan
            </ul>
        </div>
        <form method="POST" action="{{ route('logout') }}" class="inline">
            @csrf
            <button type="submit" class="btn btn-ghost btn-sm">{{ __('Log out') }}</button>
        </form>
    </div>
</div>
