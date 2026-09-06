# Prosperminds Website Redesign — Prompt for Claude Design

Paste everything below into Claude Design as one prompt. It's written to be self-contained: brand identity, business context, what's wrong with the current site, and exactly what to produce.

---

## 1. Who this is for

**Prosperminds** delivers executive-level training to senior government finance officials across Africa (and increasingly, international delegates) in Public Finance Management (PFM), IPSAS/IFRS accounting standards, data analytics, AI automation, and sustainability reporting. Delegates are accountants, auditors, budget controllers, treasury leaders, and senior decision-makers in government — not consumers, not startups. Five-day residential courses, priced from USD 599/delegate, hosted internationally (Cape Town, Kuala Lumpur, Bali, Mombasa are the next four).

**The tone this audience expects: institutional authority, not a tech startup.** A government auditor deciding whether to send their department's budget controller to a five-day course abroad is evaluating credibility, not vibe. The design should feel closer to a serious professional-services firm or a global standards body than a SaaS product landing page.

---

## 2. Brand guidelines (non-negotiable — follow exactly)

**Brand essence:** Protecting and growing the mind to achieve prosperity. Combines modern technology cues with timeless trust symbols to communicate authority, reliability, and progress.

**Brand personality:** Intelligent and thoughtful. Trustworthy and secure. Progressive and innovative. Professional yet approachable. Purpose-driven and growth-focused. **Never playful or casual.**

**Tone of voice:** Clear and confident. Insightful and educational. Strategic, not sales-heavy. Focused on long-term growth and value. No slang, no exaggerated claims, no emotional manipulation.

**Color palette — strictly three colors, nothing else as a primary brand color:**
- `#00BF63` (green) — growth, prosperity, intelligence, innovation
- `#000000` (black) — authority, strength, professionalism
- `#FFFFFF` (white) — clarity, simplicity, balance

**Typography:** Maharlika is the official and ONLY brand typeface. Logo and headlines: Maharlika Regular or Bold. Subheadings: Maharlika Regular. Body text: Maharlika Light or Regular. Confident, readable, structured — no decorative or script fonts, no substituting a different typeface anywhere.

**Logo system:** Primary logo = shield icon (protection/trust/security) + neural-head-in-shield symbol (intelligence/strategy/mind) + "Prosperminds" wordmark in Maharlika. Secondary/digital logo = shield+head icon without the outer shield outline, same wordmark. Never stretch, recolor, gradient, shadow, rotate, or place on low-contrast backgrounds.

**Imagery and graphic style** (this is the single biggest gap between the current site and the brand — see Section 4): Clean, minimal, intentional. **Strong contrast. Dark backgrounds with green highlights** as the signature look — not light, airy, pastel sections. Line-based, rounded, minimal iconography. Neural-network / node / abstract mind-inspired patterns encouraged as background texture. Shield and head elements may appear subtly as background motifs. Reference the brand's own marketing graphics (bold dark-background posters, high-contrast typographic headlines, confident short copy like "WHEN PRESSURE HITS, LEADERS RISE" and "STRONG SYSTEMS START WITH STRONG PEOPLE") as the visual register to aim for — that promotional material looks nothing like the current website, and it's the better reference.

---

## 3. What exists today (business content to reuse, not invent)

