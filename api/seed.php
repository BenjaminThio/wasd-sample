<?php
require_once 'config.php';

echo "<h1>Initializing Firestore Database...</h1>";
echo "<ul>";

// 1. Seed Users (with forced IDs 1 and 2 to match the SQL relationships)
$users = [
    '1' => [
        'username' => 'admin',
        'email' => 'admin@wasd.com',
        'password_hash' => '$2y$10$C6FUoIUn.dy9veJxWBEG3O1/1a/B2B4z3ffz.r0Rosqxa.VQT8HUO',
        'avatar' => '🕹️',
        'favorite_genres' => 'RPG,Strategy',
        'is_admin' => 1,
        'cart_item_count' => 0
    ],
    '2' => [
        'username' => 'PixelArif',
        'email' => 'arif@example.com',
        'password_hash' => '$2y$10$wngcvLVuahjiZwSYvjdcvesfbZwd7W5rixPUWltZHqgjsGknhB5A.',
        'avatar' => '👾',
        'favorite_genres' => 'RPG,Roguelike,Indie',
        'is_admin' => 0,
        'cart_item_count' => 0
    ]
];

foreach ($users as $id => $data) {
    $db->saveDocument('users', $id, $data);
    echo "<li>Added User: {$data['username']}</li>";
}

// 2. Seed Games (Pre-calculating avg_rating since Firestore can't do SQL AVG() on the fly)
$games = [
    '1' => ['title' => 'Neon Drift Protocol', 'developer' => 'Hyperline Studio', 'genre' => 'Racing', 'description' => 'Neon Drift Protocol drops you into the underground racing circuit of Bassline City, where anti-gravity rigs scream through rain-soaked highways at 400 km/h. Build your machine from salvaged parts, earn a reputation across five rival districts, and outrun the corporate enforcement drones that patrol the skyline. Features a fully dynamic weather system, a synthwave soundtrack with 40 licensed tracks, and an 8-player online drift league with weekly seasons.', 'price' => 129.00, 'discount' => 0, 'badge' => 'New release', 'art' => 'art-1', 'release_year' => 2026],
    '2' => ['title' => 'Emberfall', 'developer' => 'Ashlight Interactive', 'genre' => 'Action RPG', 'description' => 'The kingdom of Vellin burned in a single night — then time stopped. In Emberfall you play a Cindermage who walks freely through the frozen moment, unraveling what happened one ember at a time. Explore a seamless open world locked in the instant of disaster, manipulate suspended fire and falling debris to solve traversal puzzles, and face guardians who remember how to move. Over 60 hours of main story, six companion arcs, and a New Game+ that rewinds the catastrophe differently.', 'price' => 199.00, 'discount' => 50, 'badge' => 'Best seller', 'art' => 'art-2', 'release_year' => 2025, 'avg_rating' => 5.0],
    '3' => ['title' => 'Tidewalker', 'developer' => 'Lighthouse Nine', 'genre' => 'Adventure', 'description' => 'Tidewalker is a hand-painted puzzle adventure about a lighthouse keeper\'s daughter who can pull the ocean like a thread. Rethread tides to raise sunken villages, calm leviathans, and guide lost ships home. No combat, no fail states — just the sea, its music, and puzzles that reshape entire coastlines. Winner of three independent game festival awards for art direction and original score.', 'price' => 59.00, 'discount' => 0, 'badge' => 'Indie pick', 'art' => 'art-3', 'release_year' => 2025, 'avg_rating' => 5.0],
    '4' => ['title' => 'Voidbound Legion', 'developer' => 'Redshift Forge', 'genre' => 'Co-op Shooter', 'description' => 'Voidbound Legion is a squad-based co-op shooter where four mercenaries take contracts in a star system falling into a black hole. Gravity shifts mid-fight, light bends around the horizon, and every mission ends with an extraction you have to earn. Deep class customization, procedurally assembled strike zones, and a physics engine built around orbital decay. Crossplay supported on all platforms.', 'price' => 159.00, 'discount' => 75, 'badge' => 'Top deal', 'art' => 'art-4', 'release_year' => 2024, 'avg_rating' => 4.0],
    '5' => ['title' => 'Ashen Crown', 'developer' => 'Duskforge Games', 'genre' => 'Souls-like', 'description' => 'Descend beneath the Glasspeak, where a dead king\'s crown still commands an army of vitrified knights. Ashen Crown is a punishing action RPG with weighty combat, stance-based dueling, and a world that rearranges itself each time you die. Forge weapons from the glass of fallen bosses, and decide whether to shatter the crown — or wear it.', 'price' => 179.00, 'discount' => 0, 'badge' => 'Pre-order', 'art' => 'art-5', 'release_year' => 2026],
    '6' => ['title' => 'Starfall Tactics', 'developer' => 'Quiet Orbit', 'genre' => 'Strategy', 'description' => 'Command a constellation in Starfall Tactics, a turn-based strategy game where units descend from orbit as guided starfall. Position your descent lanes, chain impact combos, and reshape the battlefield with craters of your own making. A 30-mission campaign, a map editor, and ranked asynchronous multiplayer where turns can be played from any device.', 'price' => 89.00, 'discount' => 30, 'badge' => '', 'art' => 'art-6', 'release_year' => 2024],
    '7' => ['title' => 'Hollow Circuit', 'developer' => 'Glitchmoth', 'genre' => 'Metroidvania', 'description' => 'The machine god Kirel has one hour left to live — from the inside, that hour is your whole world. Hollow Circuit is a metroidvania set inside a colossal dying computer, where you play a repair daemon fighting corrupted processes for control of failing subsystems. Every ability you recover restores part of the god — and changes the map. Praised for its precise movement, haunting chiptune-orchestral score, and a final hour players still argue about.', 'price' => 69.00, 'discount' => 0, 'badge' => 'Trending', 'art' => 'art-7', 'release_year' => 2025, 'avg_rating' => 4.0],
    '8' => ['title' => 'Mirefen', 'developer' => 'Bogwitch Collective', 'genre' => 'Horror', 'description' => 'The fen took your sister seven years ago. Now it\'s calling you back. Mirefen is a slow-burn folk-horror survival game set in an endless marsh that rearranges itself around your fears. Manage light, warmth, and sanity; barter with things that wear familiar faces; and map a place that refuses to be mapped. Features an adaptive dread system that learns what unsettles you — and leans in.', 'price' => 99.00, 'discount' => 60, 'badge' => '', 'art' => 'art-8', 'release_year' => 2023]
];

