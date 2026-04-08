@php
    $navUser = auth()->user();
    $currentBudget = $navUser ? app(\App\Services\CurrentBudget::class)->current() : null;
@endphp
<aside id="bb-app-sidebar" class="flex min-h-full min-w-0 flex-col" aria-label="{{ __('Primary navigation') }}">
    <div class="flex items-center justify-between border-b border-base-300 px-3 py-3 lg:hidden">
        <span class="text-sm font-semibold text-base-content">{{ __('Menu') }}</span>
        <label for="bb-mobile-nav" class="btn btn-ghost btn-square btn-sm" aria-label="{{ __('Close menu') }}">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </label>
    </div>
    <div class="sidebar-header-inner hidden h-12 shrink-0 items-center border-b border-base-300 px-2 py-1 lg:flex">
        <button
            id="bb-sidebar-toggle"
            type="button"
            class="btn btn-ghost btn-square btn-sm shrink-0"
            aria-controls="bb-app-sidebar"
            aria-expanded="true"
            data-label-collapse="{{ __('Collapse sidebar') }}"
            data-label-expand="{{ __('Expand sidebar') }}"
            aria-label="{{ __('Collapse sidebar') }}"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="bb-sidebar-toggle-icon h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18.75 19.5l-7.5-7.5 7.5-7.5m-6 15L5.25 12l7.5-7.5" />
            </svg>
        </button>
    </div>
    <nav class="flex min-h-0 flex-1 flex-col overflow-y-auto overflow-x-hidden p-2" aria-label="{{ __('App') }}">
        <ul class="menu menu-vertical w-full gap-0.5 p-0">
            <li>
                <a
                    href="{{ route('dashboard') }}"
                    class="{{ request()->routeIs('dashboard') ? 'menu-active' : '' }} tooltip tooltip-right flex !justify-start gap-3"
                    data-tip="{{ __('Dashboard') }}"
                    wire:navigate
                >
                    <x-sidebar-nav-icon name="home" />
                    <span class="sidebar-nav-label truncate">{{ __('Dashboard') }}</span>
                </a>
            </li>
            <li>
                <a
                    href="{{ route('accounts.index') }}"
                    class="{{ request()->routeIs('accounts.*') ? 'menu-active' : '' }} tooltip tooltip-right flex !justify-start gap-3"
                    data-tip="{{ __('Accounts') }}"
                    wire:navigate
                >
                    <x-sidebar-nav-icon name="credit-card" />
                    <span class="sidebar-nav-label truncate">{{ __('Accounts') }}</span>
                </a>
            </li>
            <li>
                <a
                    href="{{ route('categories.index') }}"
                    class="{{ request()->routeIs('categories.*') ? 'menu-active' : '' }} tooltip tooltip-right flex !justify-start gap-3"
                    data-tip="{{ __('Categories') }}"
                    wire:navigate
                >
                    <x-sidebar-nav-icon name="tag" />
                    <span class="sidebar-nav-label truncate">{{ __('Categories') }}</span>
                </a>
            </li>
            <li>
                <a
                    href="{{ route('budget.activity') }}"
                    class="{{ request()->routeIs('budget.activity') ? 'menu-active' : '' }} tooltip tooltip-right flex !justify-start gap-3"
                    data-tip="{{ __('Activity') }}"
                    wire:navigate
                >
                    <x-sidebar-nav-icon name="arrow-trending-up" />
                    <span class="sidebar-nav-label truncate">{{ __('Activity') }}</span>
                </a>
            </li>
            <li>
                <a
                    href="{{ route('budget.planner') }}"
                    class="{{ request()->routeIs('budget.planner') ? 'menu-active' : '' }} tooltip tooltip-right flex !justify-start gap-3"
                    data-tip="{{ __('Planner') }}"
                    wire:navigate
                >
                    <x-sidebar-nav-icon name="calendar-days" />
                    <span class="sidebar-nav-label truncate">{{ __('Planner') }}</span>
                </a>
            </li>
            <li>
                <a
                    href="{{ route('budget.history') }}"
                    class="{{ request()->routeIs('budget.history') ? 'menu-active' : '' }} tooltip tooltip-right flex !justify-start gap-3"
                    data-tip="{{ __('History') }}"
                    wire:navigate
                >
                    <x-sidebar-nav-icon name="clock" />
                    <span class="sidebar-nav-label truncate">{{ __('History') }}</span>
                </a>
            </li>
            <li>
                <a
                    href="{{ route('transactions.import') }}"
                    class="{{ request()->routeIs('transactions.import') ? 'menu-active' : '' }} tooltip tooltip-right flex !justify-start gap-3"
                    data-tip="{{ __('Import') }}"
                    wire:navigate
                >
                    <x-sidebar-nav-icon name="arrow-up-tray" />
                    <span class="sidebar-nav-label truncate">{{ __('Import') }}</span>
                </a>
            </li>
            <li>
                <a
                    href="{{ route('tools.tax') }}"
                    class="{{ request()->routeIs('tools.tax') ? 'menu-active' : '' }} tooltip tooltip-right flex !justify-start gap-3"
                    data-tip="{{ __('Tax') }}"
                    wire:navigate
                >
                    <x-sidebar-nav-icon name="currency-dollar" />
                    <span class="sidebar-nav-label truncate">{{ __('Tax') }}</span>
                </a>
            </li>
            <li>
                <a
                    href="{{ route('settings') }}"
                    class="{{ request()->routeIs('settings') ? 'menu-active' : '' }} tooltip tooltip-right flex !justify-start gap-3"
                    data-tip="{{ __('Settings') }}"
                    wire:navigate
                >
                    <x-sidebar-nav-icon name="cog-6-tooth" />
                    <span class="sidebar-nav-label truncate">{{ __('Settings') }}</span>
                </a>
            </li>
            @if ($currentBudget)
                @can('invite', $currentBudget)
                    <li>
                        <a
                            href="{{ route('budget.team') }}"
                            class="{{ request()->routeIs('budget.team') ? 'menu-active' : '' }} tooltip tooltip-right flex !justify-start gap-3"
                            data-tip="{{ __('Team') }}"
                            wire:navigate
                        >
                            <x-sidebar-nav-icon name="user-group" />
                            <span class="sidebar-nav-label truncate">{{ __('Team') }}</span>
                        </a>
                    </li>
                @endcan
            @endif
        </ul>
    </nav>
</aside>
