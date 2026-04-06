# **Budget Buddy: Phased Implementation Plan**

This plan breaks down the development of the Laravel 11 / PHP 8.2 application into logical sprints.

## **Phase 1: Foundation & Package Installation**

*Goal: Get the environment ready and the "Zero-Cost" auth working.*

1. **Project Initialization:**  
   * laravel new budget-buddy  
   * Configure .env for MySQL and cPanel SMTP (for free Email OTP).  
2. **Frontend Setup:**  
   * Install Tailwind CSS v4 and daisyUI v5.  
   * Install Livewire 3\.  
   * composer require lucide-laravel for icons.  
3. **Authentication & Permissions:**  
   * composer require spatie/laravel-permission.  
   * Implement **Custom OTP Logic**:  
     * Create otp\_codes table or add fields to users.  
     * Create OTPService to generate and mail 6-digit codes.  
     * Build Livewire components for "Enter Email" and "Verify OTP".  
4. **Core Models & Migrations:**  
   * Create migrations for bank\_accounts, categories, and transactions.  
   * Ensure currency\_code and exchange\_rate fields are present.

## **Phase 2: The Core Ledger (Single User)**

*Goal: Manual entry and basic multi-currency calculation.*

1. **Account Management:**  
   * CRUD for Bank Accounts (ZAR and Foreign).  
   * Logic to fetch/set exchange rates (Free API or manual).  
2. **Category Manager:**  
   * System defaults (Seeder) \+ User-defined custom categories.  
3. **Transaction Engine:**  
   * "Quick-Add" Livewire modal.  
   * **Observer:** Auto-update account balance when a transaction is saved.  
   * **Currency Service:** Calculate "Base Currency" totals for the dashboard.  
4. **The Dashboard:**  
   * Simple "Surplus" view (Income vs Expenses).  
   * Privacy Toggle (Blur effect using Tailwind classes).

## **Phase 3: The "Buddy" System (Joint Accounts)**

*Goal: Collaboration and Shared Access.*

1. **Invitation System:**  
   * Create invitations table.  
   * Logic to generate secure tokens and email partners.  
2. **Shared Access Logic:**  
   * Use **Spatie Roles** to define 'Owner' vs 'Viewer'.  
   * Middleware to ensure users only see budgets they own or are invited to.  
3. **Collaborative UI:**  
   * "Who spent what" badges on shared transactions.  
   * Activity feed using spatie/laravel-activitylog.

## **Phase 4: Data Engine & Analytics**

*Goal: Smart features and AI-Readiness.*

1. **Bank Import (Free):**  
   * Use maatwebsite/excel to build CSV parsers for FNB, Standard Bank, Capitec, etc.  
2. **The BudgetService:**  
   * Implement rolling 3-month/6-month averages.  
   * Logic for "Burn Rate" (Available Cash / Days until Payday).  
3. **Historical Snapshots:**  
   * Scheduled task to take a JSON "Snapshot" of the month-end totals.  
4. **Visualizations:**  
   * Integrate **ApexCharts** for trend lines and category donuts.

## **Phase 5: Smart Modes & cPanel Deployment**

*Goal: Specialized logic and going live.*

1. **Smart Mode Middleware:**  
   * Toggle UI/Validation based on user preference (Survival, Zero-Based, Travel).  
2. **SARS Tax Calculator:**  
   * Simple utility to estimate net pay from gross based on current brackets.  
3. **cPanel Hardening:**  
   * Script to move public to public\_html.  
   * Setup Cron Jobs for scheduled tasks.  
   * Final POPIA compliance check (Data deletion/Anonymization).

### **Package Installation Commands (Reference)**

\# Framework & Auth  
composer require spatie/laravel-permission spatie/laravel-activitylog  
composer require akaunting/laravel-money

\# Data & Exports  
composer require maatwebsite/excel

\# Frontend  
npm install \-D tailwindcss@next @tailwindcss/vite daisyui@latest  
composer require livewire/livewire  
composer require lucide-laravel  
