# Prosperminds Admin Panel and CMS Redesign: Prompt for Claude Design

Paste everything below into Claude Design as one prompt. It is written to be self-contained.

> **Companion document:** the public website redesign brief is `WEBSITE-REDESIGN-PROMPT.md`, and its approved output is in `prototype/`. This admin work must feel like the same brand, but it is a different kind of product. Section 4 explains why that distinction matters more here than anywhere else.

---

## 1. What this is

Prosperminds runs five day residential training courses in public financial management, IPSAS and IFRS reporting, data analytics and sustainability disclosure, for senior government finance officials across Africa. Courses are priced from USD 599 per delegate and run in Cape Town, Kuala Lumpur, Bali and Mombasa.

Behind the public site there is a private, login protected admin panel. It already exists and it already does a lot. This brief covers **two things at once**:

1. **A visual and structural redesign of the existing admin panel**, which works but looks like a generic dashboard template and has real usability problems.
2. **A content management system**, so non technical staff can run the site the way they would run a WordPress site: add and lay out a page, upload and crop a picture, rearrange a menu, swap the logo and favicon, read form submissions, and undo a mistake. Almost none of this exists yet. Section 6 sets out the scope and tiers it honestly.

Both are internal tools. No member of the public ever sees these screens.

---

## 2. Who actually uses it

There are four real accounts today, plus a QA account. This is a small team, not an enterprise:

| Person | Role | Notes |
|---|---|---|
| admin | super_admin | The catch all account |
| evans | super_admin | Primary business contact |
| shillah | editor | Marketing and delegate operations |
| lydia | editor | Marketing and delegate operations |

**These are not developers.** They are marketing and programme staff. Lydia's own registration attempts in the delegate data show her filling in a form fourteen times in a row trying to get it to work, which tells you something important: when a screen is confusing, this team retries rather than reads. Design for that.

The permission model already implemented is a JSON structure per user of module to actions:

```json
{"dashboard":["view"],
 "registrations":["view","export"],
 "events":["view","create","edit","toggle"],
 "users":["view","create","edit"],
 "settings":["view"]}
```

Actions in use: `view`, `create`, `edit`, `toggle`, `export`. Roles are `super_admin` and `editor`, plus separate `is_administrator` and `is_staff` flags and a free text `department`. The redesign should surface permissions clearly, because right now it is impossible to tell at a glance what an editor can actually do.

---

## 3. What already exists (do not redesign these away)

Seven screens, reachable from a dark left sidebar: **Overview, Analytics, Registrations, Events, Accounting, Users, Settings.**

**Overview** is a genuine executive dashboard, not a placeholder. Eight KPI tiles (total registrations, active events, registrations this month, last seven days, total invoiced revenue, cash collected, outstanding pipeline, net income after expenses), an "Executive Summary" block described as a CFO/CEO snapshot with plain language insight lines ("Collection rate is 0.0%", "Top grossing event is ... at USD 23,361.00"), an expense breakdown, recent registrations, a collections pipeline with per customer outstanding amounts, monthly performance, registrations by event, and per event profitability with revenue, expenses and contribution.

**Events** is already a full editor. A list with banner thumbnails, dates, location, price, active status and edit/delete, plus a create form covering: title, tagline, date display, start date, location, price, sort order, focus tags, "why this programme why now", "what you will master", "who should attend", a five day programme (title and description per day), three delegate tiers (VVIP, VIP, Regular, each with price and a perks list), three early bird tiers (percentage and deadline each), an event image upload, an event brochure PDF upload, and a show-on-website toggle.

**Registrations** lists delegate sign ups with export. **Accounting** is the largest file in the panel and covers invoices, customers, expenses and vendor bills. **Users** manages accounts and that permission JSON. **Settings** holds SMTP configuration and company details. There is also an invoice download route and a resend-email action.

**The point:** this is not a thin admin. The content model is rich and mostly right. Your job is to make it usable and on brand, and to add the missing content editing, not to reinvent what works.

---

## 4. The central design problem, and it is not decoration

The public site's approved design system is deliberately editorial: a serif typeface (Maharlika, one weight only), very large uppercase headings, wide letter spacing, generous whitespace, flat 2px corners, hairline rules instead of shadows, and a strict palette of green `#00BF63`, black `#000000`, white `#FFFFFF` plus neutral greys. It is designed to make a government auditor trust an institution.

**An admin panel has the opposite job.** Staff are scanning, comparing, filtering and entering data, often the same task many times a day. Density, alignment and fast recognition matter more than gravitas. A marketing design language applied unchanged to a data tool produces something beautiful and slow.

