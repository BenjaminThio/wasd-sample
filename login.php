<?php
require_once 'config.php';

/* already logged in? go home */
if (current_user($conn)) {
    header('Location: index.php');
    exit;
}

$errors = array();
$old_email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old_email = strtolower(trim($_POST['email'] ?? ''));
    $password  = $_POST['password'] ?? '';

    if ($old_email === '' || $password === '') {
        $errors[] = 'Enter both your email and password.';
    } else {
        $account = get_user_by_email($conn, $old_email);

        if ($account && password_verify($password, $account['password_hash'])) {
            /* protect the session against fixation */
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$account['id'];
            set_flash('Welcome back, ' . $account['username'] . '.');

            $target = $_SESSION['redirect_after_login'] ?? 'index.php';
            unset($_SESSION['redirect_after_login']);
            header('Location: ' . $target);
            exit;
        }
        $errors[] = 'Email or password is incorrect.';
    }
}

$page_title = 'Log in — WASD';
include 'includes/header.php';
?>

<section class="page-head">
  <div class="wrap">
    <div class="card static panel">
      <span class="eyebrow">Welcome back</span>
      <h1 class="page-title" style="font-size:clamp(24px,3vw,32px)">Log in to WASD</h1>
      <p class="section-sub" style="margin-bottom:30px">Your wishlist, cart, and reviews are waiting where you left them.</p>

      <?php if ($errors): ?>
        <div class="form-error"><?php echo implode('<br>', array_map('e', $errors)); ?></div>
      <?php endif; ?>

      <form method="post" action="login.php" data-validate>
        <div class="field">
          <label for="email">Email address</label>
          <input type="email" id="email" name="email"
                 value="<?php echo e($old_email); ?>"
                 data-required data-label="Email" placeholder="you@email.com">
        </div>
        <div class="field">
          <label for="password">Password</label>
          <input type="password" id="password" name="password"
                 data-required data-label="Password" placeholder="Your password">
        </div>
        <button class="btn full" type="submit">Log in</button>
      </form>

      <p class="form-foot">New to WASD? <a href="register.php">Create a free account</a></p>
      <p class="form-foot" style="font-family:var(--mono);font-size:12px;color:var(--dim)">
        Demo accounts — admin: admin@wasd.com / admin123 · player: arif@example.com / player123
      </p>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
