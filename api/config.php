<?php
/* ============================================================
   WASD Game Store — configuration + shared helpers
   Included at the top of every page.

   MIGRATED: MySQL/WampServer -> Google Cloud Firestore, so this
   can run on Vercel (which has no persistent MySQL server).

   $conn is now a Firestore client instance (see lib/Firestore.php)
   instead of a mysqli connection. All page files were updated to
   call the helper functions below instead of raw SQL.
   ============================================================ */

require_once __DIR__ . '/lib/Firestore.php';
require_once __DIR__ . '/lib/FirestoreSessionHandler.php';

/* ---------- Firestore connection ----------
   Set these as environment variables (in Vercel: Project Settings
   -> Environment Variables). Never commit real credentials.
   FIRESTORE_PROJECT_ID   = your Firebase/GCP project id
   FIRESTORE_CLIENT_EMAIL = the service account's client_email
   FIRESTORE_PRIVATE_KEY  = the service account's private_key
                             (keep the \n sequences; the client
                             converts them back to real newlines) */
$conn = Firestore::fromEnv();

/* Sessions are stored in Firestore (not local disk) because Vercel
   serverless functions don't share a filesystem between invocations —
   the default file-based session handler would randomly "forget" that
   you're logged in as soon as a different function instance picks up
   your next request. Must be registered before session_start(). */
session_set_save_handler(new FirestoreSessionHandler($conn), true);
session_start();

/* Timestamps are stored as plain unix epoch integers in Firestore
   (simpler + cheaper to decode than Firestore's native timestamp
   type). Use this instead of the old strtotime($row['created_at']). */
function ts_fmt($epoch, $format) {
    if (!$epoch) return '';
    return date($format, (int)$epoch);
}

/* ---------- helpers ---------- */

/** Escape output for safe HTML rendering. */
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/** Currently logged-in user row, or null. Cached per request. */
function current_user($conn) {
    static $user = false;
    if ($user !== false) return $user;
    $user = null;
    if (isset($_SESSION['user_id'])) {
        $user = $conn->get('users', (string)$_SESSION['user_id']);
        if ($user === null) {
            unset($_SESSION['user_id']); // stale session
        } else {
            $user['id'] = (int)$user['_id'];
        }
    }
    return $user;
}

/** Redirect guests to the login page, remembering where they came from. */
function require_login($conn) {
    if (!current_user($conn)) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        set_flash('Log in to continue.', 'error');
        header('Location: login.php');
        exit;
    }
}

/** Redirect non-admins away from admin pages. */
function require_admin($conn) {
    $user = current_user($conn);
    if (!$user || !$user['is_admin']) {
        set_flash('That area is for store admins.', 'error');
        header('Location: index.php');
        exit;
    }
}

/** One-shot flash message ('success' | 'error' | 'info'). */
function set_flash($message, $type = 'success') {
    $_SESSION['flash'] = array('message' => $message, 'type' => $type);
}
function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/** Number of items in the logged-in user's cart (for the nav badge). */
function cart_count($conn) {
    $user = current_user($conn);
    if (!$user) return 0;
    $items = $conn->where('cart_items', 'user_id', 'EQUAL', (int)$user['_id']);
    $n = 0;
    foreach ($items as $i) $n += (int)$i['quantity'];
    return $n;
}

/** Final price after discount. */
function final_price($price, $discount) {
    return $discount > 0 ? $price * (100 - $discount) / 100 : $price;
}

/** Format a price in Malaysian Ringgit. */
function rm($amount) {
    return 'RM ' . number_format((float)$amount, 2);
}

/** Render 1-5 star rating string. */
function stars($rating) {
    $rating = (int)round($rating);
    return str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
}

/* ============================================================
   GAMES
   ============================================================ */

/** All games, each annotated with avg_rating / review_count (computed in PHP). */
function get_all_games($conn) {
    $games = $conn->all('games');
    $reviews = $conn->all('reviews');

    $stats = array(); // game_id => [sum, count]
    foreach ($reviews as $r) {
        $gid = (int)$r['game_id'];
        if (!isset($stats[$gid])) $stats[$gid] = array('sum' => 0, 'count' => 0);
        $stats[$gid]['sum'] += (int)$r['rating'];
        $stats[$gid]['count']++;
    }

    foreach ($games as &$g) {
        $g['id'] = (int)$g['_id'];
        $gid = $g['id'];
        if (isset($stats[$gid])) {
            $g['avg_rating'] = $stats[$gid]['sum'] / $stats[$gid]['count'];
            $g['review_count'] = $stats[$gid]['count'];
        } else {
            $g['avg_rating'] = null;
            $g['review_count'] = 0;
        }
        $g['final_price'] = final_price($g['price'], $g['discount']);
    }
    unset($g);
    return $games;
}

