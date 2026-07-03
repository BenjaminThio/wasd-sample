<?php
require_once 'config.php';
require_login($db);
$user = current_user($db);

$all_carts = $db->getCollection('carts');
$rows = [];
$total = 0;

foreach ($all_carts as $cart) {
    if ($cart['user_id'] === $user['id']) {
        $game = $db->getDocument('games', $cart['game_id']);
        if ($game) {
            $game['quantity'] = $cart['quantity'];
            $game['unit_final'] = final_price($game['price'], $game['discount'] ?? 0);
            $game['line_total'] = $game['unit_final'] * $game['quantity'];
            $total += $game['line_total'];
            $rows[] = $game;
        }
    }
}

/* ---------- place the order ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'place_order') {
    if (count($rows) === 0) {
        set_flash('Your cart is empty — nothing to check out.', 'error');
        header('Location: cart.php');
        exit;
    }

    $order_id = 'order_' . uniqid();
    
    // Save the main order metadata document
    $db->saveDocument('orders', $order_id, [
        'user_id' => $user['id'],
        'total' => (float)$total,
        'created_at' => date('c')
    ]);

    // Save individual sub-collection entries for order items
    foreach ($rows as $row) {
        $item_id = $order_id . '_' . $row['id'];
        $db->saveDocument('order_items', $item_id, [
            'order_id' => $order_id,
            'game_id' => $row['id'],
            'title' => $row['title'],
            'price' => (float)$row['unit_final'],
            'quantity' => (int)$row['quantity']
        ]);

        // Clean up cart item
        $cart_id = $user['id'] . '_' . $row['id'];
        $db->deleteDocument('carts', $cart_id);
    }

    // Reset user cart badge tracker count
    $db->saveDocument('users', $user['id'], [
        'cart_item_count' => 0
    ]);

    header('Location: checkout.php?done=' . $order_id);
    exit;
}

$done_order = null;
$done_items = [];
if (isset($_GET['done'])) {
    $done_order = $db->getDocument('orders', $_GET['done']);
    if ($done_order && $done_order['user_id'] === $user['id']) {
        $all_items = $db->getCollection('order_items');
        foreach ($all_items as $it) {
            if ($it['order_id'] === $done_order['id']) {
                $done_items[] = $it;
            }
        }
    }
}

$page_title = 'Checkout — WASD';
include 'includes/header.php';
?>

<section class="page-head">
  <div class="wrap">

    <?php if ($done_order): ?>
      <span class="eyebrow">Order confirmed</span>
      <h1 class="page-title">You're in. Game on.</h1>
      <p class="section-sub">Order <strong style="color:var(--cyan)">#<?php echo e($done_order['id']); ?></strong>. Your games are ready.</p>

      <div class="table-wrap" style="max-width:760px">
        <table>
          <thead><tr><th>Game</th><th>Price paid</th><th>Qty</th><th>Line total</th></tr></thead>
          <tbody>
            <?php foreach ($done_items as $it): ?>
              <tr>
                <td><strong><?php echo e($it['title']); ?></strong></td>
                <td><span class="price"><?php echo rm($it['price']); ?></span></td>
                <td><?php echo (int)$it['quantity']; ?></td>
                <td><span class="price"><?php echo rm($it['price'] * $it['quantity']); ?></span></td>
              </tr>
            <?php endforeach; ?>
            <tr>
              <td colspan="3" style="text-align:right;font-weight:600">Total charged</td>
              <td><span class="price" style="font-size:16px"><?php echo rm($done_order['total']); ?></span></td>
            </tr>
          </tbody>
        </table>
      </div>

    <?php elseif (count($rows) === 0): ?>
      <span class="eyebrow">Checkout</span>
      <h1 class="page-title">Nothing to check out</h1>
      <div class="empty">Your cart is empty. Find something in <a href="games.php">the store</a> first.</div>

    <?php else: ?>
      <span class="eyebrow">Checkout</span>
      <h1 class="page-title">Review your order</h1>

      <div class="table-wrap" style="max-width:760px">
        <table>
          <thead><tr><th>Game</th><th>Unit price</th><th>Qty</th><th>Line total</th></tr></thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
              <tr>
                <td><strong><?php echo e($row['title']); ?></strong></td>
                <td><span class="price"><?php echo rm($row['unit_final']); ?></span></td>
                <td><?php echo (int)$row['quantity']; ?></td>
                <td><span class="price"><?php echo rm($row['line_total']); ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="card static summary-box" style="max-width:420px">
        <div class="summary-row total"><span>Total</span><span class="price"><?php echo rm($total); ?></span></div>
        <form method="post" action="checkout.php" style="margin-top:18px">
          <input type="hidden" name="action" value="place_order">
          <button class="btn full" type="submit">Place order — <?php echo rm($total); ?></button>
        </form>
      </div>
    <?php endif; ?>

  </div>
</section>

<?php include 'includes/footer.php'; ?>