<?php

use App\Enums\LedgerEntryType;
use App\Models\Category;
use App\Services\CurrentBudget;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component
{
    public string $newName = '';

    public string $newType = 'expense';

    public function saveCustom(CurrentBudget $currentBudget): void
    {
        $this->authorize('create', Category::class);

        $this->validate([
            'newName' => ['required', 'string', 'max:120'],
            'newType' => ['required', 'in:income,expense'],
        ]);

        $user = auth()->user();
        $budget = $currentBudget->current();
        $type = LedgerEntryType::from($this->newType);

        $exists = Category::query()
            ->where('budget_id', $budget->id)
            ->where('type', $type)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($this->newName)])
            ->exists();

        if ($exists) {
            $this->addError('newName', __('You already have a category with this name and type.'));

            return;
        }

        Category::query()->create([
            'user_id' => $user->id,
            'budget_id' => $budget->id,
            'name' => $this->newName,
            'type' => $type,
            'is_system' => false,
        ]);

        $this->reset('newName');
        $this->resetValidation();
    }

    public function deleteCustom(int $id, CurrentBudget $currentBudget): void
    {
        $budgetId = $currentBudget->current()->id;
        $category = Category::query()->where('budget_id', $budgetId)->findOrFail($id);
        $this->authorize('delete', $category);

        if ($category->transactions()->exists()) {
            $this->addError('category_delete', __('Remove or reassign transactions using this category first.'));

            return;
        }

        $category->delete();
    }

    public function systemCategories(): Collection
    {
        return Category::query()
            ->whereNull('user_id')
            ->where('is_system', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get();
    }

    public function customCategories(): Collection
    {
        $budgetId = app(CurrentBudget::class)->current()->id;

        return Category::query()
            ->where('budget_id', $budgetId)
            ->where('is_system', false)
            ->orderBy('type')
            ->orderBy('name')
            ->get();
    }
};
?>

<div class="mx-auto max-w-5xl px-4 py-6">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ __('Categories') }}</h1>
        <p class="text-base-content/70 mt-1 text-sm">{{ __('System defaults plus your own labels for transactions.') }}</p>
    </div>

    <div class="card bg-base-100 mt-6 border border-base-300/60 shadow-sm">
        <div class="card-body gap-2">
            <h2 class="card-title text-lg">{{ __('Your categories') }}</h2>
            @error('category_delete')
                <div role="alert" class="alert alert-warning alert-soft text-sm">{{ $message }}</div>
            @enderror
            @can('create', Category::class)
            <form wire:submit="saveCustom" class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:items-end">
                <label class="form-control sm:col-span-1">
                    <span class="label-text">{{ __('Name') }}</span>
                    <input type="text" class="input input-bordered w-full" wire:model="newName" />
                    @error('newName')
                        <span class="label-text-alt text-error">{{ $message }}</span>
                    @enderror
                </label>
                <label class="form-control sm:col-span-1">
                    <span class="label-text">{{ __('Type') }}</span>
                    <select class="select select-bordered w-full" wire:model="newType">
                        <option value="income">{{ __('Income') }}</option>
                        <option value="expense">{{ __('Expense') }}</option>
                    </select>
                </label>
                <button type="submit" class="btn btn-primary btn-sm sm:col-span-1">{{ __('Add') }}</button>
            </form>
            @else
                <p class="text-base-content/70 text-sm">{{ __('Only budget owners can add or remove custom categories.') }}</p>
            @endcan

            <div class="overflow-x-auto mt-4">
                <table class="table table-zebra">
                    <thead>
                        <tr>
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->customCategories() as $cat)
                            <tr wire:key="c-{{ $cat->id }}">
                                <td>{{ $cat->name }}</td>
                                <td>
                                    <span @class(['badge badge-sm', 'badge-success' => $cat->type === \App\Enums\LedgerEntryType::Income, 'badge-error' => $cat->type === \App\Enums\LedgerEntryType::Expense])>
                                        {{ $cat->type->value }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    @can('delete', $cat)
                                        <button
                                            type="button"
                                            class="btn btn-ghost btn-xs text-error"
                                            wire:click="deleteCustom({{ $cat->id }})"
                                            wire:confirm="{{ __('Delete this category?') }}"
                                        >
                                            {{ __('Delete') }}
                                        </button>
                                    @else
                                        <span class="text-base-content/50 text-xs">—</span>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="text-base-content/60">{{ __('No custom categories yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card bg-base-100 mt-6 border border-base-300/60 shadow-sm">
        <div class="card-body gap-2">
            <h2 class="card-title text-lg">{{ __('System defaults') }}</h2>
            <p class="text-sm text-base-content/70">{{ __('Available to everyone. Add transactions under these in Quick add.') }}</p>
            <div class="flex flex-wrap gap-2 mt-2">
                @foreach ($this->systemCategories() as $cat)
                    <span @class(['badge badge-lg badge-outline', 'badge-success' => $cat->type === \App\Enums\LedgerEntryType::Income, 'badge-error' => $cat->type === \App\Enums\LedgerEntryType::Expense])>
                        {{ $cat->name }}
                    </span>
                @endforeach
            </div>
        </div>
    </div>
</div>
