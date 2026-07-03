<?php
require_once 'config.php';

$errors = array();
$sent = false;
$user = current_user($db);
$old = array(
    'name'    => $user ? $user['username'] : '',
    'email'   => $user ? $user['email'] : '',
    'subject' => '',
    'message' => '',
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['name']    = trim($_POST['name'] ?? '');
    $old['email']   = strtolower(trim($_POST['email'] ?? ''));
    $old['subject'] = trim($_POST['subject'] ?? '');
    $old['message'] = trim($_POST['message'] ?? '');

    if ($old['name'] === '')    $errors[] = 'Tell us your name.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email.';
    if ($old['subject'] === '') $errors[] = 'Add a subject line.';
    if (strlen($old['message']) < 10) $errors[] = 'Message must be at least 10 characters.';

    if (!$errors) {
        $msg_id = 'msg_' . uniqid();
        $db->saveDocument('messages', $msg_id, [
            'name' => $old['name'],
            'email' => $old['email'],
            'subject' => $old['subject'],
            'message' => $old['message']
        ]);
        $sent = true;
        $old['subject'] = '';
        $old['message'] = '';
    }
}

$page_title = 'Contact — WASD';
include 'includes/header.php';
?>

<section class="page-head">
  <div class="wrap">
    <h1 class="page-title">Talk to WASD</h1>
    <div class="card static section-block" style="max-width:600px; margin: 0 auto;">
      <?php if ($sent): ?>
        <div class="form-error" style="background:rgba(52,211,153,0.1); border-color:var(--green); color:var(--green);">Message received.</div>
      <?php endif; ?>
      <?php if ($errors): ?>
        <div class="form-error"><?php echo implode('<br>', array_map('e', $errors)); ?></div>
      <?php endif; ?>

      <form method="post" action="contact.php">
        <div class="field">
          <label>Name</label>
          <input type="text" name="name" value="<?php echo e($old['name']); ?>" data-required>
        </div>
        <div class="field">
          <label>Email</label>
          <input type="email" name="email" value="<?php echo e($old['email']); ?>" data-required>
        </div>
        <div class="field">
          <label>Subject</label>
          <input type="text" name="subject" value="<?php echo e($old['subject']); ?>" data-required>
        </div>
        <div class="field">
          <label>Message</label>
          <textarea name="message" data-required><?php echo e($old['message']); ?></textarea>
        </div>
        <button class="btn full" type="submit">Send Message</button>
      </form>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>