/** One game by id, with avg_rating / review_count, or null. */
function get_game($conn, $game_id) {
    $game = $conn->get('games', (string)$game_id);
    if (!$game) return null;
    $game['id'] = (int)$game['_id'];
    $reviews = $conn->where('reviews', 'game_id', 'EQUAL', (int)$game_id);
    if ($reviews) {
        $sum = 0;
        foreach ($reviews as $r) $sum += (int)$r['rating'];
        $game['avg_rating'] = $sum / count($reviews);
        $game['review_count'] = count($reviews);
    } else {
        $game['avg_rating'] = null;
        $game['review_count'] = 0;
    }
    return $game;
}

function create_game($conn, $data) {
    $id = $conn->nextId('games');
    $data['created_at'] = time();
    $conn->set('games', (string)$id, $data);
    return $id;
}

function update_game($conn, $game_id, $data) {
    $conn->update('games', (string)$game_id, $data);
}

/** Deletes a game and cascades to reviews / cart / wishlist entries that reference it
    (Firestore has no foreign keys, so this is done manually — same effect as the old
    ON DELETE CASCADE constraints). */
function delete_game($conn, $game_id) {
    $game_id = (int)$game_id;
    foreach ($conn->where('reviews', 'game_id', 'EQUAL', $game_id) as $r) $conn->delete('reviews', $r['_id']);
    foreach ($conn->where('cart_items', 'game_id', 'EQUAL', $game_id) as $c) $conn->delete('cart_items', $c['_id']);
    foreach ($conn->where('wishlist_items', 'game_id', 'EQUAL', $game_id) as $w) $conn->delete('wishlist_items', $w['_id']);
    $conn->delete('games', (string)$game_id);
}

/* ============================================================
   USERS
   ============================================================ */

function get_user_by_email($conn, $email) {
    $rows = $conn->where('users', 'email', 'EQUAL', $email);
    if (!$rows) return null;
    $u = $rows[0];
    $u['id'] = (int)$u['_id'];
    return $u;
}

/** True if the username or email is already taken (optionally excluding one user id). */
function user_taken($conn, $username, $email, $exclude_id = 0) {
    foreach ($conn->where('users', 'username', 'EQUAL', $username) as $u) {
        if ((int)$u['_id'] !== (int)$exclude_id) return true;
    }
    foreach ($conn->where('users', 'email', 'EQUAL', $email) as $u) {
        if ((int)$u['_id'] !== (int)$exclude_id) return true;
    }
    return false;
}

function create_user($conn, $data) {
    $id = $conn->nextId('users');
    $data['is_admin'] = 0;
    $data['created_at'] = time();
    $conn->set('users', (string)$id, $data);
    return $id;
}

function update_user($conn, $user_id, $data) {
    $conn->update('users', (string)$user_id, $data);
}

/* ============================================================
   REVIEWS  (doc id = "{user_id}_{game_id}", enforcing one review
   per user per game — same guarantee as the old UNIQUE KEY)
   ============================================================ */

function review_doc_id($user_id, $game_id) {
    return $user_id . '_' . $game_id;
}

function get_review($conn, $user_id, $game_id) {
    return $conn->get('reviews', review_doc_id($user_id, $game_id));
}

/** All reviews for a game, newest first, each annotated with the reviewer's username/avatar. */
function get_reviews_for_game($conn, $game_id) {
    $reviews = $conn->where('reviews', 'game_id', 'EQUAL', (int)$game_id);
    foreach ($reviews as &$r) {
        $u = $conn->get('users', (string)$r['user_id']);
        $r['username'] = $u ? $u['username'] : 'Deleted user';
        $r['avatar'] = $u ? $u['avatar'] : '❔';
    }
    unset($r);
    usort($reviews, function ($a, $b) { return $b['created_at'] <=> $a['created_at']; });
    return $reviews;
}

/** All reviews written by a user, newest first, each annotated with the game title. */
function get_reviews_for_user($conn, $user_id) {
    $reviews = $conn->where('reviews', 'user_id', 'EQUAL', (int)$user_id);
    foreach ($reviews as &$r) {
        $g = $conn->get('games', (string)$r['game_id']);
        $r['title'] = $g ? $g['title'] : 'Deleted game';
    }
    unset($r);
    usort($reviews, function ($a, $b) { return $b['created_at'] <=> $a['created_at']; });
    return $reviews;
}

function save_review($conn, $user_id, $game_id, $rating, $comment) {
    $id = review_doc_id($user_id, $game_id);
    $existing = $conn->get('reviews', $id);
    $conn->set('reviews', $id, array(
        'user_id' => (int)$user_id,
        'game_id' => (int)$game_id,
        'rating' => (int)$rating,
        'comment' => $comment,
        'created_at' => $existing ? $existing['created_at'] : time(),
        'updated_at' => $existing ? time() : null,
    ));
}

function delete_review($conn, $review_id) {
    $conn->delete('reviews', $review_id);
}

/* ============================================================
   CART  (doc id = "{user_id}_{game_id}")
   ============================================================ */

function cart_doc_id($user_id, $game_id) {
    return $user_id . '_' . $game_id;
}

