<?php
require_once 'config.php';

$game_id = $_GET['id'] ?? '';
$game = $db->getDocument('games', $game_id);

if (!$game) {
    set_flash('That game does not exist in the store.', 'error');
    header('Location: games.php');
    exit;
}

$user = current_user($db);
$in_wishlist = false;

if ($user) {
    // Look up items stored as references inside specific documents
    $wishlist_id = $user['id'] . '_' . $game_id;
    $wish_item = $db->getDocument('wishlists', $wishlist_id);
    if ($wish_item) $in_wishlist = true;
}

/* ---------- POST Actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$user) {
        $_SESSION['redirect_after_login'] = 'game.php?id=' . $game_id;
        set_flash('Log in to do that.', 'error');
        header('Location: login.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add_cart') {
        $cart_id = $user['id'] . '_' . $game_id;
        $existing = $db->getDocument('carts', $cart_id);
        $qty = $existing ? ((int)$existing['quantity'] + 1) : 1;

        $db->saveDocument('carts', $cart_id, [
            'user_id' => $user['id'],
            'game_id' => $game_id,
            'quantity' => $qty
        ]);

        if (!$existing) {
            $db->saveDocument('users', $user['id'], [
                'cart_item_count' => (int)($user['cart_item_count'] ?? 0) + 1
            ]);
        }
        set_flash('Added to your cart.');
    }

    if ($action === 'add_wishlist') {
        $wishlist_id = $user['id'] . '_' . $game_id;
        $db->saveDocument('wishlists', $wishlist_id, [
            'user_id' => $user['id'],
            'game_id' => $game_id
        ]);
        set_flash('Saved to your wishlist.');
    }

    header('Location: game.php?id=' . $game_id);
    exit;
}

$pay_now = final_price($game['price'], $game['discount'] ?? 0);
$page_title = $game['title'] . ' — WASD';
include 'includes/header.php';
?>

<section class="page-head" style="padding-bottom:0">
  <div class="wrap">
    <span class="eyebrow"><?php echo e($game['genre']); ?></span>
    <h1 class="page-title"><?php echo e($game['title']); ?></h1>

    <div class="detail-meta">
      <div><small>Developer</small><span><?php echo e($game['developer']); ?></span></div>
      <div><small>Released</small><span><?php echo (int)$game['release_year']; ?></span></div>
      <div><small>Player rating</small>
        <span>
          <?php if (isset($game['avg_rating'])): ?>
            ★ <?php echo number_format($game['avg_rating'], 1); ?>
          <?php else: ?>
            No reviews yet
          <?php endif; ?>
        </span>
      </div>
    </div>

    <div class="detail-grid">
      <div>
        <div class="game-art tall <?php echo e($game['art'] ?? 'art-1'); ?>">
          <?php if (!empty($game['badge'])): ?><span class="tag"><?php echo e($game['badge']); ?></span><?php endif; ?>
        </div>
        <h2 style="font-size:22px;margin-top:34px">About this game</h2>
        <p class="detail-desc"><?php echo nl2br(e($game['description'])); ?></p>
      </div>

      <aside class="card static buy-box">
        <span class="eyebrow">Get the game</span>
        <div class="price-line">
          <?php if (($game['discount'] ?? 0) > 0): ?>
            <span class="price"><span class="old"><?php echo rm($game['price']); ?></span></span>
            <span class="now"><?php echo rm($pay_now); ?></span>
            <span class="off">−<?php echo (int)$game['discount']; ?>%</span>
          <?php else: ?>
            <span class="now"><?php echo rm($game['price']); ?></span>
          <?php endif; ?>
        </div>

        <div class="actions">
          <form method="post" action="game.php?id=<?php echo e($game_id); ?>">
            <input type="hidden" name="action" value="add_cart">
            <button class="btn full" type="submit">Add to cart</button>
          </form>
          <?php if ($in_wishlist): ?>
            <a class="btn ghost full" href="wishlist.php">In your wishlist ✓</a>
          <?php else: ?>
            <form method="post" action="game.php?id=<?php echo e($game_id); ?>">
              <input type="hidden" name="action" value="add_wishlist">
              <button class="btn ghost full" type="submit">Save to wishlist</button>
            </form>
          <?php endif; ?>
        </div>
      </aside>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>