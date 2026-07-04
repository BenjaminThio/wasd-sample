# WASD Game Store — MySQL → Firestore migration

This is your WASD store with every `mysqli_*` call replaced by Google Cloud
**Firestore**, so it can run on Vercel (which has no persistent MySQL server
for WampServer-style apps to connect to).

## What changed

- **File layout** — every `.php` file (pages, `includes/`, `lib/`) now lives
  under an **`api/`** folder, which is the convention the `vercel-php`
  runtime expects for serverless functions. `css/` and `js/` stay at the
  project root as plain static assets. Nothing inside the PHP files needed
  path changes for this — `require_once`/`include` calls are relative to
  each other and moved together as a whole tree — except the stylesheet and
  script tags in `includes/header.php` / `includes/footer.php`, which now
  point at `/css/style.css` and `/js/main.js` (root-absolute) instead of
  `css/style.css` / `js/main.js`, since the pages that reference them now
  run from inside `/api/`.
- **`api/lib/Firestore.php`** — a small REST-API client for Firestore,
  written with plain `curl`. It does **not** use the official
  `google/cloud-firestore` Composer package, because that package needs the
  PHP `grpc` extension, which Vercel's PHP runtime doesn't provide.
  Authenticates using your Firebase project's **Web API key** (appended as
  `?key=...` on every request) rather than a service account — simpler to
  set up, but see the security note in "Create a Firestore database" below
  before you rely on this for anything beyond a personal project.
- **`api/config.php`** — now creates a `Firestore` client instead of a
  `mysqli` connection, and adds helper functions (`get_all_games()`,
  `get_cart_items()`, `save_review()`, etc.) that every page calls instead
  of writing raw SQL.
- **Every page file** (`api/admin.php`, `api/cart.php`, `api/game.php`,
  `api/games.php`, etc.) — rewritten to call the new helper functions. The
  HTML/CSS/JS output is unchanged, and all the internal links between pages
  (`href="games.php"`, `action="cart.php"`, ...) work exactly as before,
  since they're relative and every page still lives alongside its siblings
  — just one level deeper, inside `api/`.
- **`database.sql`** → **`api/seed_firestore.php`** — a one-time script that
  loads the same demo accounts/games/reviews into Firestore.
- IDs: games/users/orders keep short numeric ids (`1`, `2`, `3`...) via a
  Firestore counter, so URLs like `game.php?id=5` still work. Reviews, cart
  lines, and wishlist entries use a composite id `"{user_id}_{game_id}"`,
  which is what used to enforce the `UNIQUE KEY` constraints in MySQL.
- Search/sort/rating-average on `games.php` and `index.php` now happen in
  PHP after fetching the catalog, since Firestore has no `LIKE`, `JOIN`, or
  `AVG()`. This is a normal, standard pattern for Firestore apps and is fine
  for a catalog of dozens or a few hundred games.

## File structure

```
wasd-store/
├── vercel.json              # tells Vercel how to run the PHP functions
├── .vercelignore            # keeps the seed script out of the deployment
├── css/style.css            # static asset, served directly by Vercel
├── js/main.js                # static asset, served directly by Vercel
└── api/
    ├── config.php            # Firestore connection + all helper functions
    ├── seed_firestore.php    # run once locally to load demo data
    ├── index.php, games.php, game.php, cart.php, checkout.php,
    │   login.php, register.php, logout.php, profile.php, wishlist.php,
    │   contact.php, admin.php, admin_edit.php
    ├── includes/
    │   ├── header.php
    │   └── footer.php
    └── lib/
        └── Firestore.php     # the Firestore REST client
```

Because everything under `api/` is a serverless function on Vercel, your
site's pages are reached at URLs like `/api/index.php`, `/api/games.php`,
`/api/cart.php`, and so on. `vercel.json` includes a redirect so visiting
the bare domain root (`/`) sends people straight to `/api/index.php`.

## 1. Create a Firestore database