So: **same brand, different register.** Specifically:
- Keep the palette exactly. Green stays an accent for primary actions, active states and positive figures. No new hues. No purple, no blue, no gradient.
- Keep flat 2px corners and hairline rules rather than soft shadows.
- **Reconsider the typeface.** Maharlika is a display serif with one weight, and one weight cannot carry the hierarchy a dense table needs. Propose how to handle this. A defensible answer is Maharlika for screen titles and headings only, with a highly legible system sans for tabular data, form fields, numbers and dense UI, chosen because a serif at 13px in a table row is genuinely harder to scan and because numerals need to align. If you propose that, say so explicitly and show it, because it is a deliberate departure from "Maharlika is the only typeface" and needs to read as a considered decision rather than a slip.
- Tighter spacing than the public site throughout. Smaller type. More per screen.
- Tabular numerals for every money and count column, right aligned, so figures compare down a column.

---

## 5. What is wrong today (fix these specifically; I have reviewed every screen)

1. **The visual language is a generic dashboard template.** Pastel tinted icon chips in rounded squares, soft drop shadows, rounded cards, a colour for every category. It could be any SaaS product and shares nothing with the brand except the logo.
2. **The event form is roughly forty fields in one uninterrupted vertical scroll**, from title through five day programme to three delegate tiers to three early bird tiers to two file uploads. There is no grouping a person can navigate, no sense of progress, and no way to find one field to change it. This is the single worst usability problem in the panel.
3. **That form sits permanently in the right hand column even when the user is only browsing the list.** Creating and browsing are different tasks and should not compete for the same screen.
4. **Money is displayed inconsistently.** "USD 40,133.00" appears at heading size in a tile, while table amounts are small text, and some tiles show a currency and others do not.
5. **Empty states are bare.** "No expenses yet" with an icon and nothing else. An empty state should say what the thing is and offer the action that fills it.
6. **Nothing communicates permissions.** An editor sees menu items they may not be able to act on, and there is no indication of why.
7. **No obvious destructive action safety.** Event delete is a small red icon button directly in the list row, adjacent to edit.
8. **Mobile is not considered.** These are desktop screens. Staff do check things on a phone; at minimum the dashboard and registrations list should be usable there.

---
## 6. What Phase 5 adds: a real CMS, not a copy editor

This is the genuinely new capability, and the brief for it is deliberately ambitious. The client's requirement is that staff can run the site the way they would run a WordPress site: add a page, upload a picture, change a menu, swap the logo. Not merely retype an existing sentence.

Today the rebuilt public site reads its copy from a `page_content` table, addressed by a page slug plus a section key, with around 130 rows seeded. Pages themselves are still PHP files, the menu is a PHP array, and the logo is a file path in the markup. **All of that has to become editable.**

### 6.0 A word on scope, because it matters to the design

A full WordPress equivalent is years of work. This is a four person team, on plain PHP with no build step, deploying through cPanel with no terminal. So the brief below is grouped into three tiers. **Design tier 1 and tier 2 properly. Show tier 3 only as a placement or an empty state**, so the layout has somewhere to grow without pretending the feature exists.

Say clearly in your output which tier each screen belongs to. A prototype that quietly implies all of this ships at once would set a false expectation.

### 6.1 Tier 1: the core, without which this is not a CMS

**Pages.** Create, edit, duplicate, delete and reorder pages. Each page has a title, a URL slug, a template, a status (draft, published, scheduled), a parent for hierarchy, and SEO fields. Deleting goes to a trash that can be restored, because a marketing person will delete the wrong thing. Changing a slug must offer to leave a redirect behind, or old links break silently.

**A block based page editor.** Pages are built from stackable content blocks rather than one free text field. Blocks that match this site: a hero, a rich text section, an image, an image plus text row, a statistics row, a card grid, a testimonial set, an accordion or agenda list, a call to action band, an event list, a contact block, an embed. Each block should be addable, reorderable by drag, duplicable, removable, and individually settable to light or dark per the site's section system. This is what actually lets someone build a page without a developer.

**Rich text editing** inside a text block: headings, bold, italic, links, lists, quotes. Deliberately constrained. Do not offer arbitrary font, size and colour controls, because the whole value of the design system is that a page cannot be made ugly by accident. Show what the constrained toolbar looks like.

**Media library.** Upload, browse, search and delete images, PDFs and documents. Grid and list views, filter by type and date, and a details panel with alt text, caption, title, dimensions, file size and where the file is used. Alt text should be prompted for on upload rather than hidden, since the audience includes public sector bodies with accessibility obligations. Support replacing a file in place so a swapped banner updates everywhere it appears. Show bulk select and bulk delete, with a warning when a file is in use.

