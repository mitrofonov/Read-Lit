<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/header.php';

// Проверка авторизации пользователя
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'all';

// Получение всех книг из личной библиотеки пользователя
$sql_all = "
    SELECT 
        b.id, 
        b.title, 
        c.URL AS cover_url,
        ul.last_chapter,
        (SELECT COUNT(*) FROM chapter ch WHERE ch.book_id = b.id) AS chapter_count,
        (SELECT MIN(chapter) FROM chapter WHERE book_id = b.id) AS first_chapter
    FROM user_library ul
    JOIN book b ON ul.book_id = b.id
    JOIN cover c ON b.cover_id = c.id
    WHERE ul.user_id = :user_id
    ORDER BY ul.last_visited DESC
";

// Получение только недочитанных книг (где последняя прочитанная глава не равна количеству глав)
$sql_unfinished = "
    SELECT 
        b.id, 
        b.title, 
        c.URL AS cover_url,
        ul.last_chapter,
        (SELECT COUNT(*) FROM chapter ch WHERE ch.book_id = b.id) AS chapter_count,
        (SELECT MIN(chapter) FROM chapter WHERE book_id = b.id) AS first_chapter
    FROM user_library ul
    JOIN book b ON ul.book_id = b.id
    JOIN cover c ON b.cover_id = c.id
    WHERE ul.user_id = :user_id
    AND (ul.last_chapter < (SELECT COUNT(*) FROM chapter ch WHERE ch.book_id = b.id) 
    OR ul.last_chapter IS NULL)
    ORDER BY ul.last_visited DESC
";

$stmt = $pdo->prepare($active_tab === 'unfinished' ? $sql_unfinished : $sql_all);
$stmt->execute(['user_id' => $user_id]);
$books = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Моя библиотека - Read-Lit</title>
    <link href="./css/main.css" rel="stylesheet">
    <style>
        .library-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            padding: 20px;
        }
        .library-book {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        .library-cover {
            width: 150px;
            height: 200px;
            background-size: cover;
            background-position: center;
            margin-bottom: 10px;
        }
        .library-progress {
            width: 100%;
            height: 5px;
            background-color: #ddd;
            margin-top: 5px;
            margin-bottom: 10px;
        }
        .library-progress-bar {
            height: 100%;
            background-color: #4CAF50;
        }
        .chapter-link {
            display: inline-block;
            padding: 8px 15px;
            background-color: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s;
        }
        .chapter-link:hover {
            background-color: #2980b9;
        }
        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border: 1px solid transparent;
            border-bottom: none;
            margin-right: 5px;
            background-color: #f1f1f1;
            border-radius: 5px 5px 0 0;
        }
        .tab.active {
            background-color: #fff;
            border-color: #ddd;
            border-bottom: 1px solid #fff;
            margin-bottom: -1px;
            font-weight: bold;
        }
        .tab:hover:not(.active) {
            background-color: #e9e9e9;
        }
    </style>
</head>
<body>
<div class="fix-size">
    <div class="container">
        <h1>Моя библиотека</h1>
        
        <div class="tabs">
            <a href="?tab=all" class="tab <?= $active_tab === 'all' ? 'active' : '' ?>">Все книги</a>
            <a href="?tab=unfinished" class="tab <?= $active_tab === 'unfinished' ? 'active' : '' ?>">Недочитанные</a>
        </div>
        
        <?php if (empty($books)): ?>
            <p>
                <?php if ($active_tab === 'unfinished'): ?>
                    У вас нет недочитанных книг.
                <?php else: ?>
                    Ваша библиотека пуста. Добавьте книги, чтобы они появились здесь.
                <?php endif; ?>
            </p>
        <?php else: ?>
            <div class="library-container">
                <?php foreach ($books as $book): ?>
                    <div class="library-book">
                        <div class="library-cover" style="background-image: url('<?= htmlspecialchars($book['cover_url']) ?>');"></div>
                        <h3><?= htmlspecialchars($book['title']) ?></h3>
                        <div class="library-progress">
                            <?php if ($book['last_chapter'] && $book['chapter_count'] > 0): ?>
                                <div class="library-progress-bar" style="width: <?= min(100, ($book['last_chapter'] / $book['chapter_count']) * 100) ?>%"></div>
                            <?php endif; ?>
                        </div>
                        <?php 
                            // Определяем номер главы для ссылки
                            $chapter_num = $book['last_chapter'] ? $book['last_chapter'] : $book['first_chapter'];
                        ?>
                        <a href="chapter.php?id=<?= $book['id'] ?>&chapter=<?= $chapter_num ?>" class="chapter-link">
                            Глава <?= $chapter_num ?>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
</body>
</html>