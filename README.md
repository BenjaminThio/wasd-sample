# WASD Game Store — MySQL → Firestore migration

This is your WASD store with every `mysqli_*` call replaced by Google Cloud
**Firestore**, so it can run on Vercel (which has no persistent MySQL server
for WampServer-style apps to connect to).

## What changed

- **`config.php`** — now creates a `Firestore` client (`lib/Firestore.php`)
  instead of a `mysqli` connection, and adds helper functions
  (`get_all_games()`, `get_cart_items()`, `save_review()`, etc.) that every
  page calls instead of writing raw SQL.
- **`lib/Firestore.php`** — a small REST-API client for Firestore, written
  with plain `curl` + `openssl`. It does **not** use the official
  `google/cloud-firestore` Composer package, because that package needs the
  PHP `grpc` extension, which Vercel's PHP runtime doesn't provide. This
  client authenticates with a service account and talks to the Firestore
  REST API directly, so there are zero Composer dependencies.
- **Every page file** (`admin.php`, `cart.php`, `game.php`, `games.php`,
  etc.) — rewritten to call the new helper functions. The HTML/CSS/JS output
  is unchanged.
- **`database.sql`** → **`seed_firestore.php`** — a one-time script that
  loads the same demo accounts/games/reviews into Firestore.
- IDs: games/users/orders keep short numeric ids (`1`, `2`, `3`...) via a
  Firestore counter, so URLs like `game.php?id=5` still work. Reviews, cart
  lines, and wishlist entries use a composite id `"{user_id}_{game_id}"`,
  which is what used to enforce the `UNIQUE KEY` constraints in MySQL.
- Search/sort/rating-average on `games.php` and `index.php` now happen in
  PHP after fetching the catalog, since Firestore has no `LIKE`, `JOIN`, or
  `AVG()`. This is a normal, standard pattern for Firestore apps and is fine
  for a catalog of dozens or a few hundred games.

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
php seed_firestore.php
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

**Don't deploy `seed_firestore.php` to production once you've seeded your
data** — delete it or add it to `.vercelignore` so it isn't reachable at a
public URL.

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
