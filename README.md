# Budget Buddy

Budget Buddy is a web application for personal and household money management: accounts, categories, transactions, and shared access with a partner. The long-term direction is to move from simple tracking toward proactive budgeting, liquidity checks, and timely feedback when plans and spending diverge.

This repository is licensed under the MIT License; see the [LICENSE](LICENSE) file.

---

## For users

**What you can use it for**

- Track bank accounts (including multiple currencies) and keep balances in step with your transactions.
- Organize spending with categories (defaults plus your own).
- Record income and expenses quickly and see summaries on the dashboard.
- Work with someone else on the same budget using invitations and roles (for example, owner versus viewer), with an activity history for shared spaces.

**Privacy and comfort**

- The app supports a privacy-oriented view on the dashboard (for example, blurring sensitive figures) so you can use it in shared spaces more comfortably.

**Where the product is headed**

Detailed plans describe richer budgeting (monthly targets per category, linking budgets to accounts, copy-from-previous-month), checks that compare your plan to real balances and spending pace, optional real-time alerts when limits or liquidity are at risk, and more automation around month-end and joint use. Those ideas are spelled out in the documents linked under [Roadmap and planning documents](#roadmap-and-planning-documents) below.

---

## For developers

**Stack (this repo)**

- PHP 8.2+, Laravel 12
- Livewire 4, Vite, Tailwind CSS v4, daisyUI 5
- Pest for tests; Laravel Pint for code style
- Packages in use include Spatie Permission, Spatie Activity Log, Maatwebsite Excel, and Blade Lucide icons (see `composer.json` and `package.json`).

**Prerequisites**

- PHP 8.2+, Composer, Node.js and npm, and a database supported by Laravel (for example MySQL).

**Getting started**

1. Clone the repository and copy `.env.example` to `.env`.
2. Run `composer install` and `php artisan key:generate`.
3. Configure your database and mail settings in `.env` (the project was designed with MySQL and SMTP-friendly hosting in mind).
4. Run migrations: `php artisan migrate`.
5. Install frontend dependencies and build assets: `npm install`, then `npm run build` (or `npm run dev` while developing).

**Tests**

- Run the test suite with: `php artisan test --compact`
- Prefer targeted runs while iterating, for example: `php artisan test --compact --filter=YourTestName`

**Formatting**

- Format changed PHP code with Pint: `vendor/bin/pint --dirty`

**Further reading**

- Laravel: [https://laravel.com/docs](https://laravel.com/docs)
- Livewire: [https://livewire.laravel.com/docs](https://livewire.laravel.com/docs)

---

## Roadmap and planning documents

Implementation is described in two complementary tracks:

| Document | Focus |
| -------- | ----- |
| [Budget Buddy Build Plan](docs/Budget%20Buddy%20Build%20Plan.md) | Foundation through deployment: auth, ledger, joint accounts, imports and analytics, smart modes, and hosting hardening. |
| [Budget Feature Phases](docs/Budget%20Feature%20Phases.md) | Advanced budgeting and account linking: schema and UI for budgets, “reality check” logic, real-time notifications, and joint accountability. |

**Build Plan (high level)**

1. Foundation and packages: environment, Tailwind and daisyUI, Livewire, OTP-style email auth, core migrations for accounts, categories, and transactions.
2. Core ledger: account and category management, transactions with balance updates, multi-currency handling, dashboard.
3. Buddy system: invitations, roles, collaborative UI, activity log.
4. Data and analytics: bank CSV import patterns, budget and burn-rate style services, snapshots, charts.
5. Smart modes, tax helpers, and production deployment notes (including cron and compliance-oriented checks).

**Budget feature phases (high level)**

1. Data architecture: budgets linked to categories, accounts, and users; seed data for testing.
2. Budget Planner UI: grid of categories, inline amounts, account linking, copy from previous month, priority markers.
3. Logic layer: liquidity checks, spending velocity, funding gaps, zero-based style validation.
4. Real-time notifications: broadcasting (for example Reverb or Pusher), notification UI, events for overages and liquidity.
5. Joint workflows and automation: shared channels, month-end prompts, sinking-fund style automation, scheduled tasks for rates and queues.

The two documents sometimes suggest different ordering (for example, prioritizing notification infrastructure versus deeper analytics). Use them together and adjust sequencing to match release goals.

---

## Security

If you discover a security issue in **this application’s code**, report it to the maintainers of this repository using the contact method they prefer (for example an issue template or private security advisory).

Vulnerabilities in upstream frameworks (Laravel, Livewire, and so on) should follow each project’s own disclosure process.

---

## License

Application code in this repository is released under the [MIT License](LICENSE).

The Laravel framework and other third-party packages remain under their respective licenses.
