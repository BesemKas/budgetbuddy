<?php

use Livewire\Component;

new class extends Component
{
    public function getUnreadCountProperty(): int
    {
        return auth()->user()->unreadNotifications()->count();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Notifications\DatabaseNotification>
     */
    public function getItemsProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return auth()->user()->notifications()->latest()->limit(20)->get();
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
    }

    public function markRead(string $id): void
    {
        auth()->user()->notifications()->where('id', $id)->first()?->markAsRead();
    }
};
?>

<div class="dropdown dropdown-end" wire:poll.30s>
    <button
        tabindex="0"
        type="button"
        class="btn btn-ghost btn-circle btn-sm min-h-11 min-w-11 indicator"
        aria-label="{{ __('Notifications') }}"
    >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>
        @if ($this->unreadCount > 0)
            <span class="indicator-item badge badge-error badge-xs min-w-[1.1rem] px-1">{{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}</span>
        @endif
    </button>
    <div
        tabindex="0"
        class="dropdown-content z-[60] mt-2 w-[min(100vw-1rem,22rem)] rounded-box border border-base-300/60 bg-base-100 p-0 shadow-lg"
    >
        <div class="flex items-center justify-between gap-2 border-b border-base-300/40 px-3 py-2">
            <span class="text-sm font-semibold">{{ __('Notifications') }}</span>
            @if ($this->unreadCount > 0)
                <button type="button" class="link link-primary text-xs" wire:click="markAllRead">
                    {{ __('Mark all read') }}
                </button>
            @endif
        </div>
        <ul class="max-h-[min(70dvh,24rem)] overflow-y-auto overscroll-contain py-1">
            @forelse ($this->items as $n)
                <li wire:key="notif-{{ $n->id }}">
                    <button
                        type="button"
                        wire:click="markRead('{{ $n->id }}')"
                        class="hover:bg-base-200/80 flex w-full flex-col gap-0.5 px-3 py-2.5 text-left text-sm {{ $n->read_at ? '' : 'bg-primary/5' }}"
                    >
                        <span class="font-medium">{{ $n->data['title'] ?? '' }}</span>
                        <span class="text-base-content/75 text-xs leading-snug">{{ $n->data['message'] ?? '' }}</span>
                        <span class="text-base-content/50 text-[0.65rem]">{{ $n->created_at?->timezone(config('app.timezone'))->diffForHumans() }}</span>
                    </button>
                </li>
            @empty
                <li class="text-base-content/60 px-3 py-6 text-center text-sm">{{ __('No notifications yet.') }}</li>
            @endforelse
        </ul>
        <div class="border-t border-base-300/40 px-3 py-2 text-center">
            <a href="{{ route('budget.planner') }}" class="link link-primary text-xs" wire:navigate>{{ __('Open budget planner') }}</a>
        </div>
    </div>
</div>
