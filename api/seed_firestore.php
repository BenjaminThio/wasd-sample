<?php
/* ============================================================
   ONE-TIME SEED SCRIPT — populates Firestore with the same demo
   data the old database.sql gave you (2 accounts, 8 games, 4
   reviews). Equivalent of `mysql -u root < database.sql`.

   Run it once, locally, with your Firestore env vars set:

     export FIRESTORE_PROJECT_ID=your-project-id
     export FIRESTORE_CLIENT_EMAIL=your-service-account@...gserviceaccount.com
     export FIRESTORE_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n"
     php seed_firestore.php

   Do NOT deploy this file to a public URL / do NOT leave it
   reachable in production — delete it (or keep it out of your
   Vercel deployment) after you've seeded your database once.
   Running it twice is safe though: it skips seeding if the
   "games" collection already has documents in it.
   ============================================================ */

require_once __DIR__ . '/config.php'; // brings in $conn (Firestore) + all helper functions

if (PHP_SAPI !== 'cli') {
    die("For safety this script only runs from the command line (php seed_firestore.php).\n");
}

$existing = $conn->all('games');
if (count($existing) > 0) {
    echo "Firestore already has " . count($existing) . " game(s) — seeding skipped.\n";
    echo "Delete the 'games' collection in the Firebase console first if you want to reseed.\n";
    exit;
}

echo "Seeding users...\n";
// Password for admin: admin123 / for PixelArif: player123 (same hashes as the original database.sql)
$conn->set('users', '1', array(
    'username' => 'admin',
    'email' => 'admin@wasd.com',
    'password_hash' => '$2y$10$C6FUoIUn.dy9veJxWBEG3O1/1a/B2B4z3ffz.r0Rosqxa.VQT8HUO',
    'avatar' => '🕹️',
    'favorite_genres' => 'RPG,Strategy',
    'is_admin' => 1,
    'created_at' => time(),
));
$conn->set('users', '2', array(
    'username' => 'PixelArif',
    'email' => 'arif@example.com',
    'password_hash' => '$2y$10$wngcvLVuahjiZwSYvjdcvesfbZwd7W5rixPUWltZHqgjsGknhB5A.',
    'avatar' => '👾',
    'favorite_genres' => 'RPG,Roguelike,Indie',
    'is_admin' => 0,
    'created_at' => time(),
));
$conn->set('counters', 'users', array('value' => 2));

