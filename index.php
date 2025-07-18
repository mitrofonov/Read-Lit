<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css?family=Nunito+Sans&display=swap" rel="stylesheet">
    <link href="./css/main.css" rel="stylesheet">
    <link href="./css/index.css" rel="stylesheet">
    <title>Read-Lit</title>
        <link href="./css/main.css" rel="stylesheet">

</head>
<body>
<?php
session_start();

require_once 'includes/config.php';
require_once 'includes/header.php';

// Запрос для получения 10 последних книг, отсортированных по дате добавления
$sqlNewBooks = "
SELECT 
    b.id, 
    b.title, 
    b.author, 
    b.time, 
    g.genre AS main_genre, 
    c.URL AS cover_url,
    sb.`book status` AS book_status
FROM book b
LEFT JOIN genre g ON b.`main genre` = g.id
LEFT JOIN cover c ON b.cover_id = c.id
LEFT JOIN status_book sb ON b.status = sb.id
ORDER BY b.time DESC
LIMIT 10
";
$stmtNewBooks = $pdo->query($sqlNewBooks);
$newBooks = $stmtNewBooks->fetchAll();

// Запрос для получения 10 книг с наибольшим количеством лайков (рейтинг за все время)
$sqlTopBooks = "
    SELECT 
        b.id, 
        b.title, 
        b.author, 
        b.time, 
        g.genre AS main_genre, 
        c.URL AS cover_url,
        sb.`book status` AS book_status,
        COUNT(l.book_id) AS likes,
        (SELECT annotation FROM annotation WHERE book_id = b.id LIMIT 1) AS annotation
    FROM book b
    JOIN genre g ON b.`main genre` = g.id
    JOIN cover c ON b.cover_id = c.id
    JOIN status_book sb ON b.status = sb.id
    LEFT JOIN like_book l ON b.id = l.book_id
    GROUP BY b.id, b.title, b.author, b.time, g.genre, c.URL, sb.`book status`
    ORDER BY likes DESC
    LIMIT 10
";
$stmtTopBooks = $pdo->query($sqlTopBooks);
$topBooks = $stmtTopBooks->fetchAll();

?> 

