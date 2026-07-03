<?php
require_once 'config.php';
require_admin($db);

$errors = array();
$game_id = $_GET['id'] ?? '';
$game = [
    'title' => '', 'developer' => '', 'genre' => '', 'description' => '',
    'price' => 0.0, 'discount' => 0, 'art' => 'art-1', 'release_year' => (int)date('Y')
];

if (!empty($game_id)) {
    $found = $db->getDocument('games', $game_id);
    if (!$found) {
        set_flash('That game does not exist.', 'error');
        header('Location: admin.php');
        exit;
    }
    $game = $found;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $game['title']        = trim($_POST['title'] ?? '');
    $game['developer']    = trim($_POST['developer'] ?? '');
    $game['genre']        = trim($_POST['genre'] ?? '');
    $game['description']  = trim($_POST['description'] ?? '');
    $game['price']        = (float)($_POST['price'] ?? 0.0);
    $game['discount']     = (int)($_POST['discount'] ?? 0);
    $game['release_year'] = (int)($_POST['release_year'] ?? date('Y'));

    if ($game['title'] === '') $errors[] = 'Title is required.';
    if ($game['price'] < 0)    $errors[] = 'Price must be valid.';

    if (!$errors) {
        $target_id = empty($game_id) ? 'game_' . uniqid() : $game_id;
        
        $db->saveDocument('games', $target_id, [
            'title' => $game['title'],
            'developer' => $game['developer'],
            'genre' => $game['genre'],
            'description' => $game['description'],
            'price' => (float)$game['price'],
            'discount' => (int)$game['discount'],
            'release_year' => (int)$game['release_year'],
            'art' => $game['art'] ?? 'art-1',
            'avg_rating' => (float)($game['avg_rating'] ?? 5.0)
        ]);

        set_flash('Catalog changes successfully updated.');
        header('Location: admin.php');
        exit;
    }
}

$page_title = 'Edit game — WASD admin';
include 'includes/header.php';
?>

<section class="page-head">
  <div class="wrap">
    <div class="card static panel wide">
      <h1 class="page-title"><?php echo !empty($game_id) ? 'Edit Game' : 'Add Game'; ?></h1>

      <form method="post" action="admin_edit.php<?php echo !empty($game_id) ? '?id=' . $game_id : ''; ?>">
        <div class="field">
          <label>Title</label>
          <input type="text" name="title" value="<?php echo e($game['title']); ?>" data-required>
        </div>
        <div class="field">
          <label>Developer</label>
          <input type="text" name="developer" value="<?php echo e($game['developer']); ?>">
        </div>
        <div class="field">
          <label>Genre</label>
          <input type="text" name="genre" value="<?php echo e($game['genre']); ?>">
        </div>
        <div class="field">
          <label>Price (RM)</label>
          <input type="number" name="price" step="0.01" value="<?php echo e($game['price']); ?>">
        </div>
        <div class="field">
          <label>Discount (%)</label>
          <input type="number" name="discount" value="<?php echo (int)($game['discount'] ?? 0); ?>">
        </div>
        <div class="field">
          <label>Description</label>
          <textarea name="description"><?php echo e($game['description']); ?></textarea>
        </div>
        <button class="btn" type="submit">Save changes</button>
      </form>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>