echo "Seeding games...\n";
$games = array(
    array('Neon Drift Protocol', 'Hyperline Studio', 'Racing',
        'Anti-gravity street racing through a rain-soaked megacity.',
        'Neon Drift Protocol drops you into the underground racing circuit of Bassline City, where anti-gravity rigs scream through rain-soaked highways at 400 km/h. Build your machine from salvaged parts, earn a reputation across five rival districts, and outrun the corporate enforcement drones that patrol the skyline. Features a fully dynamic weather system, a synthwave soundtrack with 40 licensed tracks, and an 8-player online drift league with weekly seasons.',
        129.00, 0, 'New release', 'art-1', 2026),
    array('Emberfall', 'Ashlight Interactive', 'Action RPG',
        'An open-world action RPG set in a kingdom frozen mid-catastrophe.',
        "The kingdom of Vellin burned in a single night — then time stopped. In Emberfall you play a Cindermage who walks freely through the frozen moment, unraveling what happened one ember at a time. Explore a seamless open world locked in the instant of disaster, manipulate suspended fire and falling debris to solve traversal puzzles, and face guardians who remember how to move. Over 60 hours of main story, six companion arcs, and a New Game+ that rewinds the catastrophe differently.",
        199.00, 50, 'Best seller', 'art-2', 2025),
    array('Tidewalker', 'Lighthouse Nine', 'Adventure',
        'A meditative puzzle-adventure across a world of living tides.',
        "Tidewalker is a hand-painted puzzle adventure about a lighthouse keeper's daughter who can pull the ocean like a thread. Rethread tides to raise sunken villages, calm leviathans, and guide lost ships home. No combat, no fail states — just the sea, its music, and puzzles that reshape entire coastlines. Winner of three independent game festival awards for art direction and original score.",
        59.00, 0, 'Indie pick', 'art-3', 2025),
    array('Voidbound Legion', 'Redshift Forge', 'Co-op Shooter',
        'A 4-player co-op shooter on the edge of a collapsing star system.',
        'Voidbound Legion is a squad-based co-op shooter where four mercenaries take contracts in a star system falling into a black hole. Gravity shifts mid-fight, light bends around the horizon, and every mission ends with an extraction you have to earn. Deep class customization, procedurally assembled strike zones, and a physics engine built around orbital decay. Crossplay supported on all platforms.',
        159.00, 75, 'Top deal', 'art-4', 2024),
    array('Ashen Crown', 'Duskforge Games', 'Souls-like',
        'A brutal souls-like beneath a kingdom of volcanic glass.',
        "Descend beneath the Glasspeak, where a dead king's crown still commands an army of vitrified knights. Ashen Crown is a punishing action RPG with weighty combat, stance-based dueling, and a world that rearranges itself each time you die. Forge weapons from the glass of fallen bosses, and decide whether to shatter the crown — or wear it.",
        179.00, 0, 'Pre-order', 'art-5', 2026),
    array('Starfall Tactics', 'Quiet Orbit', 'Strategy',
        'Turn-based tactics where every unit is a falling star you aim.',
        'Command a constellation in Starfall Tactics, a turn-based strategy game where units descend from orbit as guided starfall. Position your descent lanes, chain impact combos, and reshape the battlefield with craters of your own making. A 30-mission campaign, a map editor, and ranked asynchronous multiplayer where turns can be played from any device.',
        89.00, 30, null, 'art-6', 2024),
    array('Hollow Circuit', 'Glitchmoth', 'Metroidvania',
        'A metroidvania inside the body of a dying machine god.',
        'The machine god Kirel has one hour left to live — from the inside, that hour is your whole world. Hollow Circuit is a metroidvania set inside a colossal dying computer, where you play a repair daemon fighting corrupted processes for control of failing subsystems. Every ability you recover restores part of the god — and changes the map. Praised for its precise movement, haunting chiptune-orchestral score, and a final hour players still argue about.',
        69.00, 0, 'Trending', 'art-7', 2025),
    array('Mirefen', 'Bogwitch Collective', 'Horror',
        'A folk-horror survival game in a marsh that remembers you.',
        "The fen took your sister seven years ago. Now it's calling you back. Mirefen is a slow-burn folk-horror survival game set in an endless marsh that rearranges itself around your fears. Manage light, warmth, and sanity; barter with things that wear familiar faces; and map a place that refuses to be mapped. Features an adaptive dread system that learns what unsettles you — and leans in.",
        99.00, 60, null, 'art-8', 2023),
);

$gid = 0;
foreach ($games as $g) {
    $gid++;
    $conn->set('games', (string)$gid, array(
        'title' => $g[0], 'developer' => $g[1], 'genre' => $g[2],
        'short_desc' => $g[3], 'description' => $g[4],
        'price' => $g[5], 'discount' => $g[6], 'badge' => $g[7],
        'art' => $g[8], 'release_year' => $g[9],
        'created_at' => time(),
    ));
}
$conn->set('counters', 'games', array('value' => count($games)));

echo "Seeding reviews...\n";
$reviews = array(
    array(2, 2, 5, 'The frozen-time open world is unreal. Walking through a suspended explosion and rearranging the debris to climb a tower is the coolest traversal I have played in years.'),
    array(2, 4, 4, 'Chaotic in the best way with a full squad. Gravity flipping mid-firefight never stops being funny. Docked one star because extractions can drag when matchmaking is quiet.'),
    array(1, 3, 5, 'Beautiful, calm, and smarter than it looks. The tide-threading puzzles in the final act are some of the best I have seen.'),
    array(1, 7, 4, 'Tight movement and a genuinely clever map that changes as you heal the machine. The last hour is special.'),
);
foreach ($reviews as $r) {
    save_review($conn, $r[0], $r[1], $r[2], $r[3]);
}

echo "Done. Seeded 2 users, " . count($games) . " games, " . count($reviews) . " reviews.\n";
