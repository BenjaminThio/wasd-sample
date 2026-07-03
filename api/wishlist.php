<?php
require_once 'config.php';
require_login($conn);
$user = current_user($conn);

/* ---------- wishlist actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = isset($_POST['action'])  ? $_POST['action']  : '';
    $item_id = isset($_POST['item_id']) ? $_POST['item_id'] : '';

    /* DELETE: remove from wishlist */
    if ($action === 'remove') {
        remove_wishlist_item($conn, $item_id, $user['id']);
        set_flash('Removed from wishlist.');
    }

    /* CREATE + DELETE: move to cart */
    if ($action === 'move_to_cart') {
        $wish = $conn->get('wishlist_items', $item_id);
        if ($wish && (int)$wish['user_id'] === (int)$user['id']) {
            add_to_cart($conn, $user['id'], $wish['game_id']);
            remove_wishlist_item($conn, $item_id, $user['id']);
            set_flash('Moved to your cart.');
        }
    }

    header('Location: wishlist.php');
    exit;
}

/* ---------- fetch wishlist ---------- */
$items = get_wishlist_items($conn, $user['id']);

$page_title = 'Wishlist — WASD';
include 'includes/header.php';
?>

<section class="page-head">
  <div class="wrap">
    <span class="eyebrow">Your collection</span>
    <h1 class="page-title">Wishlist</h1>
    <p class="section-sub">Games you're watching. Move them to your cart when the price is right.</p>

    <?php if (count($items) === 0): ?>
      <div class="empty">
        Nothing saved yet. Browse <a href="games.php">the store</a> and hit “Save to wishlist” on anything that catches your eye.
      </div>
    <?php else: ?>
      <div class="games-grid wide" style="margin-top:36px">
        <?php foreach ($items as $g): ?>
          <div class="game-card reveal">
            <a href="game.php?id=<?php echo (int)$g['id']; ?>" style="text-decoration:none;color:inherit">
              <div class="game-art <?php echo e($g['art']); ?>">
                <?php if (!empty($g['badge'])): ?><span class="tag"><?php echo e($g['badge']); ?></span><?php endif; ?>
              </div>
            </a>
            <div class="game-info">
              <h3><?php echo e($g['title']); ?></h3>
              <div class="genre"><?php echo e($g['genre']); ?> · added <?php echo ts_fmt($g['added_at'], 'j M Y'); ?></div>
              <div class="game-price">
                <span class="price">
                  <?php if ($g['discount'] > 0): ?>
                    <span class="old"><?php echo rm($g['price']); ?></span><?php echo rm(final_price($g['price'], $g['discount'])); ?>
                  <?php else: ?>
                    <?php echo rm($g['price']); ?>
                  <?php endif; ?>
                </span>
                <?php if ($g['discount'] > 0): ?><span class="off">−<?php echo (int)$g['discount']; ?>%</span><?php endif; ?>
              </div>
              <div style="display:flex;gap:10px;margin-top:16px">
                <form method="post" action="wishlist.php" style="flex:1">
                  <input type="hidden" name="action" value="move_to_cart">
                  <input type="hidden" name="item_id" value="<?php echo e($g['item_id']); ?>">
                  <button class="btn tiny full" type="submit">Move to cart</button>
                </form>
                <form method="post" action="wishlist.php">
                  <input type="hidden" name="action" value="remove">
                  <input type="hidden" name="item_id" value="<?php echo e($g['item_id']); ?>">
                  <button class="btn danger tiny" type="submit">Remove</button>
                </form>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
