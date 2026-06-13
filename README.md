# ProjectVista

ProjectVista is a premium client-experience portal for luxury outdoor construction companies. The current demo centers on Omni Pool Builders and the Smith Residence pool and outdoor living project in Scottsdale, Arizona.

## Stack

- Laravel 13, PHP 8.3+, Breeze auth, Sanctum, policies, feature tests
- Laravel Sail with MySQL 8.4 and Redis
- Inertia 2, React 18, TypeScript, Ziggy routes
- Tailwind CSS v4, Vite 8, Lucide React

## Local Setup

Use Sail for project commands once Composer dependencies are installed.

```bash
cp .env.example .env
composer install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate:fresh --seed
./vendor/bin/sail artisan storage:link
./vendor/bin/sail npm install
./vendor/bin/sail npm run dev
```

Open the app at http://localhost:8088. Vite runs on http://localhost:5178. The forwarded local MySQL and Redis ports are `33088` and `6388`.

If Composer is not available locally yet, install PHP dependencies with the official Composer container first:

```bash
docker run --rm -u "$(id -u):$(id -g)" -v "$PWD:/app" -w /app composer:latest composer install --ignore-platform-reqs
```

## Demo Accounts

All seeded demo users use the password `password`.

| Role | Email | Notes |
| --- | --- | --- |
| Super Admin | `super@projectvista.test` | Platform command center and cross-company access |
| Company Admin | `admin@omnipools.test` | Omni Pool Builders owner/admin view |
| Project Manager | `manager@omnipools.test` | Smith Residence project manager |
| Client | `client@omnipools.test` | Homeowner portal for Smith Residence |
| Subcontractor | `sub@omnipools.test` | Limited tile subcontractor access |
| Other Company Admin | `admin@desertstone.test` | Desert Stone Works isolation data |

## Product Areas

- Role-aware dashboard for platform, company, manager, client, and subcontractor users
- Branded company admin area with team, project, and invitation management
- Project portal covering timeline, selections, approvals, payments, documents, and messages
- Visibility rules for clients and subcontractors enforced through policies and feature tests
- Seeded demo media under `storage/app/public/demo`

## Daily Commands

```bash
./vendor/bin/sail artisan test
./vendor/bin/sail npm run lint
./vendor/bin/sail npm run format
./vendor/bin/sail npm run build
./vendor/bin/sail artisan route:list
```

Use `./vendor/bin/sail artisan migrate:fresh --seed` when you need to reset the demo data.

## Repository Notes

- Commit `.env.example`, but never commit `.env` or real credentials.
- Commit the demo files in `storage/app/public/demo`; they are small and the seeder references them.
- Do not commit generated output such as `vendor`, `node_modules`, `public/build`, `public/hot`, `public/storage`, framework cache files, compiled views, logs, or local SQLite databases.
- Run `./vendor/bin/sail artisan storage:link` after cloning so `asset('storage/...')` demo URLs resolve.
- Project-specific agent guidance lives in `AGENTS.md`.

## First Commit Checklist

```bash
git status --short --ignored
git add .
git status --short
git diff --cached --stat
```

Before opening a pull request or sharing the repo, run the most relevant checks for the change. For a general baseline, use:

```bash
./vendor/bin/sail artisan test
./vendor/bin/sail npm run build
```
