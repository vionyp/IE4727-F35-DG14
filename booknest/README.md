# BookNest

An online library for browsing, sampling and borrowing books, and booking quiet study rooms.
Members borrow a book for 14 days, queue for popular titles, and see any late fees live in My Account.
IE4727 Web Application Design, Theme 6 (Online Library). Base Version: HTML5, CSS3, vanilla
JavaScript, PHP and MySQL only.

**Help assistant:** Paige, in the header of every page, is a scripted assistant (not an AI model) that answers from the site's own data.

**Run it:** see [`docs/SETUP.md`](docs/SETUP.md). Short version: start Apache and MySQL in XAMPP,
import `sql/schema.sql` then `sql/seed.sql`, open `http://localhost/booknest/`, sign in as
`aisha@localhost` / `Member123!` or `admin@localhost` / `Admin123!`.

| Document | Contents |
|---|---|
| [`docs/REQUIREMENTS.md`](docs/REQUIREMENTS.md) | Application and functional requirements |
| [`docs/SITEMAP.md`](docs/SITEMAP.md) | Site hierarchy and form flow |
| [`docs/STORYBOARD.md`](docs/STORYBOARD.md) | User scenarios and the Three Click audit |
| [`docs/WIREFRAMES.md`](docs/WIREFRAMES.md) | Wireframes for all 10 pages |
| [`docs/IMPLEMENTATION.md`](docs/IMPLEMENTATION.md) | Techniques, security, contrast table, traceability |
| [`docs/TEST_LOG.md`](docs/TEST_LOG.md) | 60+ test cases with real results |
| [`docs/CHANGELOG.md`](docs/CHANGELOG.md) | What was built and what testing fixed |

Book facts are real and were checked against Open Library. Samples of classics are public domain
texts courtesy of Project Gutenberg; previews of books in copyright are written by us and are not
excerpts. Real cover images are downloaded locally with `tools/fetch-covers.php` (not stored in
Git); the logo, room plans and illustrations are original artwork made for this project.
