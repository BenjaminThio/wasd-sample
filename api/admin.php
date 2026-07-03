<?php
require_once 'config.php';
require_admin($db);
$user = current_user($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_game') {
        $game_id = $_POST['game_id'] ?? '';
        $db->deleteDocument('games', $game_id);
        set_flash('Game removed from catalog.');
    }
    header('Location: admin.php');
    exit;
}

$games = $db->getCollection('games');
$messages = $db->getCollection('messages');
$orders = $db->getCollection('orders');
$users = $db->getCollection('users');

$revenue = 0;
foreach ($orders as $o) {
    $revenue += (float)($o['total'] ?? 0);
}

$page_title = 'Admin panel — WASD';
include 'includes/header.php';
?>

<section class="page-head">
  <div class="wrap">
    <h1 class="page-title">Admin panel</h1>
    <div class="hero-meta" style="margin-top:30px;justify-content:flex-start">
      <div><strong><?php echo count($games); ?></strong><small>Games listed</small></div>
      <div><strong><?php echo count($users); ?></strong><small>Players</small></div>
      <div><strong><?php echo count($orders); ?></strong><small>Orders</small></div>
      <div><strong><?php echo rm($revenue); ?></strong><small>Revenue</small></div>
    </div>
  </div>
</section>

<section style="padding-top:0">
  <div class="wrap">
    <div style="display:flex;justify-content:space-between;margin-bottom:20px;">
        <h2>Manage games</h2>
        <a class="btn small" href="admin_edit.php">+ Add new game</a>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr><th>Game</th><th>Genre</th><th>Price</th><th style="width:170px">Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($games as $g): ?>
            <tr>
              <td><strong><?php echo e($g['title']); ?></strong></td>
              <td><?php echo e($g['genre'] ?? ''); ?></td>
              <td><span class="price"><?php echo rm($g['price']); ?></span></td>
              <td>
                <div style="display:flex;gap:9px">
                  <a class="btn ghost tiny" href="admin_edit.php?id=<?php echo e($g['id']); ?>">Edit</a>
                  <form method="post" action="admin.php" class="inline-form">
                    <input type="hidden" name="action" value="delete_game">
                    <input type="hidden" name="game_id" value="<?php echo e($g['id']); ?>">
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

<?php include 'includes/footer.php'; ?>