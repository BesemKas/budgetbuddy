# Phase-by-Phase Build Plan: Advanced Budgeting & Account Linking

This document outlines the execution strategy to evolve Budget Buddy from a tracker into a proactive financial co-pilot. It is aligned with the **existing domain model**: a `Budget` record is the workspace (personal or shared team); monthly targets and income are scoped with **`budget_id`**, not a separate “joint_budget” table.

---

## Architectural decisions (locked)

### Budget grain

- **Structure:** One row per `(budget_id, category_id, year, month)` in the **`category_month_budgets`** table (monthly category targets: amount, optional account link, priority).
- **Joint logic:** Shared households use the same **`budgets`** row (team budget). Both partners read/write rows where **`budget_id`** matches that workspace, so **Remaining** stays identical in real time.
- **Naming note:** The codebase already uses the `budgets` table for workspaces. Monthly category targets live in **`category_month_budgets`**; month-level **projected income** lives in **`budget_month_summaries`**.
- **Copy previous month:** A bulk command duplicates `amount`, `category_id`, and linked `bank_account_id` / priority into a new `(year, month)` for the same **`budget_id`**.

### Income (zero-based)

- **Source of truth:** Manual **projected income** per budget per month, stored in **`budget_month_summaries`** (`projected_income`).
- **Evolution:** Later optional toggle: average of the last three months; zero-based planning still relies on an explicit “Rands assigned” figure at month start.

### Liquidity vs category limits (Phase 3)

- **`checkBudgetLiquidity()`:** Sum **only liquid** accounts (cheque, savings, cash). Rule: **total budgeted ≤ sum of liquid balances** (in base currency).
- **Credit:** Credit card balances are **debt**, not liquidity. If a category is linked to a **credit** account, show a **debt warning**, not a funding-confirmation state.

### Echo / realtime timing

- Implement **thin Phase 3** first: **`BudgetExceeded`** and **low-liquidity** calculators and when to fire events.
- Add **Pusher + notification UI** after the backend knows when to alert. Avoid a hollow Echo setup that forces duplicate logic later.

### Hosting: Pusher (not Reverb)

- Use **Pusher** (free tier): cPanel often terminates long-lived WebSocket processes (e.g. Reverb). Pusher runs sockets off-site; Laravel only needs a reliable **`queue:work`** (e.g. cron every minute with appropriate timeout).

---

## Phase 1: Data architecture & migration

**Goal:** MySQL schema for monthly targets, month summaries, and relationships.

- **Migrations**
  - **`budget_month_summaries`:** `budget_id`, `year`, `month`, `projected_income`; unique `(budget_id, year, month)`.
  - **`category_month_budgets`:** `budget_id`, `category_id`, `year`, `month`, `amount`, nullable `bank_account_id`, `priority` (needs / wants / savings); unique `(budget_id, category_id, year, month)`.
- **Models & relationships**
  - `Budget` hasMany `BudgetMonthSummary`, hasMany `CategoryMonthBudget`.
  - `Category` hasMany `CategoryMonthBudget`.
  - `BankAccount` hasMany `CategoryMonthBudget` (linked spend account per category/month).
- **Seeding:** Sample rows for existing budgets/categories to unblock UI work.

---

## Phase 2: Budget Planner UI

**Goal:** Set monthly targets and link accounts per category.

- **Livewire `BudgetPlanner`:** Grid of categories for the selected budget and month; inline amount; per-row **bank account** selector; **Copy from previous month**; priority toggles (Needs / Wants / Savings).

---

## Phase 3: Reality-check engine (thin first)

**Goal:** Plan vs actual and alert hooks.

- **`BudgetService` (or dedicated service):**
  - `checkBudgetLiquidity()` — liquid accounts only vs total budgeted.
  - `getVelocity($categoryId)` — spend rate vs days elapsed (optional in first thin slice).
- **Funding gap / unfunded** detection; **debt warning** when linked account is non-liquid.
- **Validation:** Total assigned ≤ `projected_income` for zero-based planning.
- **Events:** Emit domain events (or queued notifications) for exceed / low liquidity **before** building the full Echo UI.

---

## Phase 4: Real-time notifications (Pusher + Echo client)

**Goal:** In-app alerts and history.

- Broadcasting driver: **Pusher**; Laravel Echo + `pusher-js` on the frontend.
- **Notification center** (e.g. Livewire in nav): dropdown or slide-over; toasts on new events.
- **Triggers:** `BudgetExceeded`, `LiquidityAlert` (and later joint-channel variants).

---

## Phase 5: Joint accountability & automation

**Goal:** Collaboration and month-end habits.

- **Joint sync:** Private channels keyed by `budget_id` so partners see updates immediately; “who spent what” in the budget view.
- **Month-end sweep:** Prompt on the 1st to reallocate surplus; sinking-fund automation for annual goals.
- **cPanel:** Cron for queue worker, scheduled tasks (e.g. exchange rates) as needed.

---

## Implementation order (recommended)

1. **Phases 1–2** — persist targets and projected income; ship the planner.
2. **Thin Phase 3** — calculators + event boundaries.
3. **Phase 4** — Pusher + notification pane + toasts.
4. **Phase 5** — joint polish and automation.

---

## Implementation priority tip

After **Phase 1 & 2**, prioritize **thin Phase 3** so notifications are truthful; then **Phase 4** for the realtime “wow” without rework.
