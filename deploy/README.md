# Deploying to production, with no terminal

Everything below is done in the cPanel web interface. Nothing here needs SSH.

Three tools, in this order: **phpMyAdmin** to take a backup, **Git Version Control** to deploy the code, **phpMyAdmin** again to run two files, then the admin panel to check.

Allow about twenty minutes, most of it waiting.

---

## Before you start

Your `.env` files are already on the server and are not touched by any of this. The deploy copies files over the top of the live site and never deletes, so `.env` survives.

---

## 1. Back up the database

cPanel, **phpMyAdmin**, select `kidsmone_Prosperminds_website`, **Export** tab, **Go**. Save the file somewhere you can find it.

Do this now, not from the copy you sent me on 4 September. Anything registered since then is only in the live database.

This backup is the whole rollback plan for the data. The rollback for the code is step 7.

---

## 2. Deploy the code

First, the branch has to move. In GitHub, open a pull request from `dev` into `main` and merge it, or use the compare view. This is the one step outside cPanel.

Then in cPanel: **Files, Git Version Control**, find the repository, **Manage**, **Pull or Deploy** tab.

1. **Update from Remote** to fetch the merged `main`.
2. **Deploy HEAD Commit**.

cPanel runs `.cpanel.yml`, which copies `public_html/` over the live document root.

The deploy carries the two `.htaccess` files that lock down the invoice and upload directories, so those are handled. It also removes nothing, which matters in step 5.

---

## 3. Wake the new tables up

Open the admin panel and sign in: **prosper-minds.com/admin/login.php**

Your username and password are unchanged. So are Evans's, Shillah's and Lydia's.

Signing in creates the audit log table. Then click through **Pages, Media library, Menus, Submissions, Delegate reviews and Trash** once each. Each screen creates its own tables the first time it loads. Nothing to type, nothing to import.

If a screen shows an error, stop and tell me before going further.

---

## 4. Run the go-live SQL

phpMyAdmin, `kidsmone_Prosperminds_website`, **SQL** tab. Open `deploy/2026-09-06-go-live.sql` from the repository, paste the whole thing in, and press **Go**.

It does four things:

- clears the last `FRO` currency row, which is registration 9
- gives Shillah and Lydia access to the content screens, keeping everything they already had
- fills in the site title, tagline, contact details, LinkedIn and Facebook
- leaves only Cape Town and Mombasa live, and takes Kuala Lumpur and Bali off the site

Nothing is deleted by that last one. The two retired events keep their rows, their registrations and their past-cohort pages, and come back by setting `is_active` to 1.

It creates no tables and touches no mail settings. It is safe to run twice.

It then prints four numbers, which should read exactly **0**, **2**, **15** and **2**, followed by the four events so you can see which two are live.

Then, in the admin panel, go to **Settings** and press **Fill in the brand details**. That is the same job as the SQL for the text, and it additionally puts the logo and favicon into the media library, which SQL cannot do. Safe to press twice.

---

## 5. Move the old tables to utf8mb4

Same place, phpMyAdmin, SQL tab. Paste `deploy/2026-09-06-utf8mb4.sql` and press **Go**.

The older tables are still on latin1 while everything new is utf8mb4. That gap is why a delegate whose name contains a character latin1 cannot hold would lose it silently on the way in.

This one rewrites every row, so it is a separate file and it comes after the backup. It has been rehearsed against a full copy of your data: every row of events, registrations, accounts and settings was read back before and after, and the text was byte for byte identical.

It prints two things. The first should be **0** tables left on latin1. The second lists the four event dates, which should read normally with their en dashes intact.

---

## 6. Delete two things by hand

The deploy copies files over the live site and never deletes, so anything removed from the repository stays on the server until you remove it.

cPanel, **File Manager**, in `public_html`:

- delete `_phase1-preview.php` if it is there
- delete `admin.zip` if it is still there

And if the CPD subdomain still exists, in `cpd.prosper-minds.com` delete `setup_database.php`, `insert_events.php` and `test_email.php`. Two of those accept unauthenticated writes on any request.

You do not have to remember this list. Step 8 tells you what is still there.

---

## 7. If something is wrong

In Git Version Control, deploy the previous commit. The code goes back.

If the data needs to go back too, restore the export from step 1 through phpMyAdmin's Import tab. Nothing in this deployment deletes or rewrites a delegate record, so that should not be necessary.

---

## 8. Check it worked

In the admin panel open **Site health**. It runs eleven checks on the spot and sorts the worst first:

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

The three in bold are the ones that would have caught the problems found during this engagement. If step 6 was done properly, the leftover scripts check reads clean.

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
