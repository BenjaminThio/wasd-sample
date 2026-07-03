<?php
require_once 'config.php';
require_login($conn);
$user = current_user($conn);

$errors = array();
$avatars = array('🚀', '👾', '🐉', '🕹️', '🦊', '⚔️');
$genre_options = array('RPG', 'FPS', 'Strategy', 'Racing', 'Horror', 'Indie', 'Roguelike', 'Co-op', 'Souls-like', 'Adventure');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* ---------- UPDATE: profile details ---------- */
    if ($action === 'update_profile') {
        $username = trim($_POST['username'] ?? '');
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $avatar   = $_POST['avatar'] ?? $user['avatar'];
        $genres   = isset($_POST['genres']) && is_array($_POST['genres']) ? $_POST['genres'] : array();

        if ($username === '' || strlen($username) < 3 || strlen($username) > 30 || !preg_match('/^[A-Za-z0-9_]+$/', $username)) {
            $errors[] = 'Username must be 3–30 characters (letters, numbers, underscores).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Enter a valid email address.';
        }
        if (!in_array($avatar, $avatars, true)) $avatar = $user['avatar'];
        $genres = array_values(array_intersect($genres, $genre_options));

        /* keep username/email unique (excluding yourself) */
        if (!$errors) {
            if (user_taken($conn, $username, $email, $user['id'])) {
                $errors[] = 'That username or email is taken by another account.';
            }
        }

        if (!$errors) {
            $genres_csv = implode(',', $genres);
            update_user($conn, $user['id'], array(
                'username' => $username,
                'email' => $email,
                'avatar' => $avatar,
                'favorite_genres' => $genres_csv,
            ));
            set_flash('Profile updated.');
            header('Location: profile.php');
            exit;
        }
    }

    /* ---------- UPDATE: password ---------- */
    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!password_verify($current, $user['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        }
        if (strlen($new) < 6)   $errors[] = 'New password needs at least 6 characters.';
        if ($new !== $confirm)  $errors[] = 'New passwords do not match.';

        if (!$errors) {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            update_user($conn, $user['id'], array('password_hash' => $hash));
            set_flash('Password changed.');
            header('Location: profile.php');
            exit;
        }
    }
}

/* refresh user after possible failed POST (so the form shows current values) */
$_user_cache_reset = null; // current_user() is statically cached; re-fetch directly for freshness
$user = $conn->get('users', (string)$user['id']);
$user['id'] = (int)$user['_id'];
$my_genres = $user['favorite_genres'] ? explode(',', $user['favorite_genres']) : array();

/* ---------- order history ---------- */
$orders = get_orders_for_user($conn, $user['id']);

/* ---------- my reviews ---------- */
$my_reviews = get_reviews_for_user($conn, $user['id']);

$page_title = 'My profile — WASD';
include 'includes/header.php';
?>

