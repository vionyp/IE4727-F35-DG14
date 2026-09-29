# Changelog

## 2026-09-28: Renamed from Marginalia to BookNest

- New name everywhere: site name, page titles and descriptions, branch name, docs,
  `PROJECT_BRIEF.md`, folder (`booknest/`), URL (`/booknest/`) and database (`booknest`).
- Serial numbers now start with `BNT-` (was `MRG-`), and cover files are `bnt-*.svg`.
- Cookie and session names now start with `bn_` (was `mg_`).
- New logo mark and favicon: an open book resting in a woven nest.
- No functional change. The course PDFs are no longer kept in the repo (`*.pdf` is in `.gitignore`).
- Re-checked after the rename: every PHP and JS file passes a syntax check, and
  `tools/e2e-test.php` passes 44 of 44 on a Linux test setup (PHP 8.3, MariaDB 10.11, Apache 2.4).
  Test T18 hard codes the Windows path `C:/xampp/tmp`, so on Linux its session path had to be
  swapped for the run. **Re-run the test on XAMPP before you submit** and update `TEST_LOG.md`.
  Because the database is now named `booknest`, import `sql/schema.sql` and `sql/seed.sql` again.

## 2026-09-28: Base Version complete (Phases 0A to 6)

### Phase 0A: design documents
- Wrote `REQUIREMENTS.md` (22 application requirements, 43 functional requirements),
  `SITEMAP.md`, `STORYBOARD.md` (3 scenarios, Three Click audit) and `WIREFRAMES.md` (10 pages,
  desktop and mobile) before any code.

### Phase 0B: foundation
- Schema with 8 InnoDB tables; seed builder that pulls 38 public domain samples from Project
  Gutenberg and writes relative dates, original SVG covers and room plans.
- Shared bootstrap, secure sessions, CSRF, flash messages, prepared statement helpers, mail guard.
- Design tokens and four external stylesheets.

### Phases 1 to 5: features
- Home, catalogue (search, filter, sort, instant refine, member serial lookup), book detail.
- Sample reader with two page spread, 3D page turn, swipe, keyboard and reduced motion.
- Cart, guest checkout, payment simulator, transaction safe stock.
- Study rooms: availability table, booking with row locks, cancellation, daily cap.
- Accounts, My Account, My Shelf, add a book, admin approvals and GROUP BY analytics.

### Phase 6: polish and proof (issues found by testing, then fixed)
- Reader: keyboard handler crashed when a key event targeted the document (found by the
  browser probe).
- Mobile: menu button showed on desktop (CSS order); book page glow and admin charts caused
  sideways scrolling at 375px; visually hidden table labels widened the page (fixed by
  positioning the table wrapper).
- Accessibility: account link had no name on phones; reader caption contrast was 4.22:1;
  room slot labels did not include the visible word "Free"; star ratings used `aria-label` on
  a `<span>` (68 validator errors); input borders were 1.9:1 and small grey text 4.2:1.
  All fixed; Lighthouse accessibility now 100 on every page and the HTML validator reports 0 errors.
- Email: Mercury rejected the sender `library@localhost`; switched to `postmaster@localhost`
  and confirmed real delivery.
- Covers: slightly softer accent colours.

### Deviations from the design documents
- None in structure. The wireframes place the member "Admin" link in My Account; it also
  appears in the main navigation for admins, which keeps admin tasks within three clicks.
