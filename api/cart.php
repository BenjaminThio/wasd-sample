<?php
require_once 'config.php';
require_login($db);
$user = current_user($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $game_id = $_POST['game_id'] ?? '';
    $cart_id = $user['id'] . '_' . $game_id;

    if ($action === 'remove') {
        $db->deleteDocument('carts', $cart_id);
        $db->saveDocument('users', $user['id'], [
            'cart_item_count' => max(0, (int)($user['cart_item_count'] ?? 1) - 1)
        ]);
        set_flash('Removed from cart.');
    }
    header('Location: cart.php');
    exit;
}

$all_carts = $db->getCollection('carts');
$rows = [];
$subtotal = 0; $savings = 0;

foreach ($all_carts as $cart) {
    if ($cart['user_id'] === $user['id']) {
        $game = $db->getDocument('games', $cart['game_id']);
        if ($game) {
            $game['quantity'] = $cart['quantity'];
            $game['unit_final'] = final_price($game['price'], $game['discount'] ?? 0);
            $game['line_total'] = $game['unit_final'] * $game['quantity'];
            
            $subtotal += $game['price'] * $game['quantity'];
            $savings += ($game['price'] - $game['unit_final']) * $game['quantity'];
            $rows[] = $game;
        }
    }
}
$total = $subtotal - $savings;

$page_title = 'Your cart — WASD';
include 'includes/header.php';
?>

<section class="page-head">
  <div class="wrap">
    <span class="eyebrow">Your collection</span>
    <h1 class="page-title">Shopping cart</h1>

    <?php if (count($rows) === 0): ?>
      <div class="empty">Your cart is empty. Head to <a href="games.php">the store</a> and find your next obsession.</div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Game</th><th>Unit price</th><th>Quantity</th><th>Line total</th><th></th></tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
              <tr>
                <td>
                  <div class="cell-game">
                    <span class="thumb <?php echo e($row['art'] ?? 'art-1'); ?>"></span>
                    <span><strong><?php echo e($row['title']); ?></strong></span>
                  </div>
                </td>
                <td><span class="price"><?php echo rm($row['unit_final']); ?></span></td>
                <td><?php echo (int)$row['quantity']; ?></td>
                <td><span class="price"><?php echo rm($row['line_total']); ?></span></td>
                <td>
                  <form method="post" action="cart.php">
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="game_id" value="<?php echo e($row['id']); ?>">
                    <button class="btn danger tiny" type="submit">Remove</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="card static summary-box" style="max-width:420px;margin-left:auto">
        <div class="summary-row"><span>Subtotal</span><span class="price"><?php echo rm($subtotal); ?></span></div>
        <div class="summary-row"><span>Discounts</span><span class="price" style="color:var(--magenta)">− <?php echo rm($savings); ?></span></div>
        <div class="summary-row total"><span>Total</span><span class="price"><?php echo rm($total); ?></span></div>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php include 'includes/footer.php'; ?>