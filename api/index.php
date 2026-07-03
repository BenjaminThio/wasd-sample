<?php
require_once 'config.php';

$all_games = $db->getCollection('games');

// Sort by pre-calculated average rating descending
usort($all_games, function($a, $b) {
    return ($b['avg_rating'] ?? 0) <=> ($a['avg_rating'] ?? 0);
});
$featured = array_slice($all_games, 0, 4);

$ticker_parts = [];
foreach ($all_games as $t) {
    $label = ($t['discount'] ?? 0) > 0 ? '<i>−' . (int)$t['discount'] . '%</i>'
           : (($t['badge'] ?? '') ? '<b>' . e(strtoupper($t['badge'])) . '</b>' : '<b>IN STORE</b>');
    $ticker_parts[] = e($t['title']) . ' ' . $label;
}
$ticker_html = implode(' ', $ticker_parts);

$page_title = 'WASD — Your next obsession starts here';
include 'includes/header.php';
?>

<!-- HERO -->
<header class="hero">
  <div class="wrap">
    <span class="eyebrow boot d1">Player one, ready</span>

    <div class="keycaps boot d2" aria-hidden="true">
      <div class="keycap" data-key="w">W</div>
      <div class="keycap" data-key="a">A</div>
      <div class="keycap" data-key="s">S</div>
      <div class="keycap" data-key="d">D</div>
    </div>
    <div class="key-hint boot d3">Try pressing <b>W · A · S · D</b> on your keyboard</div>

    <h1 class="boot d3">Your next obsession<br><span class="grad">starts here.</span></h1>
    <p class="boot d4">WASD is the game store built for players. Browse the catalog, wishlist what you're watching, and check out in seconds — all in one dark, neon-soaked storefront.</p>

    <div class="hero-ctas boot d5">
      <a class="btn" href="games.php">Browse the store</a>
      <?php if (!$nav_user): ?>
        <a class="btn ghost" href="register.php">Create free account</a>
      <?php else: ?>
        <a class="btn ghost" href="wishlist.php">Open my wishlist</a>
      <?php endif; ?>
    </div>

    <div class="hero-meta boot d5">
      <div><strong><?php echo count($all_games); ?></strong><small>Games</small></div>
      <div><strong>74</strong><small>Players</small></div>
      <div><strong>−75%</strong><small>Top Deal</small></div>
      <div><strong>12</strong><small>Reviews</small></div>
    </div>
  </div>
</header>

<!-- TICKER -->
<div class="ticker" aria-hidden="true">
  <div class="ticker-track">
    <span><?php echo $ticker_html; ?></span>
    <span><?php echo $ticker_html; ?></span>
  </div>
</div>

<!-- FEATURED GAMES -->
<section>
  <div class="wrap">
    <span class="eyebrow">Featured this week</span>
    <h2>Trending on WASD</h2>
    <p class="section-sub">The highest-rated games in the store right now, straight from player reviews.</p>

    <div class="games-grid">
      <?php foreach ($featured as $g): ?>
        <a class="game-card reveal" href="game.php?id=<?php echo e($g['id']); ?>">
          <div class="game-art <?php echo e($g['art'] ?? 'art-1'); ?>">
            <?php if (!empty($g['badge'])): ?><span class="tag"><?php echo e($g['badge']); ?></span><?php endif; ?>
            <?php if (isset($g['avg_rating'])): ?>
              <span class="rating">★ <?php echo number_format($g['avg_rating'], 1); ?></span>
            <?php endif; ?>
          </div>
          <div class="game-info">
            <h3><?php echo e($g['title']); ?></h3>
            <div class="genre"><?php echo e($g['genre']); ?> · <?php echo e($g['developer']); ?></div>
            <div class="game-price">
              <span class="price">
                <?php if (($g['discount'] ?? 0) > 0): ?>
                  <span class="old"><?php echo rm($g['price']); ?></span><?php echo rm(final_price($g['price'], $g['discount'])); ?>
                <?php else: ?>
                  <?php echo rm($g['price']); ?>
                <?php endif; ?>
              </span>
              <?php if (($g['discount'] ?? 0) > 0): ?><span class="off">−<?php echo (int)$g['discount']; ?>%</span><?php endif; ?>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- HOW IT WORKS -->
<section>
  <div class="wrap">
    <span class="eyebrow">How WASD works</span>
    <h2>Three keys to press.<br>That's the whole tutorial.</h2>

    <div class="steps-grid">
      <div class="card step-card reveal">
        <div class="step-key">W</div>
        <h3>Create your account</h3>
        <p>One username, one email, zero spam. Pick an avatar and your favorite genres so the store knows what to show you first.</p>
      </div>
      <div class="card step-card reveal">
        <div class="step-key">A</div>
        <h3>Build your collection</h3>
        <p>Wishlist the games you're watching and drop the ones you want into your cart. Reviews from real players help you choose.</p>
      </div>
      <div class="card step-card reveal">
        <div class="step-key">S</div>
        <h3>Check out in seconds</h3>
        <p>A clean order summary, honest discounts, and your full order history saved to your profile. No launchers to fight.</p>
      </div>
    </div>
  </div>
</section>

<!-- CTA -->
<section>
  <div class="wrap">
    <div class="cta-panel reveal">
      <span class="eyebrow">Free forever</span>
      <h2>Press start on your library</h2>
      <p class="section-sub" style="margin:0 auto">Join WASD to unlock your wishlist, cart, and reviews.</p>
      <div class="hero-ctas" style="margin-top:32px">
        <?php if (!$nav_user): ?>
          <a class="btn" href="register.php">Sign up free</a>
          <a class="btn ghost" href="login.php">I already have an account</a>
        <?php else: ?>
          <a class="btn" href="games.php">Keep browsing</a>
          <a class="btn ghost" href="profile.php">View my profile</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php include 'includes/footer.php'; ?>