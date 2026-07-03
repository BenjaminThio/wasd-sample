<?php
require_once 'config.php';
require_admin($conn);
$user = current_user($conn);

/* ---------- admin actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* DELETE: remove a game from the catalog */
    if ($action === 'delete_game') {
        $game_id = (int)($_POST['game_id'] ?? 0);
        delete_game($conn, $game_id);
        set_flash('Game removed from the catalog.');
    }

    /* DELETE: clear a handled contact message */
    if ($action === 'delete_message') {
        $msg_id = $_POST['message_id'] ?? '';
        delete_message($conn, $msg_id);
        set_flash('Message deleted.');
    }

    header('Location: admin.php');
    exit;
}

/* ---------- catalog (READ) ---------- */
$games = get_all_games($conn);
usort($games, function ($a, $b) { return strcasecmp($a['title'], $b['title']); });

/* ---------- inbox (READ) ---------- */
$inbox = get_all_messages($conn);

/* quick stats */
$all_orders = $conn->all('orders');
$all_users = $conn->all('users');
$order_revenue = 0;
foreach ($all_orders as $o) $order_revenue += (float)$o['total'];

$page_title = 'Admin panel — WASD';
include 'includes/header.php';
?>

<section class="page-head">
  <div class="wrap">
    <span class="eyebrow">Store admin</span>
    <h1 class="page-title">Admin panel</h1>
    <p class="section-sub">Manage the catalog and read player inquiries. Signed in as <?php echo e($user['username']); ?>.</p>

    <div class="hero-meta" style="margin-top:30px;justify-content:flex-start">
      <div><strong><?php echo count($games); ?></strong><small>Games listed</small></div>
      <div><strong><?php echo count($all_users); ?></strong><small>Players</small></div>
      <div><strong><?php echo count($all_orders); ?></strong><small>Orders</small></div>
      <div><strong><?php echo rm($order_revenue); ?></strong><small>Revenue</small></div>
    </div>
  </div>
</section>

<!-- CATALOG MANAGEMENT -->
<section style="padding-top:0">
  <div class="wrap">
    <div class="games-head" style="display:flex;justify-content:space-between;align-items:flex-end;gap:20px;flex-wrap:wrap">
      <div>
        <span class="eyebrow">Catalog</span>
        <h2>Manage games</h2>
      </div>
      <a class="btn small" href="admin_edit.php">+ Add new game</a>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Game</th><th>Genre</th><th>Price</th><th>Discount</th><th>Rating</th><th style="width:170px">Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($games as $g): ?>
            <tr>
              <td>
                <div class="cell-game">
                  <span class="thumb <?php echo e($g['art']); ?>"></span>
                  <span>
                    <strong><?php echo e($g['title']); ?></strong>
                    <small><?php echo e($g['developer']); ?> · <?php echo (int)$g['release_year']; ?></small>
                  </span>
                </div>
              </td>
              <td><?php echo e($g['genre']); ?></td>
              <td><span class="price"><?php echo rm($g['price']); ?></span></td>
              <td>
                <?php if ($g['discount'] > 0): ?>
                  <span class="off">−<?php echo (int)$g['discount']; ?>%</span>
                <?php else: ?>
                  <span class="badge">None</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($g['avg_rating'] !== null): ?>
                  ★ <?php echo number_format($g['avg_rating'], 1); ?> (<?php echo (int)$g['review_count']; ?>)
                <?php else: ?>
                  <span class="badge">No reviews</span>
                <?php endif; ?>
              </td>
              <td>
                <div style="display:flex;gap:9px">
                  <a class="btn ghost tiny" href="admin_edit.php?id=<?php echo (int)$g['id']; ?>">Edit</a>
                  <form method="post" action="admin.php" class="inline-form"
                        data-confirm="Delete “<?php echo e($g['title']); ?>” and all its reviews, cart, and wishlist entries?">
                    <input type="hidden" name="action" value="delete_game">
                    <input type="hidden" name="game_id" value="<?php echo (int)$g['id']; ?>">
                    <button class="btn danger tiny" type="submit">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>

<!-- CONTACT INBOX -->
<section style="padding-top:0">
  <div class="wrap">
    <span class="eyebrow">Inbox</span>
    <h2>Contact messages</h2>

    <?php if (count($inbox) === 0): ?>
      <div class="empty">No player messages right now. Inbox zero — enjoy it while it lasts.</div>
    <?php else: ?>
      <div class="review-list">
        <?php foreach ($inbox as $m): ?>
          <article class="card static review">
            <div class="review-head">
              <strong><?php echo e($m['subject']); ?></strong>
              <span class="badge"><?php echo e($m['name']); ?> · <?php echo e($m['email']); ?></span>
              <time><?php echo ts_fmt($m['created_at'], 'j M Y, g:i a'); ?></time>
            </div>
            <p><?php echo nl2br(e($m['message'])); ?></p>
            <div class="review-actions">
              <a class="btn ghost tiny" href="mailto:<?php echo e($m['email']); ?>?subject=Re:%20<?php echo rawurlencode($m['subject']); ?>">Reply by email</a>
              <form method="post" action="admin.php" data-confirm="Delete this message?">
                <input type="hidden" name="action" value="delete_message">
                <input type="hidden" name="message_id" value="<?php echo e($m['_id']); ?>">
                <button class="btn danger tiny" type="submit">Delete</button>
              </form>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
