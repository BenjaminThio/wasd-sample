<?php
require_once 'config.php';

$errors = array();
$sent = false;
$user = current_user($conn);
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
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email so we can reply.';
    if ($old['subject'] === '') $errors[] = 'Add a subject line.';
    if ($old['message'] === '' || strlen($old['message']) < 10) $errors[] = 'Write a message of at least 10 characters.';

    /* CREATE: store the inquiry */
    if (!$errors) {
        create_message($conn, array(
            'name' => $old['name'],
            'email' => $old['email'],
            'subject' => $old['subject'],
            'message' => $old['message'],
        ));
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
    <span class="eyebrow">Support &amp; press</span>
    <h1 class="page-title">Talk to WASD</h1>
    <p class="section-sub">Refunds, bug reports, partnership pitches, or just telling us which game kept you up until 4 a.m. — we read everything.</p>

    <div class="contact-grid">
      <!-- contact details -->
      <div>
        <div class="card static contact-info">
          <div class="row">
            <small>Email</small>
            <a href="mailto:support@wasd.com">support@wasd.com</a>
          </div>
          <div class="row">
            <small>Phone</small>
            <a href="tel:+60312345678">+60 3-1234 5678</a>
          </div>
          <div class="row">
            <small>HQ address</small>
            <span>Level 12, Menara WASD, Jalan Ampang,<br>50450 Kuala Lumpur, Malaysia</span>
          </div>
          <div class="row">
            <small>Support hours</small>
            <span>Monday–Friday, 9:00 am – 6:00 pm (MYT)</span>
          </div>
          <div class="row">
            <small>Find us on</small>
            <div class="socials" style="margin-top:6px">
              <a class="social" href="#" aria-label="WASD on X">X / Twitter</a>
              <a class="social" href="#" aria-label="WASD on Discord">Discord</a>
              <a class="social" href="#" aria-label="WASD on YouTube">YouTube</a>
              <a class="social" href="#" aria-label="WASD on Instagram">Instagram</a>
            </div>
          </div>
        </div>

        <!-- embedded Google Map -->
        <div class="map-box">
          <iframe
            title="WASD HQ on Google Maps"
            src="https://maps.google.com/maps?q=Jalan%20Ampang%20Kuala%20Lumpur&z=15&output=embed"
            loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
        </div>
      </div>

      <!-- contact form -->
      <div class="card static section-block">
        <h3>Send us a message</h3>

        <?php if ($sent): ?>
          <div class="flash flash-success" style="position:static;transform:none;margin-bottom:22px;max-width:none">
            <i></i>Message received. We reply within one working day.
          </div>
        <?php endif; ?>

        <?php if ($errors): ?>
          <div class="form-error"><?php echo implode('<br>', array_map('e', $errors)); ?></div>
        <?php endif; ?>

        <form method="post" action="contact.php" data-validate>
          <div class="two-col" style="margin-top:0;gap:20px">
            <div class="field">
              <label for="name">Your name</label>
              <input type="text" id="name" name="name" value="<?php echo e($old['name']); ?>"
                     data-required data-label="Your name" placeholder="e.g. Arif Rahman">
            </div>
            <div class="field">
              <label for="email">Email</label>
              <input type="email" id="email" name="email" value="<?php echo e($old['email']); ?>"
                     data-required data-label="Email" placeholder="you@email.com">
            </div>
          </div>
          <div class="field">
            <label for="subject">Subject</label>
            <input type="text" id="subject" name="subject" maxlength="150" value="<?php echo e($old['subject']); ?>"
                   data-required data-label="Subject" placeholder="What's this about?">
          </div>
          <div class="field">
            <label for="message">Message</label>
            <textarea id="message" name="message" data-required data-label="Message"
                      placeholder="Give us the details — order number, game title, or anything that helps."><?php echo e($old['message']); ?></textarea>
          </div>
          <button class="btn full" type="submit">Send message</button>
        </form>
      </div>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
