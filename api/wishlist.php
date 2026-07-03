<?php
require_once 'config.php';
require_login($db);
$user = current_user($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $game_id = $_POST['game_id'] ?? '';
    $wish_id = $user['id'] . '_' . $game_id;

    if ($action === 'remove') {
        $db->deleteDocument('wishlists', $wish_id);
        set_flash('Removed from wishlist.');
    }
    header('Location: wishlist.php');
    exit;
}

$all_wishlists = $db->getCollection('wishlists');
$items = [];

foreach ($all_wishlists as $wish) {
    if ($wish['user_id'] === $user['id']) {
        $game = $db->getDocument('games', $wish['game_id']);
        if ($game) $items[] = $game;
    }
}

$page_title = 'Wishlist — WASD';
include 'includes/header.php';
?>

<section class="page-head">
  <div class="wrap">
    <span class="eyebrow">Your collection</span>
    <h1 class="page-title">Wishlist</h1>

    <?php if (count($items) === 0): ?>
      <div class="empty">Your wishlist is empty.</div>
    <?php else: ?>
      <div class="games-grid wide">
        <?php foreach ($items as $g): ?>
          <div class="game-card">
            <div class="game-info">
              <h3><?php echo e($g['title']); ?></h3>
              <div class="genre"><?php echo e($g['genre']); ?></div>
              <form method="post" action="wishlist.php">
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="game_id" value="<?php echo e($g['id']); ?>">
                <button class="btn danger tiny" type="submit">Remove</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php include 'includes/footer.php'; ?>