<div class="fix-size">
    <div class="layout-body container">
        <div class="carousel-container">
            <div class="carousel-header">
                <span class="carousel-title">Новые Выпуски</span>
                <span class="carousel-more"><a href="books.php">Больше</a></span>
            </div>
            <div class="carousel" id="carousel">
                <?php foreach ($newBooks as $book): ?>
                    <a href="book.php?id=<?= htmlspecialchars($book['id']) ?>">
                        <div class="card">
                            <div class="card-image" style="background-image: url('<?= htmlspecialchars($book['cover_url']) ?>');">
                                <span class="card-badge"><?= htmlspecialchars($book['book_status']) ?></span>
                            </div>
                            <div class="card-content">
                                <div class="card-title"><?= htmlspecialchars($book['title']) ?></div>
                                <div class="card-genre"><?= htmlspecialchars($book['main_genre']) ?></div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="rating-container">
            <div class="rating-header">
                <span class="rating-title">Рейтинг книг</span>
                <span class="rating-more"><a href="rating.php">Больше</a></span>
            </div>
            <div class="rating-tabs">
                <button class="tab-button active" data-tab="day">День</button>
                <button class="tab-button" data-tab="week">Неделя</button>
            </div>
            <div class="rating-content active" id="day-content">
                <?php 
                // Запрос для получения топ-5 книг за день
                $sqlDayTop = "
                    SELECT 
                        b.id, 
                        b.title, 
                        c.URL AS cover_url,
                        COUNT(l.book_id) AS likes
                    FROM book b
                    JOIN cover c ON b.cover_id = c.id
                    LEFT JOIN like_book l ON b.id = l.book_id AND l.time >= NOW() - INTERVAL 1 DAY
                    GROUP BY b.id, b.title, c.URL
                    ORDER BY likes DESC
                    LIMIT 5
                ";
                $stmtDayTop = $pdo->query($sqlDayTop);
                $dayTopBooks = $stmtDayTop->fetchAll();
                ?>
                
                <?php foreach ($dayTopBooks as $index => $book): ?>
                    <div class="rating-item">
                        <div class="item-image">
                            <img src="<?= htmlspecialchars($book['cover_url']) ?>" alt="<?= htmlspecialchars($book['title']) ?>">
                        </div>
                        <div class="item-details">
                            <div class="item-title">#<?= $index + 1 ?> <?= htmlspecialchars($book['title']) ?></div>
                            <div class="item-stats">
                                <span class="likes"><?= htmlspecialchars($book['likes']) ?> likes</span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="rating-content hidden" id="week-content">
                <?php 
                // Запрос для получения топ-5 книг за неделю
                $sqlWeekTop = "
                    SELECT 
                        b.id, 
                        b.title, 
                        c.URL AS cover_url,
                        COUNT(l.book_id) AS likes
                    FROM book b
                    JOIN cover c ON b.cover_id = c.id
                    LEFT JOIN like_book l ON b.id = l.book_id AND l.time >= NOW() - INTERVAL 1 WEEK
                    GROUP BY b.id, b.title, c.URL
                    ORDER BY likes DESC
                    LIMIT 5
                ";
                $stmtWeekTop = $pdo->query($sqlWeekTop);
                $weekTopBooks = $stmtWeekTop->fetchAll();
                ?>
                
                <?php foreach ($weekTopBooks as $index => $book): ?>
                    <div class="rating-item">
                        <div class="item-image">
                            <img src="<?= htmlspecialchars($book['cover_url']) ?>" alt="<?= htmlspecialchars($book['title']) ?>">
                        </div>
                        <div class="item-details">
                            <div class="item-title">#<?= $index + 1 ?> <?= htmlspecialchars($book['title']) ?></div>
                            <div class="item-stats">
                                <span class="likes"><?= htmlspecialchars($book['likes']) ?> likes</span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="rating-container">
            <div class="rating-header">
                <span class="rating-title">Рейтинг за всё время</span>
                <span class="rating-more"><a href="rating.php">Больше</a></span>
            </div>
            <div class="rating-thumbnails">
                <?php foreach ($topBooks as $book): ?>
                    <div class="thumbnail" 
                         data-large-image="<?= htmlspecialchars($book['cover_url']) ?>"
                         data-title="<?= htmlspecialchars($book['title']) ?>"
                         data-annotation="<?= isset($book['annotation']) ? htmlspecialchars($book['annotation']) : 'Аннотация отсутствует' ?>">
                        <img src="<?= htmlspecialchars($book['cover_url']) ?>" alt="<?= htmlspecialchars($book['title']) ?>">
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="rating-large-image">
                <div id="large-image-container">
                    <img id="large-image" src="<?= !empty($topBooks) ? htmlspecialchars($topBooks[0]['cover_url']) : '' ?>" alt="Large Image">
                    <div id="large-image-info">
                        <h3 id="large-image-title"><?= !empty($topBooks) ? htmlspecialchars($topBooks[0]['title']) : '' ?></h3>
                        <p id="large-image-annotation"><?= !empty($topBooks) ? (isset($topBooks[0]['annotation']) ? htmlspecialchars($topBooks[0]['annotation']) : 'Аннотация отсутствует') : '' ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Табы для рейтинга (день/неделя)
        const tabButtons = document.querySelectorAll('.tab-button');
        const dayContent = document.getElementById('day-content');
        const weekContent = document.getElementById('week-content');

        tabButtons.forEach(button => {
            button.addEventListener('click', function () {
                tabButtons.forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');

                if (this.dataset.tab === 'day') {
                    dayContent.classList.remove('hidden');
                    weekContent.classList.add('hidden');
                } else if (this.dataset.tab === 'week') {
                    dayContent.classList.add('hidden');
                    weekContent.classList.remove('hidden');
                }
            });
        });

        // Миниатюры для рейтинга за все время
        const thumbnails = document.querySelectorAll('.thumbnail');
        const largeImage = document.getElementById('large-image');
        const largeImageTitle = document.getElementById('large-image-title');
        const largeImageAnnotation = document.getElementById('large-image-annotation');

        thumbnails.forEach(thumbnail => {
            thumbnail.addEventListener('click', function () {
                thumbnails.forEach(t => t.classList.remove('selected'));
                this.classList.add('selected');
                largeImage.src = this.dataset.largeImage;
                largeImageTitle.textContent = this.dataset.title;
                largeImageAnnotation.textContent = this.dataset.annotation;
            });
        });

        // Автоматическое выделение первой миниатюры
        if (thumbnails.length > 0) {
            thumbnails[0].classList.add('selected');
        }
    });
</script>

<?php
require_once 'includes/footer.php';
?>
</body>
</html>