1. Go to the [Firebase console](https://console.firebase.google.com/),
   create a project (or use an existing one).
2. Build → Firestore Database → Create database → **Native mode**, pick
   whatever region is closest to your users (this project already used
   Singapore).
3. Project settings (gear icon) → **General** tab → scroll to "Your apps" →
   add a **Web app** if you haven't already → copy the `apiKey` and
   `projectId` out of the config snippet it shows you.

```
FIRESTORE_PROJECT_ID = projectId
FIRESTORE_API_KEY    = apiKey
```

### ⚠️ Security rules — read this before deploying

This setup authenticates with your project's public Web API key instead of
a service account, since plain PHP has no Firebase Auth session to attach
to a request. That means **Firestore Security Rules are the only thing
protecting your data**, and they have to be opened up for this app to work
at all:

Firestore Database → your database → **Rules** tab → replace the rules
with:

```
rules_version = '2';
service cloud.firestore {
  match /databases/{database}/documents {
    match /{document=**} {
      allow read, write: if true;
    }
  }
}
```

**What this means in practice:** anyone who has your project id and API
key (both visible to anyone who opens your site's network requests) can
read, edit, or delete every document in your database directly — every
user's password hash, every order, everything — with no login required.
This is a deliberate simplicity-over-security tradeoff that's fine for a
personal project or portfolio piece where the data isn't sensitive and
isn't real. Don't use this pattern for an app that holds real user data,
real payment info, or anything you wouldn't want public. The secure
alternative is the service-account approach this project used originally
(OAuth-based, no open rules needed) — happy to switch back to that at any
point if this app's purpose changes.

## 2. Seed the demo data (once)

Locally, with PHP installed:

```bash
export FIRESTORE_PROJECT_ID=your-project-id
export FIRESTORE_API_KEY=your-web-api-key
php api/seed_firestore.php
```

This creates the same 2 demo accounts, 8 games, and 4 reviews the old
`database.sql` did (admin: `admin@wasd.com` / `admin123`, player:
`arif@example.com` / `player123`). It's safe to run more than once — it
skips seeding if the `games` collection already has data.

## 3. Deploy to Vercel

This uses the community **[vercel-php](https://github.com/vercel-community/php)**
runtime (already wired up in `vercel.json`) — plain PHP files, no Node/Next.js
needed.

```bash
npm i -g vercel     # once
vercel login        # once
vercel               # deploy from inside the wasd-store folder
```

In the Vercel dashboard → your project → **Settings → Environment
Variables**, add:

- `FIRESTORE_PROJECT_ID`
- `FIRESTORE_API_KEY`

Redeploy after adding the env vars so the running functions pick them up.

**Don't deploy `api/seed_firestore.php` to production once you've seeded
your data** — it's already excluded via `.vercelignore`, but double check
it isn't reachable at a public URL before you consider the app "live".

Your site's pages are served from `/api/...` (e.g.
`https://your-app.vercel.app/api/index.php`,
`https://your-app.vercel.app/api/games.php`). The included redirect sends
the bare domain root to `/api/index.php` automatically.

## Performance & session persistence fixes

Two issues that show up once you're actually running on Vercel:

**Pages feel slow to load.** Firestore has no server-side JOIN, so pages
like `games.php`/`index.php` fetch whole collections into PHP and work with
them there — and some pages called the same collection more than once while
building the page (catalog + genre filter list, etc.), meaning multiple
separate network round-trips for data already fetched. `api/lib/Firestore.php`
now caches reads for the lifetime of a single request, so repeated calls to
the same collection/document reuse the first result instead of re-fetching.

**Staying logged in doesn't work.** PHP's default session handler writes
session data to a local temp file. On Vercel, each request can be picked up
by a different serverless function instance with its own disposable
filesystem — so a session written during login may simply not exist by the
time your next request lands on a different instance, which looks exactly
like "logging in does nothing." `api/lib/FirestoreSessionHandler.php` stores
session data in a `sessions` collection in Firestore instead, so every
invocation reads/writes the same place no matter which instance handles it.

This adds two small Firestore reads/writes per page (reading and saving the
session), which is normal and necessary — much cheaper than the collection
fetches this app already does, and it's what makes login persist correctly.

**One-time optional cleanup:** old sessions accumulate in the `sessions`
collection since nothing deletes them automatically. In the Firebase
console, go to **Firestore Database → your database → TTL** and add a TTL
policy on the `sessions` collection using the `expires_at` field — Firestore
will then delete expired session documents on its own.

## Fixing a very high LCP / "everything feels slow"

If Chrome DevTools shows a Largest Contentful Paint of several seconds (or
more), the page isn't slow because of CSS or JavaScript — it's slow because
the PHP function is blocked making several sequential network calls to
Firestore *before it sends any HTML at all*. Two causes, both addressed:

**1. Connection reuse (already fixed in this version).** The Firestore
client used to open a brand-new TLS connection for every single call and
close it immediately after — so a page making 6-8 Firestore calls paid 6-8
full handshakes instead of 1. `api/lib/Firestore.php` now reuses one curl
handle for the whole request, so only the first call pays the handshake
cost and the rest reuse that connection.

**2. Region mismatch (you need to check this one).** Vercel Functions
default to **Washington, D.C., USA (`iad1`)** unless you've explicitly
picked a different region. If your Firestore database is located in Asia
(likely, if you created it without changing the default while based in
Malaysia) and your Vercel function runs in the US, every one of those
Firestore calls crosses the Pacific twice — and since they happen one after
another rather than in parallel, that latency adds up fast.

To check and fix:

1. **Find your Firestore location:** Firebase console → your project →
   Project settings (gear icon) → General tab → look for
   "Default GCP resource location" (e.g. `asia-southeast1` = Singapore,
   `nam5` = central US, `eur3` = Europe).
2. **Find/set your Vercel function region:** Vercel dashboard → your
   project → Settings → Functions → Function Region. Pick the region
   closest to your Firestore location (e.g. Firestore in
   `asia-southeast1` → pick Vercel's `sin1`, Singapore).
   - On the Hobby plan you can pick one region; Pro allows up to five.
   - You can also set it in `vercel.json` with a `"regions": ["sin1"]` key
     — but the dashboard setting is the more reliable place to confirm it
     actually took effect (check the deployment's Build Summary afterward).
3. Redeploy after changing the region.

Firestore's location can't be changed after the database is created
without recreating it, so region alignment is really about pointing your
*Vercel function* at wherever your *Firestore database* already is.

## Notes / limitations of this migration

- **Order numbers / new-doc counters** use a simple read-then-write counter
  document, not a Firestore transaction. That's fine for a personal/demo
  store; under heavy concurrent traffic two people could theoretically get
  clashing order numbers at the exact same instant. Wrapping `nextId()` in a
  real Firestore transaction (`:commit` with a transaction id) would close
  that gap if you ever need it.
- Search on `games.php` is a simple case-insensitive substring match done in
  PHP (Firestore has no full-text search). For a catalog with thousands of
  games you'd eventually want something like Algolia or Typesense in front
  of it — not needed at this store's size.
- This app is still plain PHP, and Vercel's PHP support is a community
  runtime rather than an officially first-party Vercel product — worth
  knowing if you're picking a long-term host for something beyond a
  personal project.
