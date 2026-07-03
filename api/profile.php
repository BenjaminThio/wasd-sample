<?php
require_once 'config.php';
require_login($db);
$user = current_user($db);

$errors = array();
$avatars = array('🚀', '👾', '🐉', '🕹️', '🦊', '⚔️');
$genre_options = array('RPG', 'FPS', 'Strategy', 'Racing', 'Horror', 'Indie', 'Roguelike', 'Co-op', 'Souls-like', 'Adventure');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $username = trim($_POST['username'] ?? '');
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $avatar   = $_POST['avatar'] ?? $user['avatar'];
        $genres   = isset($_POST['genres']) && is_array($_POST['genres']) ? $_POST['genres'] : array();

        if ($username === '' || strlen($username) < 3) {
            $errors[] = 'Username must be at least 3 characters.';
        }

        if (!$errors) {
            $all_users = $db->getCollection('users');
            foreach ($all_users as $u) {
                if ($u['id'] !== $user['id'] && ($u['username'] === $username || $u['email'] === $email)) {
                    $errors[] = 'That username or email is taken.';
                    break;
                }
            }
        }

        if (!$errors) {
            $db->saveDocument('users', $user['id'], [
                'username' => $username,
                'email' => $email,
                'avatar' => $avatar,
                'favorite_genres' => implode(',', $genres),
                'is_admin' => (int)($user['is_admin'] ?? 0),
                'cart_item_count' => (int)($user['cart_item_count'] ?? 0),
                'password_hash' => $user['password_hash']
            ]);
            set_flash('Profile updated.');
            header('Location: profile.php');
            exit;
        }
    }
}

$user = current_user($db);
$my_genres = !empty($user['favorite_genres']) ? explode(',', $user['favorite_genres']) : array();

// Build historical data arrays manually
$all_orders = $db->getCollection('orders');
$my_orders = [];
foreach ($all_orders as $o) {
    if ($o['user_id'] === $user['id']) {
        $my_orders[] = $o;
    }
}

$page_title = 'My profile — WASD';
include 'includes/header.php';
?>

<section class="page-head">
  <div class="wrap">
    <h1 class="page-title"><?php echo e($user['username']); ?></h1>
    
    <?php if ($errors): ?>
      <div class="form-error"><?php echo implode('<br>', array_map('e', $errors)); ?></div>
    <?php endif; ?>

    <div class="two-col">
      <div class="card static section-block">
        <h3>Profile details</h3>
        <form method="post" action="profile.php">
          <input type="hidden" name="action" value="update_profile">
          <div class="field">
            <label>Gamertag</label>
            <input type="text" name="username" value="<?php echo e($user['username']); ?>" data-required>
          </div>
          <div class="field">
            <label>Email</label>
            <input type="email" name="email" value="<?php echo e($user['email']); ?>" data-required>
          </div>
          <button class="btn" type="submit">Save changes</button>
        </form>
      </div>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>