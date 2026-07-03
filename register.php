<?php
require_once 'config.php';

/* already logged in? go home */
if (current_user($conn)) {
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

    /* ---------- server-side validation ---------- */
    if ($old['username'] === '' || strlen($old['username']) < 3 || strlen($old['username']) > 30) {
        $errors[] = 'Username must be 3–30 characters.';
    } elseif (!preg_match('/^[A-Za-z0-9_]+$/', $old['username'])) {
        $errors[] = 'Username can only use letters, numbers, and underscores.';
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
    if (!in_array($old['avatar'], $avatars, true)) $old['avatar'] = '🚀';
    $old['genres'] = array_values(array_intersect($old['genres'], $genre_options));

    /* username / email must be unique */
    if (!$errors) {
        if (user_taken($conn, $old['username'], $old['email'])) {
            $errors[] = 'That username or email is already registered. Try logging in instead.';
        }
    }

    /* ---------- CREATE the account ---------- */
    if (!$errors) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $genres_csv = implode(',', $old['genres']);
        $new_id = create_user($conn, array(
            'username' => $old['username'],
            'email' => $old['email'],
            'password_hash' => $hash,
            'avatar' => $old['avatar'],
            'favorite_genres' => $genres_csv,
        ));

        $_SESSION['user_id'] = $new_id;
        set_flash('Welcome to WASD, ' . $old['username'] . '. Your free player profile is live.');
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
      <h1 class="page-title" style="font-size:clamp(24px,3vw,32px)">Create your account</h1>
      <p class="section-sub" style="margin-bottom:30px">Forge your identity — gamertag, avatar, and the genres you want your storefront built around.</p>

      <?php if ($errors): ?>
        <div class="form-error"><?php echo implode('<br>', array_map('e', $errors)); ?></div>
      <?php endif; ?>

      <form method="post" action="register.php" data-validate>
        <div class="field">
          <label for="username">Gamertag (username)</label>
          <input type="text" id="username" name="username" maxlength="30"
                 value="<?php echo e($old['username']); ?>"
                 data-required data-label="Gamertag" placeholder="e.g. PixelArif">
          <div class="hint">3–30 characters. Letters, numbers, and underscores only.</div>
        </div>

        <div class="field">
          <label for="email">Email address</label>
          <input type="email" id="email" name="email"
                 value="<?php echo e($old['email']); ?>"
                 data-required data-label="Email" placeholder="you@email.com">
        </div>

        <div class="two-col" style="margin-top:0;gap:20px">
          <div class="field">
            <label for="password">Password</label>
            <input type="password" id="password" name="password"
                   data-required data-label="Password" placeholder="At least 6 characters">
          </div>
          <div class="field">
            <label for="confirm_password">Confirm password</label>
            <input type="password" id="confirm_password" name="confirm_password"
                   data-required data-label="Password confirmation" placeholder="Type it again">
          </div>
        </div>

        <div class="field">
          <label>Pick your avatar</label>
          <div class="ava-grid">
            <?php foreach ($avatars as $a): ?>
              <label>
                <input type="radio" name="avatar" value="<?php echo e($a); ?>" <?php echo $old['avatar'] === $a ? 'checked' : ''; ?>>
                <?php echo e($a); ?>
              </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="field">
          <label>Favorite genres (optional)</label>
          <div class="pill-wrap">
            <?php foreach ($genre_options as $g): ?>
              <label>
                <input type="checkbox" name="genres[]" value="<?php echo e($g); ?>"
                  <?php echo in_array($g, $old['genres'], true) ? 'checked' : ''; ?>>
                <?php echo e($g); ?>
              </label>
            <?php endforeach; ?>
          </div>
          <div class="hint">Shown on your profile — helps you remember what you're hunting for.</div>
        </div>

        <button class="btn full" type="submit">Create account</button>
      </form>

      <p class="form-foot">Already registered? <a href="login.php">Log in</a></p>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
