# Deploying to production, with no terminal

Everything below is done in the cPanel web interface. Nothing here needs SSH.

Three tools: **cPanel Git Version Control** to deploy the code, the **admin panel** to set itself up, and **phpMyAdmin** to back up first and run four SQL files after.

Each step below says exactly what to click and exactly what you should see. If what you see does not match, stop at that step and tell me.

Allow about twenty minutes, most of it waiting.

---

## Before you start

Your `.env` files are already on the server and are not touched by any of this. The deploy copies files over the top of the live site and never deletes, so `.env` survives.

---

## 1. Back up the database

cPanel, **phpMyAdmin**, select `kidsmone_Prosperminds_website`, **Export** tab, **Go**. Save the file somewhere you can find it.

Do this now, not from the copy you sent me on 4 September. Anything registered since then is only in the live database.

This backup is the whole rollback plan for the data. The rollback for the code is step 9.

---

## 2. Deploy the code

First, the branch has to move. In GitHub, open a pull request from `dev` into `main` and merge it, or use the compare view. This is the one step outside cPanel.

Then in cPanel: **Files, Git Version Control**, find the repository, **Manage**, **Pull or Deploy** tab.

1. **Update from Remote** to fetch the merged `main`.
2. **Deploy HEAD Commit**.

cPanel runs `.cpanel.yml`, which copies `public_html/` over the live document root.

The deploy carries the two `.htaccess` files that lock down the invoice and upload directories, so those are handled. It also removes nothing, which matters in step 8.

---

## 3. Sign in and let the panel set itself up

Go to **prosper-minds.com/admin/login.php** and sign in.

Your username and password have not changed. Neither have Evans's, Shillah's or Lydia's.

Now click **Site health**, at the bottom of the left sidebar under SYSTEM.

That one page does the setup. The new system stores things in twenty tables that do not exist yet on the live database, and opening Site health creates all of them. It takes a second or two and there is nothing to type, import or upload.

**What you should see:** a row at the top of the table reading

> **Database tables** | Fine | All 20 are present.

If it says some could not be created, stop and send me the line. It names them.

The rest of that page is the health report, which you will come back to in step 10.

---

## 4. Run the go-live SQL

In cPanel open **phpMyAdmin**, and on the left click the database **`kidsmone_Prosperminds_website`**. Then click the **SQL** tab at the top.

Open `deploy/2026-09-06-go-live.sql` from the repository, copy all of it, paste it into the big box, and click **Go**.

**What it does.** Four things, and it creates no tables:

- clears the last `FRO` currency row, which is registration 9
- gives Shillah and Lydia the content screens, keeping every right they already had
- fills in the site title, tagline, contact details, LinkedIn and Facebook
- leaves **Cape Town and Mombasa** live and takes **Kuala Lumpur and Bali** off the site

Nothing is deleted by that last one. The rows, the registrations and the invoices all remain, and an event comes back by setting `is_active` to 1.

**One consequence worth knowing before you run it.** Taking an event off the site also makes its page return 404, because a course page stays reachable only while the event is active or its date has already passed, and both of these are still in the future.

Bali has no registrations, so nothing is affected there. **Kuala Lumpur has three registrations totalling USD 2,396**, and those delegates would find a dead page if they returned to the course they paid for. If that is not what you want, delete the line for event 2 in the SQL and Kuala Lumpur stays live. Nothing else in the file is affected.

**What you should see.** phpMyAdmin shows two result tables underneath. The first has four numbers:

| fro_rows_should_be_0 | editors_with_cms_should_be_2 | settings_should_be_15 | live_events_should_be_2 |
|---|---|---|---|
| 0 | 2 | 15 | 2 |

Each column name says what the number should be. If any of them disagrees, stop and send me the row.

The second table lists the four events with an `is_active` column: **1** for Cape Town and Mombasa, **0** for the other two.

Running the file twice is safe and changes nothing the second time.

### Then one button in the admin panel

Back in the admin panel, go to **Settings** in the left sidebar. At the top of the **Site identity** card there is a button, **Fill in the brand details**.

Click it once.

The SQL you just ran already set the text. This button additionally copies the logo and the favicon into the media library, which SQL cannot do because it involves files. Afterwards the Logo and Favicon boxes on that page show the real images on white and on black instead of saying "Nothing set".

Safe to press twice. It never touches the mail settings.

---

## 5. Move the old tables to utf8mb4

Same place: **phpMyAdmin**, the same database, the **SQL** tab.

Open `deploy/2026-09-06-utf8mb4.sql`, paste the whole file, click **Go**.

Every script begins with a `USE` line naming the database, so it does not matter which one is highlighted on the left.

**Why.** Your original tables store text as latin1, an old character set. Everything the new system adds uses utf8mb4, which covers every language. While the old tables stay on latin1, a delegate whose name contains a character latin1 cannot represent loses it silently on the way in. This closes that gap.

**Is it safe.** It rewrites every row, which is why it is separate and why the backup came first. I rehearsed it against your 4 September data: every row of events, registrations, accounts and settings was read back before and after, and the text was identical.