foreach ($games as $id => $data) {
    $db->saveDocument('games', $id, $data);
    echo "<li>Added Game: {$data['title']}</li>";
}

// 3. Seed Reviews
$reviews = [
    'rev_1' => ['user_id' => '2', 'game_id' => '2', 'rating' => 5, 'comment' => 'The frozen-time open world is unreal. Walking through a suspended explosion and rearranging the debris to climb a tower is the coolest traversal I have played in years.'],
    'rev_2' => ['user_id' => '2', 'game_id' => '4', 'rating' => 4, 'comment' => 'Chaotic in the best way with a full squad. Gravity flipping mid-firefight never stops being funny. Docked one star because extractions can drag when matchmaking is quiet.'],
    'rev_3' => ['user_id' => '1', 'game_id' => '3', 'rating' => 5, 'comment' => 'Beautiful, calm, and smarter than it looks. The tide-threading puzzles in the final act are some of the best I have seen.'],
    'rev_4' => ['user_id' => '1', 'game_id' => '7', 'rating' => 4, 'comment' => 'Tight movement and a genuinely clever map that changes as you heal the machine. The last hour is special.']
];

foreach ($reviews as $id => $data) {
    $db->saveDocument('reviews', $id, $data);
    echo "<li>Added Review for Game ID: {$data['game_id']}</li>";
}

echo "</ul>";
echo "<h2>✅ Success! All seed data has been pushed to Firestore.</h2>";
echo "<p style='color:red;'><b>IMPORTANT:</b> Delete this seed.php file and commit the deletion to GitHub so no one else can overwrite your database!</p>";
?>