<?php

use App\Models\Budget;
use App\Services\CurrentBudget;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::livewire('/invitations/{token}', 'pages.invitations-accept')->name('budget-invitations.accept');

Route::middleware('guest')->group(function (): void {
    Route::livewire('/login', 'auth.request-otp')->name('login');
    Route::livewire('/login/verify', 'auth.verify-otp')->name('login.verify');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/budget/{budget}/switch', function (Budget $budget) {
        Gate::authorize('view', $budget);
        app(CurrentBudget::class)->switchTo($budget, auth()->user());

        return back();
    })->name('budget.switch');
});

Route::middleware(['auth', 'budget'])->group(function (): void {
    Route::livewire('/dashboard', 'pages.dashboard')->name('dashboard');
    Route::livewire('/accounts', 'accounts.index')->name('accounts.index');
    Route::livewire('/categories', 'categories.index')->name('categories.index');
    Route::livewire('/budget/team', 'pages.budget-team')->name('budget.team');
    Route::livewire('/budget/activity', 'pages.budget-activity')->name('budget.activity');
    Route::livewire('/budget/history', 'pages.budget-history')->name('budget.history');
    Route::livewire('/import', 'pages.transaction-import')->name('transactions.import');
    Route::livewire('/settings', 'pages.settings')->name('settings');
});

Route::post('/logout', function () {
    auth()->logout();

    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('home');
})->middleware('auth')->name('logout');