**What you should see.** Two result tables. The first is a single number:

| latin1_tables_should_be_0 |
|---|
| 0 |

The second lists the four events with their dates, which should read normally, for example **19-23 October 2026** with a proper en dash. If a date looks like `19â23 October 2026`, stop and restore the backup from step 1.

If anything went wrong partway, paste `deploy/CHECK-utf8mb4.sql` instead. It changes nothing and reports whether the conversion finished, which tables are still on latin1 if any, and confirms the registration count, delegate count and invoiced total are untouched.

---

## 6. Load the website copy

Same place: **phpMyAdmin**, the **SQL** tab. Paste `deploy/2026-09-06-page-content.sql` and click **Go**.

**Why.** Site health reports *"No content rows. Pages are falling back to their built-in copy."* The tables the new system needs create themselves, but the copy that goes in them ships as migration files, and nothing had run those against the live database.

Nothing is broken while it is missing. Every page carries its own copy as a fallback, which is exactly why the site reads correctly today. What is missing is the **editable** version: search titles show empty on the SEO screen, and there is no stored copy for anyone to change.

**Is it safe.** Every statement is `INSERT IGNORE`, so running it twice adds nothing, and it will never overwrite copy somebody has already edited. Tested both ways.

**What you should see.**

| content_rows_should_be_349 |
|---|
| 349 |

Then a second table listing thirteen pages with their row counts, `sponsorship` being the largest at 60.

Afterwards, Site health's Content layer row turns from **Look** to **Fine**.

---

## 7. Add the seven cohorts that have already run

Same place: **phpMyAdmin**, the **SQL** tab. Paste `deploy/2026-09-06-past-cohorts.sql` and click **Go**.

**Why.** The Past cohorts tab on the calendar is empty, because the only events the main database has ever held are the four still to come. The seven 2026 schools that already ran (Foundations of Foresight through Transparency Engine) were only ever published on the CPD subdomain. This copies them across, word for word, from what was actually advertised for each one.

They are added as finished schools: off the Upcoming tab, listed under Past cohorts newest first, and each with a working page that says *"This cohort has already run"* and points at the current calendar instead of a registration form.

**Is it safe.** `INSERT IGNORE` on fixed ids, so running it twice adds nothing, and it touches no existing row. It is also the reason to run it before you retire the CPD subdomain: after this, the record of those seven lives on the main site.

**What you should see.**

| past_cohorts_should_be_7 | events_should_be_11 |
|---|---|
| 7 | 11 |

Then all eleven events in date order, the seven new ones first.

Prices are deliberately left blank on all seven. The events table defaults its price columns to this year's figures, so filling them in would have quietly advertised USD 599 against a school that finished in January.

None of the seven had a banner designed for it. Rather than leave a hole in the row, the site draws the course's initials in the banner's place, so the archive rows sit at the same height as the live ones.

---

## 8. Delete two things by hand

The deploy copies files over the live site and never deletes, so anything removed from the repository stays on the server until you remove it.

cPanel, **File Manager**, in `public_html`:

- delete `_phase1-preview.php` if it is there
- delete `admin.zip` if it is still there

And if the CPD subdomain still exists, in `cpd.prosper-minds.com` delete `setup_database.php`, `insert_events.php` and `test_email.php`. Two of those accept unauthenticated writes on any request.

You do not have to remember this list. Step 10 tells you what is still there.

---

## 9. If something is wrong

In Git Version Control, deploy the previous commit. The code goes back.

If the data needs to go back too, restore the export from step 1 through phpMyAdmin's Import tab. Nothing in this deployment deletes or rewrites a delegate record, so that should not be necessary.

---

## 10. Check it worked

Open **Site health** again. It runs twelve checks on the spot and sorts the worst first:

- are all twenty tables present
- is the database reachable
- is mail configured, and did anything fail to send in the last seven days
- are registrations still arriving
- are sponsorship enquiries being stored
- **are the invoice PDFs private**
- **can the uploads directory run code**
- are the uploads, invoices and logs directories writable
- is the content layer populated
- are any scheduled pages waiting
- **are any leftover setup scripts still present**
- what PHP version is running, and the largest upload it accepts

The three in bold are the ones that would have caught the problems found during this engagement. If step 8 was done properly, the leftover scripts check reads clean.

Then look at the public site: the homepage, an event page, and the footer, where LinkedIn and Facebook should now appear.

---

## What has not changed

- Every delegate record, invoice number and registration id is untouched.
- All four sign-ins work exactly as before.
- The events, their prices and their early bird dates are as they were.
- SMTP settings are untouched, so mail behaves exactly as it does today.

## Two decisions already folded in

**Early bird applies to new events only.** The four existing courses keep the dates they were advertised with, and the script does not touch their tiers. The Early bird control screen still shows the gap between what a page advertises and what an invoice charges, so it stays visible rather than forgotten.

**Historical event names stay as they were booked.** `event_registrations.event_name` keeps older wordings. That column records what the delegate actually signed up to, and rewriting it would edit history to match a later marketing decision.

## What is still waiting on a decision from you

**Two factor authentication**, deferred by your decision.
