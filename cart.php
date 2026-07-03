<?php
require_once 'config.php';
require_login($conn);
$user = current_user($conn);

/* ---------- handle cart updates ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $item_id = isset($_POST['item_id']) ? $_POST['item_id'] : '';

    /* UPDATE: change quantity */
    if ($action === 'update_qty') {
        $qty = isset($_POST['quantity']) ? max(1, min(99, (int)$_POST['quantity'])) : 1;
        update_cart_qty($conn, $item_id, $user['id'], $qty);
        set_flash('Cart updated.');
    }

    /* DELETE: remove one line */
    if ($action === 'remove') {
        remove_cart_item($conn, $item_id, $user['id']);
        set_flash('Removed from cart.');
    }

    /* DELETE: clear everything */
    if ($action === 'clear') {
        clear_cart($conn, $user['id']);
        set_flash('Cart cleared.');
    }

    header('Location: cart.php');
    exit;
}

/* ---------- fetch cart contents ---------- */
$rows = get_cart_items($conn, $user['id']);

$subtotal = 0; $savings = 0;
foreach ($rows as $row) {
    $subtotal += $row['price'] * $row['quantity'];
    $savings  += ($row['price'] - $row['unit_final']) * $row['quantity'];
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
      <div class="empty">
        Your cart is empty. Head to <a href="games.php">the store</a> and find your next obsession.
      </div>
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
                    <span class="thumb <?php echo e($row['art']); ?>"></span>
                    <span>
                      <strong><a href="game.php?id=<?php echo (int)$row['id']; ?>" style="color:inherit;text-decoration:none"><?php echo e($row['title']); ?></a></strong>
                      <small><?php echo e($row['genre']); ?></small>
                    </span>
                  </div>
                </td>
                <td>
                  <span class="price">
                    <?php if ($row['discount'] > 0): ?>
                      <span class="old"><?php echo rm($row['price']); ?></span><?php echo rm($row['unit_final']); ?>
                    <?php else: ?>
                      <?php echo rm($row['price']); ?>
                    <?php endif; ?>
                  </span>
                </td>
                <td>
                  <form method="post" action="cart.php" style="display:flex;gap:9px;align-items:center">
                    <input type="hidden" name="action" value="update_qty">
                    <input type="hidden" name="item_id" value="<?php echo e($row['item_id']); ?>">
                    <span class="qty-box">
                      <button class="btn ghost tiny" type="button" data-step="-1" aria-label="Decrease">−</button>
                      <input type="number" name="quantity" min="1" max="99" value="<?php echo (int)$row['quantity']; ?>">
                      <button class="btn ghost tiny" type="button" data-step="1" aria-label="Increase">+</button>
                    </span>
                    <button class="btn ghost tiny" type="submit">Save</button>
                  </form>
                </td>
                <td><span class="price"><?php echo rm($row['line_total']); ?></span></td>
                <td>
                  <form method="post" action="cart.php">
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="item_id" value="<?php echo e($row['item_id']); ?>">
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
        <a class="btn full" href="checkout.php" style="margin-top:18px">Proceed to checkout</a>
        <form method="post" action="cart.php" data-confirm="Remove every item from your cart?" style="margin-top:11px">
          <input type="hidden" name="action" value="clear">
          <button class="btn ghost full" type="submit">Clear cart</button>
        </form>
      </div>

    <?php endif; ?>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
