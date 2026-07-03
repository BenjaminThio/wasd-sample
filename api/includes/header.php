<?php
/* Shared header — expects $conn from config.php.
   Optional: set $page_title before including. */
$nav_user  = current_user($conn);
$nav_cart  = cart_count($conn);
$nav_flash = get_flash();
if (!isset($page_title)) $page_title = 'WASD — Your next obsession starts here';
$here = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo e($page_title); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Unbounded:wght@400;600;800&family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css">
</head>
<body>

<div class="bg-grid"></div>
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>
<div class="orb orb-3"></div>

<nav id="nav">
  <div class="nav-inner">
    <a class="nav-logo" href="index.php" aria-label="WASD home">
      <span>W</span><span>A</span><span>S</span><span>D</span>
    </a>

    <button class="nav-burger" id="nav-burger" aria-label="Open menu" aria-expanded="false">☰</button>

    <div class="nav-links" id="nav-links">
      <a href="index.php" class="<?php echo $here === 'index.php' ? 'active' : ''; ?>">Home</a>
      <a href="games.php" class="<?php echo in_array($here, array('games.php','game.php')) ? 'active' : ''; ?>">Store</a>
      <a href="contact.php" class="<?php echo $here === 'contact.php' ? 'active' : ''; ?>">Contact</a>

      <?php if ($nav_user): ?>
        <a href="cart.php" class="cart-link <?php echo $here === 'cart.php' ? 'active' : ''; ?>">
          Cart<?php if ($nav_cart > 0): ?><span class="cart-badge"><?php echo $nav_cart; ?></span><?php endif; ?>
        </a>

        <div class="dropdown" id="account-dropdown">
          <button class="dropdown-toggle" id="dropdown-toggle" aria-expanded="false">
            <span class="mini-ava"><?php echo e($nav_user['avatar']); ?></span>
            <?php echo e($nav_user['username']); ?> ▾
          </button>
          <div class="dropdown-menu" id="dropdown-menu">
            <a href="profile.php">My profile</a>
            <a href="wishlist.php">Wishlist</a>
            <a href="cart.php">Cart</a>
            <?php if ($nav_user['is_admin']): ?>
              <a href="admin.php" class="admin-link">Admin panel</a>
            <?php endif; ?>
            <a href="logout.php">Log out</a>
          </div>
        </div>
      <?php else: ?>
        <a href="login.php" class="<?php echo $here === 'login.php' ? 'active' : ''; ?>">Log in</a>
        <a class="btn small" href="register.php">Sign up free</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<?php if ($nav_flash): ?>
  <div class="flash flash-<?php echo e($nav_flash['type']); ?>" id="flash">
    <i></i><?php echo e($nav_flash['message']); ?>
  </div>
<?php endif; ?>

<main>
