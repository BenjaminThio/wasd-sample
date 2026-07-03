<?php
require_once 'config.php';

$game_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/* ---------- handle POST actions (cart / wishlist / review CRUD) ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = current_user($conn);
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $game_id = isset($_POST['game_id']) ? (int)$_POST['game_id'] : $game_id;

    if (!$user) {
        $_SESSION['redirect_after_login'] = 'game.php?id=' . $game_id;
        set_flash('Log in to do that.', 'error');
        header('Location: login.php');
        exit;
    }

    /* CREATE: add to cart (or bump quantity if already there) */
    if ($action === 'add_cart') {
        add_to_cart($conn, $user['id'], $game_id);
        set_flash('Added to your cart.');
    }

    /* CREATE: add to wishlist (ignore if already saved) */
    if ($action === 'add_wishlist') {
        add_to_wishlist($conn, $user['id'], $game_id);
        set_flash('Saved to your wishlist.');
    }

    /* CREATE / UPDATE: post or edit your review */
    if ($action === 'save_review') {
        $rating  = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
        $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
        if ($rating < 1 || $rating > 5 || $comment === '') {
            set_flash('Pick a star rating and write a comment to post a review.', 'error');
        } else {
            save_review($conn, $user['id'], $game_id, $rating, $comment);
            set_flash('Review published.');
        }
    }

    /* DELETE: remove your own review (admins can remove any) */
    if ($action === 'delete_review') {
        $review_id = isset($_POST['review_id']) ? $_POST['review_id'] : '';
        // review ids are "{user_id}_{game_id}" — only the owner or an admin may delete
        $owner_id = explode('_', $review_id)[0];
        if ($user['is_admin'] || (string)$owner_id === (string)$user['id']) {
            delete_review($conn, $review_id);
        }
        set_flash('Review deleted.');
    }

    header('Location: game.php?id=' . $game_id);
    exit;
}

/* ---------- fetch the game ---------- */
$game = get_game($conn, $game_id);

if (!$game) {
    set_flash('That game does not exist in the store.', 'error');
    header('Location: games.php');
    exit;
}

$user = current_user($conn);

/* the visitor's own review (for pre-filling the form) */
$my_review = null;
$in_wishlist = false;
if ($user) {
    $my_review = get_review($conn, $user['id'], $game_id);
    $in_wishlist = is_in_wishlist($conn, $user['id'], $game_id);
}

/* all reviews for this game */
$reviews = get_reviews_for_game($conn, $game_id);

/* related games in the same genre */
$related = array_values(array_filter(get_all_games($conn), function ($g) use ($game, $game_id) {
    return $g['genre'] === $game['genre'] && $g['id'] !== (int)$game_id;
}));
$related = array_slice($related, 0, 3);

$pay_now = final_price($game['price'], $game['discount']);
$page_title = $game['title'] . ' — WASD';
include 'includes/header.php';
?>

<section class="page-head" style="padding-bottom:0">
  <div class="wrap">
    <span class="eyebrow"><?php echo e($game['genre']); ?></span>
    <h1 class="page-title"><?php echo e($game['title']); ?></h1>

    <div class="detail-meta">
      <div><small>Developer</small><span><?php echo e($game['developer']); ?></span></div>
      <div><small>Released</small><span><?php echo (int)$game['release_year']; ?></span></div>
      <div><small>Player rating</small>
        <span>
          <?php if ($game['avg_rating'] !== null): ?>
            ★ <?php echo number_format($game['avg_rating'], 1); ?> (<?php echo (int)$game['review_count']; ?> review<?php echo $game['review_count'] == 1 ? '' : 's'; ?>)
          <?php else: ?>
            No reviews yet
          <?php endif; ?>
        </span>
      </div>
    </div>

    <div class="detail-grid">
      <div>
        <div class="game-art tall <?php echo e($game['art']); ?>">
          <?php if (!empty($game['badge'])): ?><span class="tag"><?php echo e($game['badge']); ?></span><?php endif; ?>
        </div>
        <h2 style="font-size:22px;margin-top:34px">About this game</h2>
        <p class="detail-desc"><?php echo nl2br(e($game['description'])); ?></p>
      </div>

      <!-- buy box -->
      <aside class="card static buy-box">
        <span class="eyebrow">Get the game</span>
        <div class="price-line">
          <?php if ($game['discount'] > 0): ?>
            <span class="price"><span class="old"><?php echo rm($game['price']); ?></span></span>
            <span class="now"><?php echo rm($pay_now); ?></span>
            <span class="off">−<?php echo (int)$game['discount']; ?>%</span>
          <?php else: ?>
            <span class="now"><?php echo rm($game['price']); ?></span>
          <?php endif; ?>
        </div>

        <div class="actions">
          <form method="post" action="game.php">
            <input type="hidden" name="action" value="add_cart">
            <input type="hidden" name="game_id" value="<?php echo (int)$game['id']; ?>">
            <button class="btn full" type="submit">Add to cart</button>
          </form>
          <?php if ($in_wishlist): ?>
            <a class="btn ghost full" href="wishlist.php">In your wishlist ✓</a>
          <?php else: ?>
            <form method="post" action="game.php">
              <input type="hidden" name="action" value="add_wishlist">
              <input type="hidden" name="game_id" value="<?php echo (int)$game['id']; ?>">
              <button class="btn ghost full" type="submit">Save to wishlist</button>
            </form>
          <?php endif; ?>
        </div>

        <p class="buy-note">
          Instant download after checkout · Plays on WASD launcher for Windows, macOS, and Linux ·
          14-day refund if you've played under 2 hours.
        </p>
      </aside>
    </div>
  </div>
