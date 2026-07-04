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
- **`api/lib/Firestore.php`** — new: a small REST-API client for Firestore,
  written with plain `curl` + `openssl`. It does **not** use the official
  `google/cloud-firestore` Composer package, because that package needs the
  PHP `grpc` extension, which Vercel's PHP runtime doesn't provide. This
  client authenticates with a service account and talks to the Firestore
  REST API directly, so there are zero Composer dependencies.
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
   create a project (or use an existing GCP project).
2. Build → Firestore Database → Create database → **Native mode**, any
   region.
3. Project settings (gear icon) → **Service accounts** → **Generate new
   private key**. This downloads a JSON file — keep it secret, never commit
   it to Git.

From that JSON file you need three values:

```
FIRESTORE_PROJECT_ID   = project_id
FIRESTORE_CLIENT_EMAIL = client_email
FIRESTORE_PRIVATE_KEY  = private_key   (the whole "-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n" string)
```

Since Firestore access here goes through your own server code with a
service account (never the browser), you can leave the default Firestore
security rules ("deny all client access") in place — nothing needs to be
opened up publicly.

## 2. Seed the demo data (once)

Locally, with PHP installed:

```bash
export FIRESTORE_PROJECT_ID=your-project-id
export FIRESTORE_CLIENT_EMAIL=your-service-account@your-project.iam.gserviceaccount.com
export FIRESTORE_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----
...
-----END PRIVATE KEY-----
"
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
- `FIRESTORE_CLIENT_EMAIL`
- `FIRESTORE_PRIVATE_KEY` (paste it with the literal `\n` sequences kept, or
  as real newlines — the client converts either form)

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
