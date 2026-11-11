
<?php
// CraveLab - Recipe Sharing & Food Community Platform

require_once __DIR__ . '/includes/session.php';
require_once 'includes/db.php';

$isLoggedIn = isset($_SESSION["user_id"]);
$profileImage = "";
$profileImageExists = false;

if ($isLoggedIn) {
    $userId = (int)$_SESSION["user_id"];

    $profileStmt = $conn->prepare("SELECT profile_image FROM users WHERE id = ?");
    $profileStmt->bind_param("i", $userId);
    $profileStmt->execute();
    $profileData = $profileStmt->get_result()->fetch_assoc();
    $profileStmt->close();

    $profileImage = trim($profileData["profile_image"] ?? "");

    $profileImageExists = (
        $profileImage !== "" &&
        strpos($profileImage, "uploads/profiles/") === 0 &&
        is_file(__DIR__ . "/" . $profileImage)
    );
}

$featuredRecipes = [
    [
        "title" => "Smoky Street-Style Burger",
        "category" => "Street food",
        "time" => "25 min",
        "level" => "Medium",
        "image" => "assets/images/burger.jpg",
        "description" => "Juicy, messy and absolutely worth the extra napkins."
    ],
    [
        "title" => "Zesty Salmon Salad Bowl",
        "category" => "Healthy-ish",
        "time" => "20 min",
        "level" => "Easy",
        "image" => "assets/images/salmon-fish-salad.jpg",
        "description" => "Fresh, colourful and fancy without trying too hard."
    ],
    [
        "title" => "Homestyle Curry & Rice",
        "category" => "Comfort food",
        "time" => "35 min",
        "level" => "Medium",
        "image" => "assets/images/curry rice.jpg",
        "description" => "A warm bowl of comfort. Happiness served with rice."
    ],
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="CraveLab — recipes, kitchen experiments and food people.">
    <title>CraveLab — Good food. Questionable kitchen decisions.</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        .nav-actions {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .nav-profile-button {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            overflow: hidden;
            display: grid;
            place-items: center;
            background: #f5eee4;
            border: 2px solid #e36b42;
            text-decoration: none;
            color: #333;
            font-size: 20px;
            flex-shrink: 0;
            transition: .2s;
        }

        .nav-profile-button:hover {
            transform: scale(1.08);
        }

        .nav-profile-button img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .nav-logout {
            color: #e36b42;
            text-decoration: none;
            font-weight: 700;
            font-size: 13px;
        }

        @media(max-width: 750px) {
            .nav-actions { gap: 12px; }
            .nav-profile-button {
                width: 36px;
                height: 36px;
            }
        }
    </style>
</head>

<body>

<header class="site-header">
    <nav class="nav container">

        <a class="brand" href="index.php">
            <span class="brand-mark">C</span> crave<span>lab.</span>
        </a>

        <button class="menu-toggle" id="menuToggle" aria-label="Toggle navigation">
            ☰
        </button>

        <div class="nav-links" id="navLinks">
            <a class="active" href="index.php">Home</a>
            <a href="#discover">Discover</a>
            <a href="#categories">Categories</a>
            <a href="#community">Community</a>
            <?php if ($isLoggedIn): ?>
                <a href="discover.php">Creators</a>
                <a href="following.php">Following</a>
                <a href="notifications.php">Notifications</a>
            <?php endif; ?>
        </div>

        <div class="nav-actions">
            <?php if ($isLoggedIn): ?>

                <a href="profile.php"
                   class="nav-profile-button"
                   title="My profile"
                   aria-label="Open my profile">

                    <?php if ($profileImageExists): ?>
                        <img src="<?= htmlspecialchars($profileImage) ?>" alt="Profile picture">
                    <?php else: ?>
                        👤
                    <?php endif; ?>

                </a>

                <a class="nav-logout" href="logout.php">Log out ↗</a>

            <?php else: ?>

                <a class="login-link" href="login.php">Log in</a>

                <a class="btn btn-dark btn-small" href="register.php">
                    Join the kitchen <span>↗</span>
                </a>

            <?php endif; ?>
        </div>

    </nav>
</header>

<main>

    <section class="hero container">
        <div class="hero-copy">

            <div class="eyebrow">
                <span class="eyebrow-dot"></span>
                YOUR NEW FAVOURITE FOOD CORNER
            </div>

            <h1>
                Good food.<br>
                <em>Questionable</em><br>
                kitchen decisions.
            </h1>

            <p class="hero-text">
                Find recipes, share your kitchen experiments, and meet people who also think cheese is a personality trait.
            </p>

            <div class="hero-actions">
                <a class="btn btn-orange" href="#discover">
                    Explore recipes <span>↗</span>
                </a>

                <a class="text-link" href="<?= $isLoggedIn ? 'add_recipe.php' : 'register.php' ?>">
                    Got a recipe? Share it <span>→</span>
                </a>
            </div>

            <div class="hero-proof">
                <div class="avatar-stack">
                    <span>🍳</span>
                    <span>🥑</span>
                    <span>🍜</span>
                    <span>🍓</span>
                </div>

                <p>
                    <strong>A growing community</strong><br>
                    of hungry, creative humans
                </p>
            </div>
        </div>

        <div class="hero-art">
            <div class="hero-image-wrap">
                <img src="assets/images/biriyani.jpg" alt="A delicious biryani dish">
            </div>

            <div class="floating-note note-top">
                <span>✦</span> made with a little chaos
            </div>

            <div class="floating-note note-bottom">
                <span>♥</span> 100% food motivated
            </div>

            <div class="hero-sticker">
                COOK<br>
                CREATE<br>
                REPEAT
                <span>↻</span>
            </div>
        </div>
    </section>

    <section class="ticker" aria-label="CraveLab motto">
        <div class="ticker-track">
            LESS SCROLLING, MORE STIRRING
            <span>✳</span>
            GOOD FOOD, GREAT MOOD
            <span>✳</span>
            NO GATEKEEPING RECIPES
            <span>✳</span>
            LESS SCROLLING, MORE STIRRING
            <span>✳</span>
            GOOD FOOD, GREAT MOOD
            <span>✳</span>
        </div>
    </section>

    <section class="section container" id="discover">
        <div class="section-heading">
            <div>
                <div class="eyebrow">THE GOOD STUFF</div>
                <h2>Fresh from the <em>kitchen.</em></h2>
            </div>

            <a class="text-link" href="recipes.php">
                See all recipes <span>→</span>
            </a>
        </div>

        <div class="recipe-grid">
            <?php foreach ($featuredRecipes as $recipe): ?>
                <article class="recipe-card">
                    <div class="recipe-image">
                        <img src="<?= htmlspecialchars($recipe['image']) ?>"
                             alt="<?= htmlspecialchars($recipe['title']) ?>">

                        <span class="image-tag">
                            <?= htmlspecialchars($recipe['category']) ?>
                        </span>

                        <button class="save-btn" type="button"
                                aria-label="Save recipe" title="Save for later">
                            ♡
                        </button>
                    </div>

                    <div class="recipe-info">
                        <div class="recipe-meta">
                            <span>◷ <?= htmlspecialchars($recipe['time']) ?></span>
                            <span>•</span>
                            <span><?= htmlspecialchars($recipe['level']) ?></span>
                        </div>

                        <h3><?= htmlspecialchars($recipe['title']) ?></h3>

                        <p><?= htmlspecialchars($recipe['description']) ?></p>

                        <a href="recipes.php" class="card-link">
                            Get cooking <span>↗</span>
                        </a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="category-section" id="categories">
        <div class="container">

            <div class="section-heading">
                <div>
                    <div class="eyebrow">WHAT ARE WE FEELING?</div>
                    <h2>Pick your <em>craving.</em></h2>
                </div>

                <p class="heading-aside">
                    The fridge is full of possibilities.<br>
                    Probably. Go check.
                </p>
            </div>

            <div class="category-grid">
                <a class="category-card category-orange" href="recipes.php?category=comfort">
                    <span class="category-emoji">🍝</span>
                    <span><strong>Comfort food</strong><small>Big hugs, but edible.</small></span>
                    <b>↗</b>
                </a>

                <a class="category-card category-green" href="recipes.php?category=quick">
                    <span class="category-emoji">🥪</span>
                    <span><strong>Quick & easy</strong><small>For the impatient chef.</small></span>
                    <b>↗</b>
                </a>

                <a class="category-card category-pink" href="recipes.php?category=sweet">
                    <span class="category-emoji">🍰</span>
                    <span><strong>Sweet tooth</strong><small>We don't judge dessert.</small></span>
                    <b>↗</b>
                </a>

                <a class="category-card category-yellow" href="recipes.php?category=experiments">
                    <span class="category-emoji">🧪</span>
                    <span><strong>Kitchen chaos</strong><small>It looked better online.</small></span>
                    <b>↗</b>
                </a>
            </div>
        </div>
    </section>

    <section class="community-section container">
        <div class="community-copy">
            <div class="eyebrow">PULL UP A CHAIR</div>

            <h2>
                Recipes are better<br>
                when they're <em>shared.</em>
            </h2>

            <p>
                Post your signature dish, swap tips, and tell us what went wrong. Especially what went wrong.
            </p>

            <a class="btn btn-dark" href="<?= $isLoggedIn ? 'add_recipe.php' : 'register.php' ?>">
                <?= $isLoggedIn ? 'Share a recipe' : 'Join the community' ?>
                <span>↗</span>
            </a>
        </div>

        <div class="community-visual">
            <img src="assets/images/spaghetti.jpg" alt="A delicious spaghetti dish">

            <div class="community-stamp">
                GOOD<br>
                FOOD<br>
                PEOPLE ✳
            </div>
        </div>
    </section>

</main>

<footer class="site-footer">
    <div class="container footer-inner">
        <a class="brand brand-light" href="index.php">
            <span class="brand-mark">C</span> crave<span>lab.</span>
        </a>

        <p>Made for the love of food (and mild kitchen disasters).</p>

        <span>© <?= date('Y') ?> CraveLab</span>
    </div>
</footer>

<script src="assets/js/script.js"></script>
</body>
</html>
