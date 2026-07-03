<?php
require_once 'config.php';
require_login($conn);
$user = current_user($conn);

/* ---------- fetch cart contents (needed for both GET and POST) ---------- */
$rows = get_cart_items($conn, $user['id']);
$total = 0;
foreach ($rows as $row) $total += $row['line_total'];

/* ---------- place the order ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'place_order') {
    if (count($rows) === 0) {
        set_flash('Your cart is empty — nothing to check out.', 'error');
        header('Location: cart.php');
        exit;
    }

    /* snapshot title + price paid per line, same as the old order_items table */
    $line_items = array();
    foreach ($rows as $row) {
        $line_items[] = array(
            'game_id'  => (int)$row['id'],
            'title'    => $row['title'],
            'price'    => (float)$row['unit_final'],
            'quantity' => (int)$row['quantity'],
        );
    }

    $order_id = create_order($conn, $user['id'], $total, $line_items);
    clear_cart($conn, $user['id']);

    header('Location: checkout.php?done=' . $order_id);
    exit;
}

/* ---------- order confirmation view ---------- */
$done_order = null;
if (isset($_GET['done'])) {
    $order_id = (int)$_GET['done'];
    $done_order = get_order($conn, $order_id, $user['id']);
}

$page_title = 'Checkout — WASD';
include 'includes/header.php';
?>

<section class="page-head">
  <div class="wrap">

    <?php if ($done_order): ?>
      <!-- ============ order placed ============ -->
      <span class="eyebrow">Order confirmed</span>
      <h1 class="page-title">You're in. Game on.</h1>
      <p class="section-sub">Order <strong style="color:var(--cyan)">#WASD-<?php echo str_pad($done_order['id'], 5, '0', STR_PAD_LEFT); ?></strong>
        placed on <?php echo ts_fmt($done_order['created_at'], 'j M Y, g:i a'); ?>.
        Your games are ready to download from your library.</p>

      <div class="table-wrap" style="max-width:760px">
        <table>
          <thead><tr><th>Game</th><th>Price paid</th><th>Qty</th><th>Line total</th></tr></thead>
          <tbody>
            <?php foreach ($done_order['items'] as $it): ?>
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

      <div class="hero-ctas" style="justify-content:flex-start;margin-top:34px">
        <a class="btn" href="games.php">Keep browsing</a>
        <a class="btn ghost" href="profile.php">View order history</a>
      </div>

    <?php elseif (count($rows) === 0): ?>
      <span class="eyebrow">Checkout</span>
      <h1 class="page-title">Nothing to check out</h1>
      <div class="empty">Your cart is empty. Find something in <a href="games.php">the store</a> first.</div>

    <?php else: ?>
      <!-- ============ review + confirm ============ -->
      <span class="eyebrow">Checkout</span>
      <h1 class="page-title">Review your order</h1>
      <p class="section-sub">One last look before the download buttons light up. This is a simulated payment for demo purposes — no card needed.</p>

      <div class="table-wrap" style="max-width:760px">
        <table>
          <thead><tr><th>Game</th><th>Unit price</th><th>Qty</th><th>Line total</th></tr></thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
              <tr>
                <td>
                  <div class="cell-game">
                    <span class="thumb <?php echo e($row['art']); ?>"></span>
                    <span><strong><?php echo e($row['title']); ?></strong><small><?php echo e($row['genre']); ?></small></span>
                  </div>
                </td>
                <td><span class="price"><?php echo rm($row['unit_final']); ?></span></td>
                <td><?php echo (int)$row['quantity']; ?></td>
                <td><span class="price"><?php echo rm($row['line_total']); ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="card static summary-box" style="max-width:420px">
        <div class="summary-row"><span>Billed to</span><span><?php echo e($user['username']); ?> (<?php echo e($user['email']); ?>)</span></div>
        <div class="summary-row total"><span>Total</span><span class="price"><?php echo rm($total); ?></span></div>
        <form method="post" action="checkout.php" style="margin-top:18px">
          <input type="hidden" name="action" value="place_order">
          <button class="btn full" type="submit">Place order — <?php echo rm($total); ?></button>
        </form>
        <a class="btn ghost full" href="cart.php" style="margin-top:11px">Back to cart</a>
      </div>
    <?php endif; ?>

  </div>
</section>

<?php include 'includes/footer.php'; ?>
