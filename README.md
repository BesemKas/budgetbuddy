# Budget Buddy

Budget Buddy is a web application for personal and household money management. You can track accounts in multiple currencies, categorize income and expenses, plan monthly category budgets, import bank files, share selected accounts with a partner, and review activity and long-term trends.

**About this manual.** The `/docs` folder is listed in `.gitignore`, so it is not part of a typical clone of this repository. **This README is the canonical user and operator guide**: usage, how-tos, customization, and troubleshooting. Anything that only existed under `/docs` is not available from Git alone.

This repository is licensed under the MIT License; see the [LICENSE](LICENSE) file.

---

## Table of contents

1. [Core concepts](#core-concepts)
2. [Signing in](#signing-in)
3. [Navigation and budgets](#navigation-and-budgets)
4. [Training manual by screen](#training-manual-by-screen)
5. [How-tos](#how-tos)
6. [Customization and configuration](#customization-and-configuration)
7. [Troubleshooting](#troubleshooting)
8. [Developers and self-hosting](#developers-and-self-hosting)
9. [Security](#security)

---

## Core concepts

- **Budget (workspace)**  
  A budget groups bank accounts, categories, transactions, monthly plans, and snapshots. You might have one budget for yourself and another if you participate in someone else’s budget. The **base currency** is set for each budget (often ZAR) and is used to compare totals across accounts.

- **Bank account**  
  A real or logical account (cheque, savings, credit card, and so on) with a **currency** and optional **exchange rate** to the budget base currency when the currency differs. **Kind** is either liquid cash accounts or credit. Balances move when you add or import transactions.

- **Category**  
  Income and expense labels (defaults plus any you add). Transactions and imports assign spending to categories.

- **Transaction**  
  A dated entry with amount, type (income or expense), account, category, and optional description. Quick Add on the dashboard creates transactions; imports create many at once.

- **Roles on a shared budget**  
  Owners can invite others. Invited users are **viewers** for only the accounts you select; they keep their **own separate personal budget** for their private data.

---

## Signing in

1. Open the app URL your administrator gave you.
2. Go to **Log in** (or the login route). Enter your **email address**.
3. You receive a **one-time code** by email. Codes expire after a short time (see [Customization](#customization-and-configuration) for `OTP_TTL_MINUTES`).
4. Enter the code on the verification screen. If your code expired, request a new one from the login flow.

You stay signed in according to your browser session settings. Use **Log out** in the top bar when you finish on a shared device.

---

## Navigation and budgets

- **Top bar (desktop)**  
  Links: **Dashboard**, **Accounts**, **Categories**, **Activity**, **Planner**, **History**, **Import**, **Tax**, **Settings**, and **Team** (only if you may invite members to the current budget).

- **Small screens**  
  Use the menu button to open the same links.

- **Budget name and switcher**  
  If you belong to **more than one** budget, the budget name opens a dropdown. Choose another budget to switch; the app reloads context for that workspace.

- **Theme**  
  The theme control toggles between light and dark styles (stored in the browser). It does not change your data.

---

## Training manual by screen

### Dashboard

- **Month totals**  
  Income, expenses, and net for the current calendar month use transactions on accounts you can access in the active budget.

- **Privacy blur**  
  Enable blur to hide amounts on the dashboard when someone might read over your shoulder. The choice is remembered for your session.

- **Rolling averages**  
  Typical expense and income averages use recent months (see configuration for rolling windows). **Daily runway** divides available cash by days until **payday** (see Settings). If payday is not set, the app may use the end of the month for that calculation.

- **Category breakdown**  
  Shows how this month’s expenses split across categories.

- **Trend chart**  
  Visual history of income and expenses over several months (span is configurable).

- **Recent transactions**  
  Latest entries; use this to spot-check what you recorded.

- **Quick Add**  
  Opens a short form: account, category, income or expense, amount, date, and optional note. **Smart mode** (under Settings) can require longer notes for large expenses or notes on every transaction.

### Accounts

- Add and edit **bank accounts** tied to the current budget.
- Set **name**, **currency**, and **kind** (liquid vs credit).
- For non-base currencies, set an **exchange rate** (base currency per one unit of account currency) or use **fetch rate** if your server is configured to retrieve rates.
- Editing respects your permissions; viewers cannot change accounts they do not own unless policy allows.

### Categories

- Review **system** categories and add **custom** categories.
- Categories drive reporting and imports; keep names clear so imports map correctly.

### Planner

- Pick **year** and **month** (including previous and next month controls).
- Enter **projected income** for the month where your role allows editing.
- For each **expense category**, set planned amounts, optional **link to a bank account**, and **priority** (needs, wants, savings) where available.
- Use **copy from previous month** when your role allows it to duplicate last month’s plan as a starting point.
- The page may show **liquidity or funding hints** (planned totals vs linked accounts) depending on your version; only users with an appropriate **budget role** can edit monthly plans.

### History

- **Snapshots** summarize past months (income, expense, net) in the budget base currency.
- A **trend chart** uses stored snapshot history up to a configurable number of months.

### Activity

- A chronological **activity log** for the current budget (who did what).

### Import

- Choose the **bank account** the file belongs to (must be one you can access).
- Upload a **CSV** or spreadsheet (size limits apply).
- Select the **format** that matches your file:
  - **Signed amount** (one column with positive and negative amounts),
  - **Separate debit and credit** columns,
  - **FNB**, **Capitec**, or **Standard Bank** layouts where implemented.
- The importer creates transactions and updates balances. Rows that cannot be mapped (for example unknown categories) may be **skipped**; the result message tells you how many rows imported vs skipped.

### Tax (tools)

- A **South African–oriented** gross-to-net style calculator for **indicative** take-home pay. It is **not** professional tax advice; use official SARS guidance for real decisions.

### Settings

- **Payday (day of month, 1–31)**  
  Used with dashboard **runway** and “days until payday.” Leave empty to fall back to month-end behaviour described in the UI.

- **Smart mode**  
  - **Standard** — everyday defaults.  
  - **Survival** — large expenses need a short note (threshold is configurable on the server).  
  - **Zero-based** — every transaction needs a note.  
  - **Travel** — emphasises foreign currency and trip-style spending.

- **Tax calculator** link — same as the Tax page.

- **Delete account and data**  
  Permanently removes your user and budgets where you are the **only** member. You **cannot** delete while you still share a budget with someone else; resolve sharing first.

### Team

- Visible when you may **invite** others to the current budget.
- Enter an **email** and select which **accounts** the invitee may use. They join as a **viewer** on those accounts only.
- Pending invitations can be **cancelled** before they accept.
- Invitations **expire** after a number of days (server-configurable). The invitee opens the link from email to accept.

---

## How-tos

### Record a transaction quickly

1. Open **Dashboard**.  
2. Open **Quick Add**.  
3. Choose account, category, income or expense, amount, and date.  
4. Add a description or note if required by your smart mode.  
5. Save.

### Change the active budget

1. Use the **budget dropdown** next to the logo (only if you have multiple budgets).  
2. Select the other budget.  
3. All screens then use that budget’s accounts and data.

### Invite someone to shared accounts

1. Open **Team** (you must be allowed to invite).  
2. Enter their email and tick the accounts they should see.  
3. Send the invitation.  
4. They accept via the email link before it expires.

### Import a bank export

1. Export a file from your bank in a format this app supports (see **Import** formats).  
2. Open **Import**, pick the account, upload the file, choose the matching format.  
3. Read the success message for imported vs skipped rows.  
4. Fix categories or file layout if many rows were skipped.

### Switch light and dark theme

Use the **theme** control in the navigation bar. Your choice is remembered in the browser.

### Adjust runway and payday behaviour

Set **Payday** under **Settings**. Clear it to use the default described on that page.

---

## Customization and configuration

Operators set these in the environment (`.env`) and, where needed, `config/budgetbuddy.php`. Defaults are safe for local development; production values should be chosen deliberately.

| Variable / topic | Purpose |
| ---------------- | ------- |
| `APP_NAME`, `APP_URL` | Application name and public URL (emails and links). |
| `BUDGET_BASE_CURRENCY` | Default base currency for new budgets (often `ZAR`). |
| `OTP_TTL_MINUTES` | How long login codes remain valid. |
| `BUDGET_INVITATION_TTL_DAYS` | How long team invitations stay valid. |
| `BUDGET_DASHBOARD_CHART_MONTHS` | Months shown on dashboard trend (3–24). |
| `BUDGET_SNAPSHOT_TREND_MONTHS` | Months of snapshot history on History (3–36). |
| `BUDGET_SURVIVAL_EXPENSE_NOTE_THRESHOLD` | In Survival mode, expenses above this amount (in account currency) need a longer note. |
| Database and `mail` | Required for login codes and invitations. Use real SMTP in production (e.g. cPanel). See Laravel’s `config/mail.php`. |
| `SESSION_*`, `QUEUE_*`, `CACHE_*` | Use `database` or `redis` as appropriate for production. |

**Branding.** The app references `public/images/budget-buddy-logo.png` for the favicon and navbar. Replace those assets with your own if you rebrand (keep filenames or update views).

**Rolling averages.** Short and long windows for averages are defined in `config/budgetbuddy.php` under `rolling_average_months` (defaults 3 and 6 months).

---

## Troubleshooting

| Problem | What to check |
| ------- | ------------- |
| No email with login code | Mail settings (`MAIL_*`), spam folder, that the server can send mail (queue worker if mail is queued). |
| Code expired | Request a new code; increase `OTP_TTL_MINUTES` only if policy allows. |
| Import reports many skipped rows | Wrong **format** for your bank file; missing category mapping; file not CSV/XLS/XLSX within size limits. |
| Exchange rate fetch failed | External API or network; enter a **manual rate** on the account. |
| Wrong totals or currency | Confirm each account’s **currency** and **rate** to base; transactions belong to the intended **budget**. |
| Cannot delete my account | You still **share** a budget with someone; leave or transfer ownership per your process, then retry. |
| Team invite link invalid | Invitation **expired**; send a new invite (`BUDGET_INVITATION_TTL_DAYS`). |
| Blank styles or missing scripts | After deploy, run `npm ci` and `npm run build`, or run the Vite dev server during development. |
| 419 / session expired | Refresh the page, log in again; ensure `APP_URL` matches how users open the site and cookies work. |
| Theme resets oddly | Clear site data for the domain; theme uses browser storage. |

For **server errors**, check `storage/logs/laravel.log` (and your HTTP server or PHP logs). For database issues, confirm migrations ran: `php artisan migrate`.

---

## Developers and self-hosting

**Stack:** PHP 8.2+, Laravel 12, Livewire 4, Vite, Tailwind CSS v4, daisyUI 5, Pest. See `composer.json` and `package.json` for packages.

**Setup:**

1. Copy `.env.example` to `.env` and set `APP_KEY`, database, and mail.  
2. `composer install`  
3. `php artisan key:generate`  
4. `php artisan migrate`  
5. `npm install` then `npm run build` (or `npm run dev` while developing)

**Tests:** `php artisan test --compact`  
**PHP formatting:** `vendor/bin/pint --dirty`

**In-app notifications (bell).** Alerts such as over-budget categories and plan checks are stored in the `notifications` table only. They are not sent by email or push from this feature. A queue worker is **not** required for these database notifications. The UI refreshes the bell on a short **Livewire poll** and can show **toasts** when new unread items appear — **no Pusher, Echo, or WebSockets** are required. If you later queue other mail or jobs, run Laravel’s scheduler (`php artisan schedule:run` from cron) and a queue worker as usual.

**Shared budgets (Phase 5, no WebSockets).** Teammates see each other’s spending in **Who spent what** on the planner and in **Activity**. The **dashboard** and **activity** pages **poll periodically** so totals update without a full refresh. The **budget planner** does **not** auto-refresh (that could overwrite unsaved edits); use **Refresh** after a partner changes the plan. Early in the month, **Month check-in** on the dashboard summarizes **last month’s** income/expense/net for accounts you can access. **Sinking fund** rules can include an optional **goal name**, **savings target**, and an approximate **months-to-target** hint from the monthly add.

**Documentation:** Laravel [https://laravel.com/docs](https://laravel.com/docs), Livewire [https://livewire.laravel.com/docs](https://livewire.laravel.com/docs).

---

## Security

If you discover a security issue in **this application’s code**, report it to the maintainers of this repository using their preferred channel (for example a security advisory or private email).

Framework vulnerabilities should follow **Laravel** and **Livewire** disclosure processes.

---

## License

Application code in this repository is released under the [MIT License](LICENSE). Third-party packages remain under their respective licenses.