**Image handling on upload.** Automatic resizing to the sizes the site actually uses, plus crop and focal point selection so a banner does not crop through somebody's face. Show the crop interface.

**Menus.** Build the header and footer navigation visually: add items, drag to reorder, nest one level for dropdowns, and link to a page, an event, an external URL or an anchor. Right now the navigation is a PHP array and the Events item was hand edited when the events page shipped. That must never require a developer again.

**Site identity.** Logo upload with a preview on both light and dark backgrounds, since the footer is black. Favicon upload with a live preview at real sizes, because the current favicon had to be cropped from a wide logo by hand. Site title, tagline, the contact details that appear in the footer, and the social links.

**Forms and submissions.** The site already writes to three tables: `contact_messages`, `newsletter_subscribers` and sponsorship enquiries. Staff currently have no way to read any of them. They need a submissions inbox: list, filter, search, read a single submission, mark handled, and export to CSV. Newsletter subscribers additionally need an unsubscribe action and an export for the mailing provider.

**Revision history.** Every content change stores a revision with who made it and when, a visual diff between versions, and one click restore. This is the single most valuable safety feature for a non technical team, and it is why they will trust the tool.

**Draft, preview and publish.** Edit without affecting the live site, preview the real page as it will look, then publish. Scheduled publishing for a page or a block, since course announcements are date driven. A preview must be shareable with a colleague who is not logged in, via an expiring token link.

### 6.2 Tier 2: the things that keep a live site healthy

**SEO per page.** Meta title and description with live character counts and a Google result preview, an Open Graph image, a canonical URL, and a noindex toggle.

**Structured data.** `schema.org/Event` markup driven from the event records. `PROJECT.md` flags this as a real missed opportunity: an events business with no event structured data is invisible to rich results. Surface it as a per event toggle with a preview of what search engines will see.

**Redirect manager.** Create and manage 301 redirects, with a log of 404s actually being hit so staff can see which broken links matter. The prototype already has a "broken link demo" acknowledging this.

**Sitemap and robots.** Both are already generated. Give them a screen: which pages are included, exclude toggles, last generated, and the robots rules.

**Audit log.** Who changed what, when, and from where. This team shares work across four accounts and has already had one incident where nobody could reconstruct what happened. Filterable by user, by content type and by date.

**Global design controls, tightly bounded.** Not a theme editor. A small set: which sections are dark, the accent colour within brand limits, and the container width. Enough to adjust, not enough to break the brand.

**Bulk operations** on lists: publish, unpublish, delete, change status, export.

**Admin search.** One search across pages, events, media, registrations and submissions. A command palette is a good fit for a tool used daily.

**Site health.** A single screen answering: is mail working, is the database reachable, are scheduled jobs running, are there broken links, is anything misconfigured. The August incident, where registration silently failed for weeks, is precisely what this screen exists to catch.

**Two factor authentication** on admin accounts, and a session list showing where an account is signed in. The panel currently protects real financial data with a password alone.

**Backup and export.** Export content as a file, and show when the last database backup ran. No terminal access means this must be a button.

### 6.3 Tier 3: show the placement, do not build the feature

Indicate where these would live, as a disabled item or an empty state, and label them clearly as later:

Reusable blocks saved to a library, content templates, a content calendar, multilingual, comments or approval workflow beyond draft and publish, A/B testing, personalisation, a public API, and anything resembling a plugin or theme marketplace. **Explicitly out of scope entirely:** multisite, e-commerce, a CLI (there is no terminal), and real time collaborative editing.

### 6.4 The specific gaps this must close

Three concrete problems from this engagement that the CMS is the planned home for:

1. **Early bird discounts.** The site advertises discounts prominently and the invoice charges full price, because nothing in the invoicing path reads the discount. Every registration so far was created inside an open discount window and billed the full amount. Design a per event screen showing the three tiers, their deadlines, whether the discount is actually applied to invoices, and the resulting delegate price. The state must be unmistakable, because the entire problem was that a promise on one page and the behaviour of another system silently disagreed.

2. **Invoice access.** Invoice PDFs are currently readable by anyone who guesses a filename, and the names are a date plus a sequential id. Delivery is moving to signed expiring links. Admin needs to view an invoice and reissue a link to a delegate.

3. **Banner library.** The public events page shows each event's promotional banner with download and copy link, used for LinkedIn and partner mailings. The admin side needs upload, replacement, and a clear indication of which banner is live for which event.

---

## 7. Screens to prototype

Desktop for all, mobile for the starred ones. Label each with its tier from section 6.

**The existing panel, redesigned**