</section>

<!-- REVIEWS -->
<section>
  <div class="wrap">
    <span class="eyebrow">Player reviews</span>
    <h2>Word from the lobby</h2>

    <?php if ($user): ?>
      <!-- create / update your review -->
      <div class="card static" style="padding:28px;margin-top:26px;max-width:720px">
        <h3 style="font-family:var(--display);font-size:17px;margin-bottom:18px">
          <?php echo $my_review ? 'Edit your review' : 'Write a review'; ?>
        </h3>
        <form method="post" action="game.php" data-validate>
          <input type="hidden" name="action" value="save_review">
          <input type="hidden" name="game_id" value="<?php echo (int)$game['id']; ?>">
          <div class="field">
            <label>Your rating</label>
            <div class="rating-input">
              <?php for ($s = 1; $s <= 5; $s++): ?>
                <label>
                  <input type="radio" name="rating" value="<?php echo $s; ?>"
                    <?php echo ($my_review && (int)$my_review['rating'] === $s) ? 'checked' : ''; ?>>
                  <span><?php echo str_repeat('★', $s); ?></span>
                </label>
              <?php endfor; ?>
            </div>
          </div>
          <div class="field">
            <label for="comment">Your review</label>
            <textarea id="comment" name="comment" data-required data-label="Your review"
              placeholder="What should other players know?"><?php echo $my_review ? e($my_review['comment']) : ''; ?></textarea>
          </div>
          <button class="btn" type="submit"><?php echo $my_review ? 'Update review' : 'Publish review'; ?></button>
        </form>
      </div>
    <?php else: ?>
      <p class="section-sub" style="margin-top:20px">
        <a href="login.php">Log in</a> or <a href="register.php">create an account</a> to write a review.
      </p>
    <?php endif; ?>

    <div class="review-list">
      <?php if (count($reviews) === 0): ?>
        <div class="empty">No reviews yet. Be the first player to weigh in.</div>
      <?php endif; ?>

      <?php foreach ($reviews as $r): ?>
        <article class="card static review">
          <div class="review-head">
            <span class="mini-ava"><?php echo e($r['avatar']); ?></span>
            <strong><?php echo e($r['username']); ?></strong>
            <span class="stars-line"><?php echo stars($r['rating']); ?></span>
            <time>
              <?php echo ts_fmt($r['created_at'], 'j M Y'); ?>
              <?php if (!empty($r['updated_at'])): ?> · edited<?php endif; ?>
            </time>
          </div>
          <p><?php echo nl2br(e($r['comment'])); ?></p>

          <?php if ($user && ((int)$user['id'] === (int)$r['user_id'] || $user['is_admin'])): ?>
            <div class="review-actions">
              <form method="post" action="game.php" data-confirm="Delete this review?">
                <input type="hidden" name="action" value="delete_review">
                <input type="hidden" name="game_id" value="<?php echo (int)$game['id']; ?>">
                <input type="hidden" name="review_id" value="<?php echo e($r['_id']); ?>">
                <button class="btn danger tiny" type="submit">Delete</button>
              </form>
            </div>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- RELATED -->
<?php if (count($related) > 0): ?>
<section style="padding-top:0">
  <div class="wrap">
    <span class="eyebrow">More like this</span>
    <h2>Also in <?php echo e($game['genre']); ?></h2>
    <div class="games-grid wide">
      <?php foreach ($related as $g): ?>
        <a class="game-card reveal" href="game.php?id=<?php echo (int)$g['id']; ?>">
          <div class="game-art <?php echo e($g['art']); ?>">
            <?php if (!empty($g['badge'])): ?><span class="tag"><?php echo e($g['badge']); ?></span><?php endif; ?>
          </div>
          <div class="game-info">
            <h3><?php echo e($g['title']); ?></h3>
            <div class="genre"><?php echo e($g['genre']); ?></div>
            <div class="game-price">
              <span class="price">
                <?php if ($g['discount'] > 0): ?>
                  <span class="old"><?php echo rm($g['price']); ?></span><?php echo rm(final_price($g['price'], $g['discount'])); ?>
                <?php else: ?>
                  <?php echo rm($g['price']); ?>
                <?php endif; ?>
              </span>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
