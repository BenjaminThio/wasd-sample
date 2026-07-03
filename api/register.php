<?php
require_once 'config.php';

if (current_user($db)) {
    header('Location: index.php');
    exit;
}

$errors = array();
$old = array('username' => '', 'email' => '', 'avatar' => '🚀', 'genres' => array());
$avatars = array('🚀', '👾', '🐉', '🕹️', '🦊', '⚔️');
$genre_options = array('RPG', 'FPS', 'Strategy', 'Racing', 'Horror', 'Indie', 'Roguelike', 'Co-op', 'Souls-like', 'Adventure');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['username'] = trim($_POST['username'] ?? '');
    $old['email']    = strtolower(trim($_POST['email'] ?? ''));
    $old['avatar']   = $_POST['avatar'] ?? '🚀';
    $old['genres']   = isset($_POST['genres']) && is_array($_POST['genres']) ? $_POST['genres'] : array();
    $password        = $_POST['password'] ?? '';
    $confirm         = $_POST['confirm_password'] ?? '';

    if ($old['username'] === '' || strlen($old['username']) < 3 || strlen($old['username']) > 30) {
        $errors[] = 'Username must be 3–30 characters.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password needs at least 6 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        $all_users = $db->getCollection('users');
        foreach ($all_users as $u) {
            if ($u['username'] === $old['username'] || $u['email'] === $old['email']) {
                $errors[] = 'That username or email is already registered.';
                break;
            }
        }
    }

    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $new_id = 'user_' . uniqid();
        
        $db->saveDocument('users', $new_id, [
            'username' => $old['username'],
            'email' => $old['email'],
            'password_hash' => $hash,
            'avatar' => $old['avatar'],
            'favorite_genres' => implode(',', $old['genres']),
            'is_admin' => 0,
            'cart_item_count' => 0
        ]);

        $_SESSION['user_id'] = $new_id;
        set_flash('Welcome to WASD, ' . $old['username'] . '.');
        header('Location: index.php');
        exit;
    }
}

$page_title = 'Create account — WASD';
include 'includes/header.php';
?>

<section class="page-head">
  <div class="wrap">
    <div class="card static panel wide">
      <span class="eyebrow">Player one, ready</span>
      <h1 class="page-title">Create your account</h1>

      <?php if ($errors): ?>
        <div class="form-error"><?php echo implode('<br>', array_map('e', $errors)); ?></div>
      <?php endif; ?>

      <form method="post" action="register.php" data-validate>
        <div class="field">
          <label for="username">Gamertag (username)</label>
          <input type="text" id="username" name="username" value="<?php echo e($old['username']); ?>" data-required>
        </div>
        <div class="field">
          <label for="email">Email address</label>
          <input type="email" id="email" name="email" value="<?php echo e($old['email']); ?>" data-required>
        </div>
        <div class="two-col" style="margin-top:0;gap:20px">
          <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" data-required>
          </div>
          <div class="field">
            <label for="confirm_password">Confirm password</label>
            <input type="password" id="confirm_password" name="confirm_password" data-required>
          </div>
        </div>
        <button class="btn full" type="submit">Create account</button>
      </form>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>