/** Cart rows joined with their game, plus computed unit_final / line_total. */
function get_cart_items($conn, $user_id) {
    $items = $conn->where('cart_items', 'user_id', 'EQUAL', (int)$user_id);
    usort($items, function ($a, $b) { return $b['added_at'] <=> $a['added_at']; });

    $rows = array();
    foreach ($items as $c) {
        $g = $conn->get('games', (string)$c['game_id']);
        if (!$g) continue; // game was deleted
        $g['id'] = (int)$g['_id'];
        $g['item_id'] = $c['_id'];
        $g['quantity'] = (int)$c['quantity'];
        $g['unit_final'] = final_price($g['price'], $g['discount']);
        $g['line_total'] = $g['unit_final'] * $g['quantity'];
        $rows[] = $g;
    }
    return $rows;
}

function add_to_cart($conn, $user_id, $game_id) {
    $id = cart_doc_id($user_id, $game_id);
    $existing = $conn->get('cart_items', $id);
    $qty = $existing ? (int)$existing['quantity'] + 1 : 1;
    $conn->set('cart_items', $id, array(
        'user_id' => (int)$user_id,
        'game_id' => (int)$game_id,
        'quantity' => $qty,
        'added_at' => $existing ? $existing['added_at'] : time(),
    ));
}

function update_cart_qty($conn, $item_id, $user_id, $qty) {
    // item ids are "{user_id}_{game_id}" — verify it actually belongs to this user
    if (strpos($item_id, $user_id . '_') !== 0) return;
    $conn->update('cart_items', $item_id, array('quantity' => $qty));
}

function remove_cart_item($conn, $item_id, $user_id) {
    if (strpos($item_id, $user_id . '_') !== 0) return;
    $conn->delete('cart_items', $item_id);
}

function clear_cart($conn, $user_id) {
    foreach ($conn->where('cart_items', 'user_id', 'EQUAL', (int)$user_id) as $c) {
        $conn->delete('cart_items', $c['_id']);
    }
}

/* ============================================================
   WISHLIST  (doc id = "{user_id}_{game_id}")
   ============================================================ */

function wishlist_doc_id($user_id, $game_id) {
    return $user_id . '_' . $game_id;
}

function get_wishlist_items($conn, $user_id) {
    $items = $conn->where('wishlist_items', 'user_id', 'EQUAL', (int)$user_id);
    usort($items, function ($a, $b) { return $b['added_at'] <=> $a['added_at']; });

    $rows = array();
    foreach ($items as $w) {
        $g = $conn->get('games', (string)$w['game_id']);
        if (!$g) continue;
        $g['id'] = (int)$g['_id'];
        $g['item_id'] = $w['_id'];
        $g['added_at'] = $w['added_at'];
        $rows[] = $g;
    }
    return $rows;
}

function is_in_wishlist($conn, $user_id, $game_id) {
    return $conn->get('wishlist_items', wishlist_doc_id($user_id, $game_id)) !== null;
}

function add_to_wishlist($conn, $user_id, $game_id) {
    $id = wishlist_doc_id($user_id, $game_id);
    if ($conn->get('wishlist_items', $id)) return; // already saved
    $conn->set('wishlist_items', $id, array(
        'user_id' => (int)$user_id,
        'game_id' => (int)$game_id,
        'added_at' => time(),
    ));
}

function remove_wishlist_item($conn, $item_id, $user_id) {
    if (strpos($item_id, $user_id . '_') !== 0) return;
    $conn->delete('wishlist_items', $item_id);
}

/* ============================================================
   ORDERS  (order_items are stored as an array field on the order
   document instead of a separate table/subcollection)
   ============================================================ */

function create_order($conn, $user_id, $total, $line_items) {
    $id = $conn->nextId('orders');
    $conn->set('orders', (string)$id, array(
        'user_id' => (int)$user_id,
        'total' => (float)$total,
        'created_at' => time(),
        'items' => $line_items, // [{game_id, title, price, quantity}, ...]
    ));
    return $id;
}

function get_order($conn, $order_id, $user_id) {
    $order = $conn->get('orders', (string)$order_id);
    if (!$order || (int)$order['user_id'] !== (int)$user_id) return null;
    $order['id'] = (int)$order['_id'];
    return $order;
}

function get_orders_for_user($conn, $user_id) {
    $orders = $conn->where('orders', 'user_id', 'EQUAL', (int)$user_id);
    foreach ($orders as &$o) $o['id'] = (int)$o['_id'];
    unset($o);
    usort($orders, function ($a, $b) { return $b['created_at'] <=> $a['created_at']; });
    return $orders;
}

/* ============================================================
   CONTACT MESSAGES
   ============================================================ */

function create_message($conn, $data) {
    $data['created_at'] = time();
    return $conn->add('messages', $data);
}

function get_all_messages($conn) {
    $messages = $conn->all('messages');
    usort($messages, function ($a, $b) { return $b['created_at'] <=> $a['created_at']; });
    return $messages;
}

function delete_message($conn, $id) {
    $conn->delete('messages', $id);
}
