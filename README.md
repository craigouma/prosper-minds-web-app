# ProsperMinds Website

Source for both ProsperMinds properties, kept in one repository:

| Path | Serves | Stack |
|---|---|---|
| `public_html/` | [prosper-minds.com](https://prosper-minds.com) — main site | Custom PHP, MySQL/MariaDB, no framework |
| `cpd.prosper-minds.com/` | [cpd.prosper-minds.com](https://cpd.prosper-minds.com) — CPD calendar subdomain | Custom PHP, MySQL/MariaDB, no framework |

Each folder is deployed to its own cPanel document root — see **Deployment** below. They are independent PHP applications with separate databases; they share no code.

## Branches

- **`main`** — what should match production. Deploy from here.
- **`dev`** — active work. Test locally, open a PR into `main`, merge only after review.

Nothing merges to `main` without being verified against a local copy first (see below) — both sites touch live registrations and billing.

## Local development

Docker is required. From the repository root:

```bash
docker compose up -d
php -S 127.0.0.1:8080 -t public_html
php -S 127.0.0.1:8081 -t cpd.prosper-minds.com
```

This starts a MariaDB container per site (seeded from a local dump — ask in the team channel for the current one, dumps are never committed) plus [Mailpit](https://github.com/axllent/mailpit) as a local mail catcher at `http://127.0.0.1:8025`, so nothing sent while developing ever reaches a real inbox.

Copy `.env.example` to `.env` inside each site folder and fill in local values before running either site — see that file for what each variable does. `local-dev/verify.sh` runs the full local acceptance check (vendor integrity, CSRF, credential resolution, the registration-response fix, currency parsing) against a freshly seeded stack.

## Environment variables (required in every environment, including production)

Both sites read their database credentials from the environment — there is no hardcoded fallback. Set real environment variables (cPanel → **Environment Variables** for the domain) or place a `.env` file in the site's own folder; see `.env.example` in each folder for the full list. **Deploying without these set takes the site down** — every page returns "Site configuration is incomplete" until they exist.

## Deployment

Both sites deploy via cPanel's **Git™ Version Control** feature, using this repository as the remote and a `.cpanel.yml` deployment task to copy each folder into its real document root — no SSH/terminal access is required; deployment runs from the cPanel UI's "Deploy HEAD Commit" button. See `.cpanel.yml` at the repository root.

## What's excluded from this repository

- Database dumps and any `.sql` file — never committed.
- `.env` files — gitignored in each site folder; only `.env.example` (placeholder values) is tracked.
- Working/planning documents (engagement notes, discovery findings, internal reports) — kept locally, not committed here.
