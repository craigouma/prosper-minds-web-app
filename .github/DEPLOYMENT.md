# Deployment and rollback

This is your operations note, not the client's. The client's first-deployment
steps live in `deploy/README.md`. This file covers the automated pipeline and
the rollback, both of which run from **your** GitHub account.

## What exists

Two workflows, both driving cPanel's Git Version Control over its API (no SSH):

| Workflow | File | Trigger | Deploys |
|---|---|---|---|
| Deploy to production | `.github/workflows/deploy.yml` | push to `main` (or manual) | `main` -> live site |
| Roll back to the original site | `.github/workflows/rollback.yml` | manual only, typed confirmation | `rollback` -> the 2026-08-19 original |

Both call one shared composite action, `.github/actions/cpanel-deploy`, which
makes three documented cPanel UAPI calls: pull the branch, run its `.cpanel.yml`,
poll until done. Deploy and rollback differ only by the branch name, so they
cannot drift apart.

The `rollback` branch holds the original site as pulled from production on
2026-08-19 (`public_html/` from commit `08a6f09`, `cpd.prosper-minds.com/` from
`1d2d1df`) plus its own `.cpanel.yml` that restores it safely (below).

## Why the pipeline lives on GitHub

The source and the automation are in your account
(`git@github.com:craigouma/prosper-minds-web-app.git`). A changed cPanel
password logs you out of *their* server; it does not touch your repository, your
history, or these workflows. Every commit of the finished work is already on
`origin/main`. Losing cPanel access cannot cost you the code.

What a lockout *does* stop is deploying and rolling back, because both need the
cPanel token to reach their server. So the window for anything that has to run
on their server is *while you still have access*. See "If you are about to lose
access".

## One-time setup

1. **cPanel Git repo.** In cPanel > Files > Git Version Control, confirm the
   repository cloned from GitHub exists, its deployment branch is `main`, and note
   its path on the server, e.g. `/home/<acct>/repositories/prosper-minds-web-app`.
   That path is the `repository_root`.
2. **cPanel API token.** cPanel > Security > Manage API Tokens > Create. Give it
   a name like `github-deploy`. Copy the token once; you cannot see it again.
3. **GitHub secrets.** Repo > Settings > Secrets and variables > Actions > New
   repository secret, four of them:
   - `CPANEL_HOST` - e.g. `https://server-hostname` (the cPanel server URL, with
     `https://`, no port). If unsure, it is the host in your cPanel login URL.
   - `CPANEL_USERNAME` - the cPanel account username that created the token.
   - `CPANEL_API_TOKEN` - the token from step 2.
   - `CPANEL_REPOSITORY_ROOT` - the path from step 1.
4. **`.env` on the server.** Already present at `public_html/.env`. The pipeline
   never writes it; deploys and rollbacks both leave it in place.
5. **Push the branches.** `main` (with these workflows) and the `rollback`
   branch both need to be on GitHub. See "Publishing this".

## Normal deploys

Merge or push to `main`. The Deploy workflow pulls `main` into the server clone
and runs the root `.cpanel.yml`, which copies `public_html/` and
`cpd.prosper-minds.com/` into their document roots. Watch it in the repo's
Actions tab. If a rollback had previously left the clone on the `rollback`
branch, this switches it back to `main`, so a normal deploy always supersedes a
rollback.

## Rollback

Run it from Actions > "Roll back to the original site" > Run workflow. It asks
for two things:

- **confirm** - you must type `ROLLBACK TO ORIGINAL` exactly, or it stops.
- **reason** - recorded in the run log (e.g. the unpaid invoice and the date you
  gave notice). This is your audit trail; fill it in honestly.

What the rollback's `.cpanel.yml` does, in order:

1. **Snapshots the entire live site** (both document roots) to
   `$HOME/pre-rollback-<timestamp>/` on the server. This is the one-command undo
   and means nothing is ever destroyed.
2. Clears the live document roots (the snapshot holds every byte).
3. Restores the original 2026-08-19 code.
4. Puts `.env`, `storage/`, `assets/invoices/` and `assets/uploads/` back from
   the snapshot, so no delegate invoice, uploaded file, log, or credential is
   lost.

**The database is never touched by any of this.** Registrations, payments and
subscribers are the client's and their delegates' data, not part of the code you
are withdrawing. Removing it would harm third parties and is not yours to remove.

There is a few seconds of downtime between step 2 and step 3. That is expected
for a deliberate rollback.

### Caveat: original DB credentials

The original site read its database credentials from hardcoded values, not from
`.env`. If the client has changed the database password since 2026-08-19, the
restored original will not connect and will show a database error. There is no
way around that from here; it would need the original's credentials updated. In
the common case (password unchanged) it just works.

### Undoing a rollback

Either run "Deploy to production" (restores the full current site from `main`),
or on the server copy `$HOME/pre-rollback-<timestamp>/` back over the document
roots. The snapshot is why the rollback is safe to run.

## If you are about to lose access

The code is already safe on GitHub, so the only thing a lockout costs you is the
ability to run these workflows. Before that happens:

- Confirm `main` and `rollback` are both pushed (`git branch -r`).
- Consider tagging the finished state so it is easy to point to later
  (`git tag -a v1.0-complete -m "Completed work" main && git push origin v1.0-complete`).
- A rollback, if you intend one, has to run while the token still works. Do it
  deliberately, after written notice, not as an automatic trigger. That
  distinction is what keeps this a contractual withdrawal of unpaid work rather
  than something that can be characterised as interfering with a live system.

None of this is legal advice. Make sure the "code remains mine until paid in
full" term is actually in your signed agreement; that clause is what makes the
rollback a remedy you are entitled to.

## Publishing this

These files were prepared on the `ci/cpanel-pipeline` branch and the `rollback`
branch was created locally. To put them live:

```
# the pipeline (review, then merge to main; do NOT deploy until secrets are set)
git push origin ci/cpanel-pipeline
# open a PR ci/cpanel-pipeline -> main, or fast-forward main yourself

# the original-site branch used by the rollback workflow
git push origin rollback
```

The Deploy workflow will fire on the first push to `main` that includes it, so
add the four secrets *before* merging, or the run will fail harmlessly (it
cannot reach cPanel without them and changes nothing).