**Two live properties, will eventually consolidate to one canonical registration flow (not this project's concern — just know the data is real and already flowing):**

**Four upcoming flagship events**, all USD 599/delegate with tiered early-bird discounts (20%/15%/10%):
1. **Future-Ready PFM Leaders in the Age of AI & Automation** — Cape Town, South Africa, 19–23 October 2026
2. **IPSAS Clean-Audit Mastery & Intelligent Assets Accounting** — Kuala Lumpur, Malaysia, 16–20 November 2026
3. **Data-Driven Budget Control, Revenue Growth & Funding Breakthroughs** — Bali, Indonesia, 7–11 December 2026
4. **The Christmas PFM Mastery School for the Digital Finance Era** — Mombasa, Kenya, 14–18 December 2026

Each has a real 5-day agenda structure (Day 1 leadership/context → Days 2–4 technical depth → Day 5 synthesis + action plan + certification), a defined audience list, and three pricing tiers (Regular USD 599, VIP USD 1,999, VVIP USD 2,899 with escalating perks like executive roundtables and gala dinners).

**Three service pillars:**
1. PFM, IPSAS & IFRS Mastery — "Build the technical foundation your finance teams need."
2. Data Analytics & AI Automation — "Transform reporting from burden to strategic advantage."
3. Sustainability Reporting — "Meet global standards while strengthening transparency."

**Real credibility markers already in use:** 25 years collective experience, 875 leaders trained, testimonials from named officials across Kenya, Nigeria, Ghana, Rwanda (Chief Accountant, Treasury Leader, Strategy Director, Auditor General — genuine senior titles, use this level of seniority in any placeholder content).

**Contact:** Nairobi HQ (Twiga Towers, Moi Avenue), info@prosper-minds.com, +254 740 582302 / +254 741 174909, Mon–Fri 8am–5pm.

---

## 4. What's actually wrong with the current site (fix these specifically)

I pulled real screenshots of the live homepage and registration page before writing this. Concrete problems, not vague "make it better":

1. **It's one long single-page scroll pretending to be a multi-page site.** Nav links "About," "Services," "Contact" are anchor-jumps down the same homepage, not real pages. This is the core complaint driving this redesign — it needs to become an actual multi-page site with real URLs and real information architecture, not a single page with in-page anchors.
2. **Wrong typeface entirely.** The live site uses a generic system sans-serif everywhere. Maharlika appears nowhere. This alone makes it feel like a template, not a designed brand.
3. **Visual identity contradicts the brand guide.** The brand's own marketing material is dark, bold, high-contrast, green-on-black. The live site is dominated by light pastel-green sections, plain white cards, and generic corporate stock photography (people pointing at laptops, generic handshake photos) that could belong to any company. One dark section exists (a stats block) and reads as a random interruption rather than part of a system — there's no consistent visual rhythm.
4. **Testimonial carousel is visibly broken.** Quotes are cut off mid-sentence at the viewport edge ("...ally reduced our reporting time and ved accuracy" — truncated on both sides), with no visible way to scroll/advance. This is a real, embarrassing bug on a trust-building section for an audience that is evaluating credibility.
5. **No consistent card/section system.** Event cards, service cards, and testimonial cards all use different spacing, corner radii, and shadow treatments — reads as several different templates stitched together rather than one coherent design.
6. **The registration flow is functionally solid but visually generic** — a plain white card with standard form inputs, no sense of occasion for what is a serious, paid, executive commitment. (Functionally: it already validates correctly, computes multi-delegate invoice totals live, and generates a real invoice on submit — don't worry about the backend, only the presentation and flow need design attention.)
7. **Admin panel exists separately** (staff-only, login-gated) and is explicitly **out of scope for this round** — do not design admin/dashboard screens now.

---

## 5. Explicitly avoid: the generic "AI-generated" look

This brief exists partly to get *away* from a templated, vibecoded feel — so don't replace it with a different flavor of the same problem. A lot of AI-assisted design defaults to a recognizable house style that has nothing to do with this brand. Do not do any of the following, even if it's a plausible "modern" default:

- **No purple/blue/pink gradients**, anywhere — not on buttons, not on headline text, not as background washes. This is the single most recognizable "AI SaaS" tell and it directly contradicts the brand's flat 3-color palette (green/black/white only).
- **No glassmorphism** — no frosted/blurred translucent panels, no `backdrop-blur` cards floating over busy backgrounds. The brand calls for clean, minimal, high-contrast — flat surfaces, not frosted glass.
- **No emoji, anywhere** — not in headlines, not in buttons, not as bullet-point icons (🚀 ✨ 💡 🎯 etc.). This audience is senior government finance officials; the brand's own tone rules say "never playful or casual." Use the brand's actual line-icon system instead.
- **No em dashes as a copywriting tic.** Don't write hero/section copy in the "X — but better" or "Not just Y — Z" pattern repeatedly. Write plainly and declaratively, matching the brand voice ("clear and confident... strategic, not sales-heavy") — short, direct statements, not em-dash-stitched clauses.
- **No generic AI-SaaS headline formulas** — avoid "Supercharge your...", "Unlock the power of...", "The future of X is here", "Where X meets Y", "Elevate your...". The brand's own marketing copy is a better model: short, blunt, declarative ("WHEN PRESSURE HITS, LEADERS RISE", "STRONG SYSTEMS START WITH STRONG PEOPLE").
- **No floating blurred gradient blobs / abstract 3D shapes** as background decoration. If a background needs texture, use the brand's own sanctioned motif — neural-network nodes/lines, or the shield-and-head mark used subtly — not generic colorful blob shapes.
- **No "bento grid" of identical soft-shadow rounded cards** applied uniformly to everything regardless of content. Card treatment should come from an intentional layout decision per section, not a default component reused everywhere because it's the easy choice.
- **No icon-soup** — don't pair every single bullet point or feature with a small circular icon out of habit. Use icons deliberately, matching the brand's line-based/minimal icon rules, not as automatic decoration.
- **No generic stock-photo clichés** — no "diverse group pointing at a laptop screen smiling," no isolated stock handshake photos, no generic "person typing on laptop with coffee" filler shots. If photography is used, it should look like it was shot for this brand specifically (or at minimum, chosen with real intent) — the brand guide's own reference images (real-looking executives in real-looking government/office settings) are a better model than stock-library filler.
- **No flat "undraw.co"-style vector illustrations of generic disproportionate people.** If illustration is used at all, it should be the brand's own neural/shield motif system, not generic SaaS illustration filler.
- **No fake "Trusted by" logo strips.** Don't invent placeholder company/government logos to imply social proof that doesn't exist — the real testimonials and the 25-years/875-leaders stats are the actual credibility markers; use those.
- **No excessive rounded-pill badges scattered everywhere** ("NEW", "AI-Powered", "Live", etc.) as decorative habit — every badge/label should carry real information (e.g. an actual early-bird discount deadline), not be decoration.
- **No neon or oversaturated accent lighting effects.** The brand's contrast comes from black/white/green placement and typography weight, not glow effects.

If in doubt on any specific element, the test is: **does this look like it belongs on the brand's own dark, bold, high-contrast marketing posters (Section 2), or does it look like the default output of a generic AI page builder?** If it's the latter, don't use it.

---

## 6. What to design

A **multi-page marketing site + registration flow**, as high-fidelity prototype screens (desktop + mobile for each), all strictly on-brand per Section 2. Pages needed:

1. **Homepage** — hero, the four flagship events (as a real preview grid linking to real event pages, not everything crammed in), the three service pillars, credibility stats (25 years / 875 leaders trained — real numbers, keep them), a fixed (not broken) testimonial section, footer.
2. **Events listing / CPD calendar page** — all events, filterable by upcoming vs. past, each linking to its own detail page. This is a real, separate page, not a homepage section.
3. **Event detail page** — one event's full agenda, pricing tiers (Regular/VIP/VVIP), early-bird countdown, audience fit, clear path to registration.
4. **Registration flow** — redesigned, not just restyled. Should feel like a guided, multi-step process appropriate for a serious paid commitment (e.g., event confirmation → contact/billing → attendees → review & consent → confirmation), not one long form on a single scroll. Live invoice total, consent capture, and a genuine success state are required (these already work functionally — just needs a flow and visual treatment that matches the weight of a $599–$2,899 executive commitment).
5. **About page** — real page, not an anchor. Institutional credibility: who Prosperminds is, the 25-years/875-leaders story, the three pillars in depth.
6. **Services pages** — the three pillars (PFM & IPSAS, Data Analytics & AI, Sustainability Reporting), each substantial enough to stand alone.
7. **Contact page** — real page with the existing contact details, map, and a working-feeling contact form.
8. **404 / not-found state** — on-brand, not a generic error page.

For each page: show **both desktop and mobile**, and call out where dark-background/green-highlight brand treatment is used vs. the white/light sections, so the two aren't just alternating at random.

---

## 7. Constraints for whoever builds this afterward

- Will be implemented in **plain PHP, no frontend framework** (no React/Vue build step) — favor layouts and interactions that translate cleanly to server-rendered HTML/CSS plus light vanilla JS, not patterns that assume a component framework or heavy client-side state.
- Must be genuinely responsive, not just "doesn't break" on mobile — this audience will often be on a phone checking event details.
- Accessible: real contrast ratios (the brand's own black/white/green palette supports this well if used correctly), readable type sizes, forms with real labels.
- A CMS to let non-technical staff edit page content is planned as a **separate, later phase** — don't design admin/editing UI now, but keep the page structures reasonably clean (clear content blocks) since they'll inform that phase later.
