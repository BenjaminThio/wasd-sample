<?php
require_once 'config.php';

/* ---------- read filters from the query string ---------- */
$q     = isset($_GET['q'])     ? trim($_GET['q'])     : '';
$genre = isset($_GET['genre']) ? trim($_GET['genre']) : '';
$sort  = isset($_GET['sort'])  ? $_GET['sort']        : 'featured';

$sorts = array('featured', 'price_low', 'price_high', 'name', 'newest');
if (!in_array($sort, $sorts, true)) $sort = 'featured';

/* ---------- fetch + filter + sort in PHP ----------
   Firestore has no LIKE/OR text search and no server-side JOIN or
   AVG(), so (same as index.php) we load the catalog once and do
   the search/sort/rating math here. Totally fine at this scale. */
$games = get_all_games($conn);

if ($q !== '') {
    $needle = mb_strtolower($q);
    $games = array_values(array_filter($games, function ($g) use ($needle) {
        return mb_strpos(mb_strtolower($g['title']), $needle) !== false
            || mb_strpos(mb_strtolower($g['developer']), $needle) !== false
            || mb_strpos(mb_strtolower($g['short_desc']), $needle) !== false;
    }));
}
if ($genre !== '') {
    $games = array_values(array_filter($games, function ($g) use ($genre) {
        return $g['genre'] === $genre;
    }));
}

switch ($sort) {
    case 'price_low':
        usort($games, function ($a, $b) { return $a['final_price'] <=> $b['final_price']; });
        break;
    case 'price_high':
        usort($games, function ($a, $b) { return $b['final_price'] <=> $a['final_price']; });
        break;
    case 'name':
        usort($games, function ($a, $b) { return strcasecmp($a['title'], $b['title']); });
        break;
    case 'newest':
        usort($games, function ($a, $b) {
            if ($a['release_year'] != $b['release_year']) return $b['release_year'] <=> $a['release_year'];
            return $b['created_at'] <=> $a['created_at'];
        });
        break;
    default: // featured
        usort($games, function ($a, $b) {
            if (($a['avg_rating'] === null) !== ($b['avg_rating'] === null)) {
                return $a['avg_rating'] === null ? 1 : -1;
            }
            if ($a['avg_rating'] != $b['avg_rating']) return $b['avg_rating'] <=> $a['avg_rating'];
            return $b['created_at'] <=> $a['created_at'];
        });
}

/* genre list for the filter dropdown */
$all_games_for_genres = get_all_games($conn);
$genres = array_values(array_unique(array_map(function ($g) { return $g['genre']; }, $all_games_for_genres)));
sort($genres);

$page_title = 'Store — WASD';
include 'includes/header.php';
?>

<section class="page-head">
  <div class="wrap">
    <span class="eyebrow">Catalog</span>
    <h1 class="page-title">Browse the store</h1>
    <p class="section-sub">Every game on WASD, searchable and sortable. Prices already include current discounts.</p>

    <!-- filter / search bar (GET form so results are shareable) -->
    <form class="filter-bar" method="get" action="games.php">
      <input type="text" name="q" placeholder="Search title, developer, or description…"
             value="<?php echo e($q); ?>" aria-label="Search games">
      <select name="genre" aria-label="Filter by genre">
        <option value="">All genres</option>
        <?php foreach ($genres as $gname): ?>
          <option value="<?php echo e($gname); ?>" <?php echo $genre === $gname ? 'selected' : ''; ?>>
            <?php echo e($gname); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <select name="sort" aria-label="Sort results">
        <option value="featured"   <?php echo $sort === 'featured'   ? 'selected' : ''; ?>>Sort: Featured</option>
        <option value="price_low"  <?php echo $sort === 'price_low'  ? 'selected' : ''; ?>>Price: low to high</option>
        <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: high to low</option>
        <option value="name"       <?php echo $sort === 'name'       ? 'selected' : ''; ?>>Name: A to Z</option>
        <option value="newest"     <?php echo $sort === 'newest'     ? 'selected' : ''; ?>>Newest first</option>
      </select>
      <button class="btn small" type="submit">Apply</button>
      <?php if ($q !== '' || $genre !== '' || $sort !== 'featured'): ?>
        <a class="btn ghost small" href="games.php">Reset</a>
      <?php endif; ?>
    </form>

    <div class="result-note">
      <?php echo count($games); ?> game(s) found
      <?php if ($q !== ''): ?> for “<?php echo e($q); ?>”<?php endif; ?>
      <?php if ($genre !== ''): ?> in <?php echo e($genre); ?><?php endif; ?>
    </div>
  </div>
</section>

<section style="padding-top:0">
  <div class="wrap">
    <?php if (count($games) === 0): ?>
      <div class="empty">
        Nothing matched that search. Try a different keyword, or <a href="games.php">reset the filters</a>.
      </div>
    <?php else: ?>
      <div class="games-grid" style="margin-top:10px">
        <?php foreach ($games as $g): ?>
          <a class="game-card reveal" href="game.php?id=<?php echo (int)$g['id']; ?>">
            <div class="game-art <?php echo e($g['art']); ?>">
              <?php if (!empty($g['badge'])): ?><span class="tag"><?php echo e($g['badge']); ?></span><?php endif; ?>
              <?php if ($g['avg_rating'] !== null): ?>
                <span class="rating">★ <?php echo number_format($g['avg_rating'], 1); ?></span>
              <?php endif; ?>
            </div>
            <div class="game-info">
              <h3><?php echo e($g['title']); ?></h3>
              <div class="genre"><?php echo e($g['genre']); ?> · <?php echo e($g['developer']); ?></div>
              <p class="blurb"><?php echo e($g['short_desc']); ?></p>
              <div class="game-price">
                <span class="price">
                  <?php if ($g['discount'] > 0): ?>
                    <span class="old"><?php echo rm($g['price']); ?></span><?php echo rm($g['final_price']); ?>
                  <?php else: ?>
                    <?php echo rm($g['price']); ?>
                  <?php endif; ?>
                </span>
                <?php if ($g['discount'] > 0): ?><span class="off">−<?php echo (int)$g['discount']; ?>%</span><?php endif; ?>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php include 'includes/footer.php'; ?>
