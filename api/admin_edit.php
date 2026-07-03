<?php
require_once 'config.php';
require_admin($conn);

$arts = array('art-1', 'art-2', 'art-3', 'art-4', 'art-5', 'art-6', 'art-7', 'art-8');
$errors = array();

/* editing an existing game, or adding a new one? */
$game_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$game = array(
    'title' => '', 'developer' => '', 'genre' => '', 'short_desc' => '',
    'description' => '', 'price' => '', 'discount' => 0, 'badge' => '',
    'art' => 'art-1', 'release_year' => date('Y'),
);

if ($game_id > 0) {
    $found = get_game($conn, $game_id);
    if (!$found) {
        set_flash('That game does not exist.', 'error');
        header('Location: admin.php');
        exit;
    }
    $game = $found;
}

/* ---------- save (CREATE or UPDATE) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $game['title']        = trim($_POST['title'] ?? '');
    $game['developer']    = trim($_POST['developer'] ?? '');
    $game['genre']        = trim($_POST['genre'] ?? '');
    $game['short_desc']   = trim($_POST['short_desc'] ?? '');
    $game['description']  = trim($_POST['description'] ?? '');
    $game['price']        = $_POST['price'] ?? '';
    $game['discount']     = (int)($_POST['discount'] ?? 0);
    $game['badge']        = trim($_POST['badge'] ?? '');
    $game['art']          = $_POST['art'] ?? 'art-1';
    $game['release_year'] = (int)($_POST['release_year'] ?? date('Y'));

    if ($game['title'] === '')       $errors[] = 'Title is required.';
    if ($game['developer'] === '')   $errors[] = 'Developer is required.';
    if ($game['genre'] === '')       $errors[] = 'Genre is required.';
    if ($game['short_desc'] === '')  $errors[] = 'Short description is required.';
    if ($game['description'] === '') $errors[] = 'Full description is required.';
    if (!is_numeric($game['price']) || (float)$game['price'] < 0) $errors[] = 'Price must be 0 or more.';
    if ($game['discount'] < 0 || $game['discount'] > 99) $errors[] = 'Discount must be between 0 and 99.';
    if (!in_array($game['art'], $arts, true)) $game['art'] = 'art-1';
    if ($game['release_year'] < 1980 || $game['release_year'] > (int)date('Y') + 2) $errors[] = 'Release year looks wrong.';

    if (!$errors) {
        $price = (float)$game['price'];
        $badge = $game['badge'] === '' ? null : $game['badge'];

        $data = array(
            'title' => $game['title'],
            'developer' => $game['developer'],
            'genre' => $game['genre'],
            'short_desc' => $game['short_desc'],
            'description' => $game['description'],
            'price' => $price,
            'discount' => (int)$game['discount'],
            'badge' => $badge,
            'art' => $game['art'],
            'release_year' => (int)$game['release_year'],
        );

        if ($game_id > 0) {
            /* UPDATE */
            update_game($conn, $game_id, $data);
            set_flash('“' . $game['title'] . '” updated.');
        } else {
            /* CREATE */
            create_game($conn, $data);
            set_flash('“' . $game['title'] . '” added to the catalog.');
        }
        header('Location: admin.php');
        exit;
    }
}

$page_title = ($game_id > 0 ? 'Edit game' : 'Add game') . ' — WASD admin';
include 'includes/header.php';
?>

<section class="page-head">
  <div class="wrap">
    <div class="card static panel wide">
      <span class="eyebrow">Store admin</span>
      <h1 class="page-title" style="font-size:clamp(24px,3vw,32px)">
        <?php echo $game_id > 0 ? 'Edit “' . e($game['title']) . '”' : 'Add a new game'; ?>
      </h1>

      <?php if ($errors): ?>
        <div class="form-error"><?php echo implode('<br>', array_map('e', $errors)); ?></div>
      <?php endif; ?>

      <form method="post" action="admin_edit.php<?php echo $game_id > 0 ? '?id=' . $game_id : ''; ?>" data-validate>
        <div class="two-col" style="margin-top:0;gap:20px">
          <div class="field">
            <label for="title">Title</label>
            <input type="text" id="title" name="title" maxlength="120"
                   value="<?php echo e($game['title']); ?>" data-required data-label="Title">
          </div>
          <div class="field">
            <label for="developer">Developer</label>
            <input type="text" id="developer" name="developer" maxlength="120"
                   value="<?php echo e($game['developer']); ?>" data-required data-label="Developer">
          </div>
        </div>

        <div class="two-col" style="margin-top:0;gap:20px">
          <div class="field">
            <label for="genre">Genre</label>
            <input type="text" id="genre" name="genre" maxlength="60"
                   value="<?php echo e($game['genre']); ?>" data-required data-label="Genre"
                   placeholder="e.g. Action RPG">
          </div>
          <div class="field">
            <label for="release_year">Release year</label>
            <input type="number" id="release_year" name="release_year" min="1980" max="<?php echo (int)date('Y') + 2; ?>"
                   value="<?php echo (int)$game['release_year']; ?>">
          </div>
        </div>

        <div class="field">
          <label for="short_desc">Short description (shown on listing cards)</label>
          <input type="text" id="short_desc" name="short_desc" maxlength="255"
                 value="<?php echo e($game['short_desc']); ?>" data-required data-label="Short description">
        </div>

        <div class="field">
          <label for="description">Full description (shown on the details page)</label>
          <textarea id="description" name="description" data-required data-label="Full description"
                    style="min-height:170px"><?php echo e($game['description']); ?></textarea>
        </div>

        <div class="two-col" style="margin-top:0;gap:20px">
          <div class="field">
            <label for="price">Price (RM)</label>
            <input type="number" id="price" name="price" min="0" step="0.01"
                   value="<?php echo e($game['price']); ?>" data-required data-label="Price">
          </div>
          <div class="field">
            <label for="discount">Discount (%)</label>
            <input type="number" id="discount" name="discount" min="0" max="99"
                   value="<?php echo (int)$game['discount']; ?>">
            <div class="hint">0 means no discount.</div>
          </div>
        </div>

        <div class="two-col" style="margin-top:0;gap:20px">
          <div class="field">
            <label for="badge">Badge (optional)</label>
            <input type="text" id="badge" name="badge" maxlength="30"
                   value="<?php echo e($game['badge'] ?? ''); ?>" placeholder="e.g. New release, Best seller">
          </div>
          <div class="field">
            <label for="art">Cover artwork style</label>
            <select id="art" name="art">
              <?php foreach ($arts as $a): ?>
                <option value="<?php echo e($a); ?>" <?php echo $game['art'] === $a ? 'selected' : ''; ?>>
                  <?php echo e(strtoupper(str_replace('-', ' ', $a))); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="field">
          <label>Artwork preview</label>
          <div style="display:grid;grid-template-columns:repeat(8,1fr);gap:8px">
            <?php foreach ($arts as $a): ?>
              <span class="thumb <?php echo e($a); ?>" style="width:100%;height:38px" title="<?php echo e($a); ?>"></span>
            <?php endforeach; ?>
          </div>
          <div class="hint">Styles 1–8 map left to right to the dropdown above.</div>
        </div>

        <div style="display:flex;gap:12px;flex-wrap:wrap">
          <button class="btn" type="submit"><?php echo $game_id > 0 ? 'Save changes' : 'Add game'; ?></button>
          <a class="btn ghost" href="admin.php">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