1. **Login***. Currently plain. It is the first impression of the tool and should look like the brand.
2. **Overview dashboard***. Restructure the eight tiles, executive summary, pipeline and per event profitability into a clear hierarchy. Decide what a person actually needs first.
3. **Registrations list***. Filter, search, export, and drill into one registration, including invoice status and the resend action.
4. **Events list**. Browsing, separated from creating.
5. **Event editor**. The forty field problem solved. Group it, make it navigable, make a single change easy.
6. **Users and permissions**. Make what an editor can actually do legible.
7. **Settings**. SMTP, company details, and site identity including logo and favicon upload with previews.

**The new CMS**

8. **Pages list**. Hierarchy, status, last edited, who edited, and the trash.
9. **Page editor with blocks***. The centrepiece. Block list, add block, reorder by drag, per block settings, and the constrained rich text toolbar. Show the editor with a real page loaded, not an empty one.
10. **Media library***. Grid and list, filters, and the details panel with alt text and usage.
11. **Image crop and focal point**.
12. **Menu builder**. Drag, nest, and the four link types.
13. **Revision history**. The diff between two versions, and restore.
14. **Preview and publish**. Including the shareable expiring preview link.
15. **Submissions inbox***. Contact, newsletter and sponsorship enquiries in one place.
16. **SEO panel** for a page, with the search result preview.
17. **Early bird discount control**.
18. **Banner library**.
19. **Redirects and 404 log**.
20. **Audit log**.
21. **Site health**.
22. **Admin search or command palette**.
23. **Empty states, a destructive confirmation, and a validation error**, as a small pattern set rather than full screens.

For each screen state which permission level sees it, and what an editor sees differently from a super admin.

---

## 8. Explicitly avoid the generic AI look

The public brief carries this section and it applies here too. Do not produce a different flavour of the same problem.

- **No purple, blue or pink gradients** anywhere. The palette is green, black, white and neutral greys.
- **No glassmorphism**, no frosted translucent panels, no blurred backdrops.
- **No emoji**, in navigation, buttons, empty states or status chips. Use a consistent line icon set.
- **No em dashes in any interface copy.** This is an explicit client instruction and it applies to labels, buttons, help text, empty states and validation messages. Write with full stops, commas, colons or parentheses instead. Do not substitute an en dash as a lookalike.
- **No pastel tinted icon chips**, which is exactly the current template look being replaced.
- **No colour coding by category** for its own sake. Colour should carry meaning: a status, a warning, a positive figure.
- **No decorative charts.** Every chart must answer a question a person actually has. If a bar chart adds nothing a number does not, use the number.
- **No fake data that flatters.** Use realistic figures drawn from the real ones quoted in this brief, including the awkward ones: cash collected is currently USD 0.00 against USD 40,133.00 invoiced, and the collection rate is 0.0%. A dashboard that only looks good with healthy numbers is not finished. Show how it looks when the news is bad.
- **No sidebar full of aspirational menu items** that do not exist. Seven modules plus the new content ones, nothing invented.

The test: does this look like a serious internal tool for a finance training institution, or like the default output of a dashboard generator?

---

## 9. Constraints for whoever builds it

- **Plain PHP, no framework and no build step.** No React, no Vue, no npm pipeline. Server rendered HTML, CSS, and light vanilla JavaScript. The existing panel is already this and it must stay deployable the same way.
- **Deployment is cPanel Git with no terminal access.** No step may require a shell command, a migration runner, or a compile.
- Progressive enhancement matters. A staff member on a poor connection must still be able to do the core task. Do not design an interaction that only works with a heavy client side dependency.
- Reuse the public site's design tokens where they fit. The stylesheet already defines the palette, the flat 2px radius and the hairline rules.
- Accessibility: real labels on every field, visible focus states, sensible heading order, and contrast that holds. Staff use this all day.
- The permission model, the seven existing modules and the `page_content` shape described above are all already built. Design to them rather than inventing a different data model.
- **The block editor is the hardest thing here, and the constraints are real.** Drag to reorder, inline editing and a media picker all have to work without a framework and without a build step. Prefer patterns that degrade: a block list with explicit move up and move down controls alongside dragging, a full page editor rather than an inline overlay, and a normal form submit as the fallback for every action. If a design decision only works with a heavy client side dependency, it is the wrong decision for this project regardless of how good it looks.
- **File uploads are constrained by shared hosting.** Assume modest upload size limits and no image processing library beyond what PHP ships with. Cropping and resizing must be achievable server side with GD, or client side before upload.
- **Nothing may require a shell.** Backups, exports, cache clearing, regenerating image sizes and applying schema changes all have to be buttons in the interface, because there is no terminal on this hosting.