<section class="page-head">
  <div class="wrap">
    <span class="eyebrow">Account</span>
    <h1 class="page-title"><span class="mini-ava" style="width:44px;height:44px;font-size:22px;vertical-align:middle;margin-right:10px"><?php echo e($user['avatar']); ?></span><?php echo e($user['username']); ?></h1>
    <p class="section-sub">
      Member since <?php echo ts_fmt($user['created_at'], 'F Y'); ?>
      <?php if ($user['is_admin']): ?> · <span class="badge on">Store admin</span><?php endif; ?>
    </p>

    <?php if ($errors): ?>
      <div class="form-error" style="max-width:720px"><?php echo implode('<br>', array_map('e', $errors)); ?></div>
    <?php endif; ?>

    <div class="two-col">
      <!-- profile details -->
      <div class="card static section-block">
        <h3>Profile details</h3>
        <form method="post" action="profile.php" data-validate>
          <input type="hidden" name="action" value="update_profile">
          <div class="field">
            <label for="username">Gamertag</label>
            <input type="text" id="username" name="username" maxlength="30"
                   value="<?php echo e($user['username']); ?>" data-required data-label="Gamertag">
          </div>
          <div class="field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email"
                   value="<?php echo e($user['email']); ?>" data-required data-label="Email">
          </div>
          <div class="field">
            <label>Avatar</label>
            <div class="ava-grid">
              <?php foreach ($avatars as $a): ?>
                <label>
                  <input type="radio" name="avatar" value="<?php echo e($a); ?>" <?php echo $user['avatar'] === $a ? 'checked' : ''; ?>>
                  <?php echo e($a); ?>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="field">
            <label>Favorite genres</label>
            <div class="pill-wrap">
              <?php foreach ($genre_options as $g): ?>
                <label>
                  <input type="checkbox" name="genres[]" value="<?php echo e($g); ?>"
                    <?php echo in_array($g, $my_genres, true) ? 'checked' : ''; ?>>
                  <?php echo e($g); ?>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
          <button class="btn" type="submit">Save changes</button>
        </form>
      </div>

      <!-- change password -->
      <div class="card static section-block">
        <h3>Change password</h3>
        <form method="post" action="profile.php" data-validate>
          <input type="hidden" name="action" value="change_password">
          <div class="field">
            <label for="current_password">Current password</label>
            <input type="password" id="current_password" name="current_password"
                   data-required data-label="Current password">
          </div>
          <div class="field">
            <label for="password">New password</label>
            <input type="password" id="password" name="password"
                   data-required data-label="New password" placeholder="At least 6 characters">
          </div>
          <div class="field">
            <label for="confirm_password">Confirm new password</label>
            <input type="password" id="confirm_password" name="confirm_password"
                   data-required data-label="Password confirmation">
          </div>
          <button class="btn" type="submit">Update password</button>
        </form>
      </div>
    </div>
  </div>
</section>

<!-- ORDER HISTORY -->
<section style="padding-top:0">
  <div class="wrap">
    <span class="eyebrow">Purchases</span>
    <h2>Order history</h2>

    <?php if (count($orders) === 0): ?>
      <div class="empty">No orders yet. Your first haul is one <a href="games.php">store visit</a> away.</div>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Order</th><th>Date</th><th>Games</th><th>Total</th></tr></thead>
          <tbody>
            <?php foreach ($orders as $o): ?>
              <tr>
                <td><strong>#WASD-<?php echo str_pad($o['id'], 5, '0', STR_PAD_LEFT); ?></strong></td>
                <td><?php echo ts_fmt($o['created_at'], 'j M Y, g:i a'); ?></td>
                <td>
                  <?php
                    $names = array();
                    foreach ($o['items'] as $oi) {
                        $names[] = e($oi['title']) . ($oi['quantity'] > 1 ? ' ×' . (int)$oi['quantity'] : '');
                    }
                    echo implode(', ', $names);
                  ?>
                </td>
                <td><span class="price"><?php echo rm($o['total']); ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- MY REVIEWS -->
<section style="padding-top:0">
  <div class="wrap">
    <span class="eyebrow">Contributions</span>
    <h2>My reviews</h2>

    <?php if (count($my_reviews) === 0): ?>
      <div class="empty">You haven't reviewed anything yet. Open any game page to leave your first review.</div>
    <?php else: ?>
      <div class="review-list">
        <?php foreach ($my_reviews as $r): ?>
          <article class="card static review">
            <div class="review-head">
              <strong><a href="game.php?id=<?php echo (int)$r['game_id']; ?>" style="color:inherit;text-decoration:none"><?php echo e($r['title']); ?></a></strong>
              <span class="stars-line"><?php echo stars($r['rating']); ?></span>
              <time><?php echo ts_fmt($r['created_at'], 'j M Y'); ?></time>
            </div>
            <p><?php echo nl2br(e($r['comment'])); ?></p>
            <div class="review-actions">
              <a class="btn ghost tiny" href="game.php?id=<?php echo (int)$r['game_id']; ?>">Edit on game page</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
