<?php
ob_start();
session_start();

require_once 'includes/config.php';

// Проверка статуса пользователя
$is_admin = false;
$user_id = $_SESSION['user_id'] ?? null;

if ($user_id !== null) {
    $sql = "SELECT status_id FROM user WHERE id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['user_id' => $user_id]);
    $user = $stmt->fetch();
    if ($user && $user['status_id'] == 1) {
        $is_admin = true;
    }
}

// Обработка AJAX-запросов для лайков
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'like_book_ajax') {
    $book_id = $_POST['book_id'] ?? null;
    $user_id = $_SESSION['user_id'] ?? null;
    
    if (!$book_id || !$user_id) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid request']);
        exit;
    }
    
    // Проверка, поставил ли пользователь уже лайк
    $sql = "SELECT * FROM like_book WHERE book_id = :book_id AND user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['book_id' => $book_id, 'user_id' => $user_id]);
    $like = $stmt->fetch();

    if ($like) {
        // Если лайк уже есть, удаляем его
        $sql = "DELETE FROM like_book WHERE book_id = :book_id AND user_id = :user_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['book_id' => $book_id, 'user_id' => $user_id]);
        $action = 'unliked';
    } else {
        // Если лайка нет, добавляем его
        $sql = "INSERT INTO like_book (book_id, user_id, time) VALUES (:book_id, :user_id, NOW())";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['book_id' => $book_id, 'user_id' => $user_id]);
        $action = 'liked';
    }

    // Получаем общее количество лайков
    $sql = "SELECT COUNT(*) as like_count FROM like_book WHERE book_id = :book_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['book_id' => $book_id]);
    $like_count = $stmt->fetch()['like_count'];

    // Возвращаем результат
    header('Content-Type: application/json');
    echo json_encode(['action' => $action, 'like_count' => $like_count]);
    exit;
}

// Обработка POST-запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Обработка лайков комментариев
    if ($_POST['action'] === 'like_comment') {
        $comment_id = $_POST['comment_id'] ?? 0;
        $user_id = $_SESSION['user_id'] ?? 0;
        $book_id = $_POST['book_id'] ?? 0;

        if ($comment_id && $user_id && $book_id) {
            $sql = "SELECT * FROM like_comment WHERE user_id = :user_id AND coment_id = :comment_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['user_id' => $user_id, 'comment_id' => $comment_id]);
            $like = $stmt->fetch();

            if ($like) {
                $sql = "DELETE FROM like_comment WHERE user_id = :user_id AND coment_id = :comment_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['user_id' => $user_id, 'comment_id' => $comment_id]);
            } else {
                $sql = "INSERT INTO like_comment (user_id, coment_id, book_id) VALUES (:user_id, :comment_id, :book_id)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    'user_id' => $user_id,
                    'comment_id' => $comment_id,
                    'book_id' => $book_id
                ]);
            }
            header("Location: book.php?id=" . $book_id . "&tab=reviews");
            exit;
        }
    }

    // Обработка добавления комментария
    if ($_POST['action'] === 'add_comment' && isset($_SESSION['user_id'])) {
        $book_id = $_POST['book_id'] ?? 0;
        $comment_text = $_POST['comment_text'] ?? '';
        
        if ($book_id && $comment_text) {
            $sql = "INSERT INTO comment (user_id, comment, date, book_id) VALUES (:user_id, :comment, NOW(), :book_id)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'user_id' => $_SESSION['user_id'],
                'comment' => $comment_text,
                'book_id' => $book_id
            ]);
            
            header("Location: book.php?id=" . $book_id . "&tab=reviews");
            exit;
        }
    }

    // Обработка удаления комментария
    if ($_POST['action'] === 'delete_comment') {
        $comment_id = $_POST['comment_id'] ?? 0;
        $book_id = $_POST['book_id'] ?? 0;
        $user_id = $_SESSION['user_id'] ?? 0;

        if ($comment_id && $book_id && $user_id) {
            // Проверяем, может ли пользователь удалить этот комментарий
            $sql = "SELECT user_id FROM comment WHERE id = :comment_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['comment_id' => $comment_id]);
            $comment = $stmt->fetch();

            if ($comment && ($is_admin || $comment['user_id'] == $user_id)) {
                // Сначала удаляем все лайки этого комментария
                $sql = "DELETE FROM like_comment WHERE coment_id = :comment_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['comment_id' => $comment_id]);
                
                // Затем удаляем сам комментарий
                $sql = "DELETE FROM comment WHERE id = :comment_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['comment_id' => $comment_id]);
            }
            
            header("Location: book.php?id=" . $book_id . "&tab=reviews");
            exit;
        }
    }

    // Обработка удаления главы
    if ($_POST['action'] === 'delete_chapter' && $is_admin) {
        $chapter_id = $_POST['chapter_id'] ?? 0;
        $book_id = $_POST['book_id'] ?? 0;

        if ($chapter_id && $book_id) {
            try {
                // Получаем информацию о главе
                $sql = "SELECT chapter_URL FROM chapter WHERE id = :chapter_id AND book_id = :book_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['chapter_id' => $chapter_id, 'book_id' => $book_id]);
                $chapter = $stmt->fetch();

                if ($chapter) {
                    // Удаляем файл главы
                    if (file_exists($chapter['chapter_URL'])) {
                        unlink($chapter['chapter_URL']);
                    }

                    // Удаляем запись о главе
                    $sql = "DELETE FROM chapter WHERE id = :chapter_id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(['chapter_id' => $chapter_id]);

                    header("Location: book.php?id=" . $book_id . "&tab=contents");
                    exit;
                }
            } catch (Exception $e) {
                die("Ошибка при удалении главы: " . $e->getMessage());
            }
        }
    }

    // Обработка удаления книги
    if ($_POST['action'] === 'delete_book' && $is_admin) {
        $book_id = $_POST['book_id'] ?? 0;
        
        if ($book_id) {
            try {
                $pdo->beginTransaction();
                
                // 1. Удаляем записи из библиотек пользователей
                $sql = "DELETE FROM user_library WHERE book_id = :book_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['book_id' => $book_id]);
                
                // 2. Удаляем лайки комментариев книги
                $sql = "DELETE lc FROM like_comment lc 
                        JOIN comment c ON lc.coment_id = c.id 
                        WHERE c.book_id = :book_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['book_id' => $book_id]);
                
                // 3. Удаляем комментарии книги
                $sql = "DELETE FROM comment WHERE book_id = :book_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['book_id' => $book_id]);
                
                // 4. Удаляем лайки книги
                $sql = "DELETE FROM like_book WHERE book_id = :book_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['book_id' => $book_id]);
                
                // 5. Удаляем главы книги
                $sql = "SELECT chapter_URL FROM chapter WHERE book_id = :book_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['book_id' => $book_id]);
                $chapters = $stmt->fetchAll();
                
                $sql = "DELETE FROM chapter WHERE book_id = :book_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['book_id' => $book_id]);
                
                // 6. Удаляем аннотацию книги
                $sql = "DELETE FROM annotation WHERE book_id = :book_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['book_id' => $book_id]);
                
                // 7. Получаем информацию об обложке
                $sql = "SELECT cover_id, URL FROM book JOIN cover ON book.cover_id = cover.id WHERE book.id = :book_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['book_id' => $book_id]);
                $book_data = $stmt->fetch();
                
                // 8. Удаляем запись о книге
                $sql = "DELETE FROM book WHERE id = :book_id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['book_id' => $book_id]);
                
                // 9. Удаляем обложку
                if ($book_data) {
                    $sql = "DELETE FROM cover WHERE id = :cover_id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(['cover_id' => $book_data['cover_id']]);
                    
                    // Удаляем файлы
                    if (file_exists($book_data['URL'])) {
                        unlink($book_data['URL']);
                    }
                }
                
                // 10. Удаляем файлы глав
                foreach ($chapters as $chapter) {
                    if (file_exists($chapter['chapter_URL'])) {
                        unlink($chapter['chapter_URL']);
                    }
                }
                
                $pdo->commit();
                
                header("Location: index.php");
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                die("Ошибка при удалении книги: " . $e->getMessage());
            }
        }
    }
}

// Получение ID книги из URL
$book_id = $_GET['id'] ?? null;

if (!$book_id) {
    die("Book ID is missing.");
}

// Определение активной вкладки
$active_tab = $_GET['tab'] ?? 'about';

// Запрос информации о книге
$sql = "SELECT b.id, b.title, b.author, b.time, g.genre AS main_genre, 
        c.URL AS cover_url, sb.`book status` AS book_status,
        (SELECT COUNT(*) FROM chapter ch WHERE ch.book_id = b.id) AS chapter_count
        FROM book b
        JOIN genre g ON b.`main genre` = g.id
        JOIN cover c ON b.cover_id = c.id
        JOIN status_book sb ON b.status = sb.id
        WHERE b.id = :book_id";
$stmt = $pdo->prepare($sql);
$stmt->execute(['book_id' => $book_id]);
$book = $stmt->fetch();

if (!$book) {
    die("Book not found.");
}

// Получение аннотации
$annotation = '';
$sql = "SELECT annotation FROM annotation WHERE book_id = :book_id";
$stmt = $pdo->prepare($sql);
$stmt->execute(['book_id' => $book_id]);
$annotation_row = $stmt->fetch();
if ($annotation_row) {
    $annotation = $annotation_row['annotation'];
}

// Получение количества лайков
$sql = "SELECT COUNT(*) as like_count FROM like_book WHERE book_id = :book_id";
$stmt = $pdo->prepare($sql);
$stmt->execute(['book_id' => $book_id]);
$like_count = $stmt->fetch()['like_count'];

// Получение количества комментариев
$sql = "SELECT COUNT(*) as comment_count FROM comment WHERE book_id = :book_id";
$stmt = $pdo->prepare($sql);
$stmt->execute(['book_id' => $book_id]);
$comment_count = $stmt->fetch()['comment_count'];

// Получение комментариев
$sql = "SELECT c.id, c.comment, c.date, u.nickname, 
        c.user_id AS comment_user_id, COUNT(lc.coment_id) as like_count
        FROM comment c
        LEFT JOIN user u ON c.user_id = u.id
        LEFT JOIN like_comment lc ON c.id = lc.coment_id
        WHERE c.book_id = :book_id
        GROUP BY c.id
        ORDER BY c.date DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute(['book_id' => $book_id]);
$comments = $stmt->fetchAll();

// Получение глав книги
$sql = "SELECT * FROM chapter WHERE book_id = :book_id ORDER BY chapter ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute(['book_id' => $book_id]);
$chapters = $stmt->fetchAll();

// Получение первой главы для кнопки "Начать чтение"
$first_chapter = null;
if (!empty($chapters)) {
    $first_chapter = $chapters[0];
}

require_once 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css?family=Nunito+Sans&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="./css/main.css" rel="stylesheet">
    <link href="./css/book.css" rel="stylesheet">
    <title><?= htmlspecialchars($book['title']) ?> - Read-Lit</title>
    <style>
        /* Общие стили */
        .delete-comment-button {
            background-color: #ff4d4d;
            color: white;
            border: none;
            padding: 5px 10px;
            cursor: pointer;
            border-radius: 4px;
            margin-left: 10px;
        }

        .delete-comment-button:hover {
            background-color: #cc0000;
        }

        .tab.active {
            font-weight: bold;
            border-bottom: 2px solid #000;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .stat-icon {
            margin-right: 8px;
            color: #555;
        }

        .like-count i, .rating-count i {
            margin-right: 5px;
        }

        .action-button i {
            margin-right: 8px;
        }

        /* Стили для комментариев */
        .comment {
            padding: 15px;
            margin-bottom: 15px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-left: 3px solid #4a90e2;
        }

        .comment-author {
            font-weight: bold;
            margin-bottom: 8px;
            color: #333;
        }

        .comment-author i {
            color: #6c757d;
            margin-right: 8px;
        }

        .comment-text {
            margin-bottom: 10px;
            line-height: 1.5;
            color: #495057;
        }

        .comment-date {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 10px;
        }

        .comment-date i {
            margin-right: 5px;
        }

        .comment-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .like-btn, .delete-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            padding: 5px 10px;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .like-btn {
            color: #6c757d;
        }

        .like-btn:hover {
            color: #dc3545;
            background: rgba(220, 53, 69, 0.1);
        }

        .delete-btn {
            color: #dc3545;
            background: rgba(220, 53, 69, 0.1);
        }

        .delete-btn:hover {
            background: rgba(220, 53, 69, 0.2);
        }

        /* Форма комментария */
        .comment-form-container {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .comment-form-title {
            font-size: 1.2rem;
            margin-bottom: 15px;
            color: #333;
            font-weight: 600;
        }

        .comment-form-title i {
            color: #4a90e2;
            margin-right: 10px;
        }

        #comment-form textarea {
            width: 100%;
            min-height: 120px;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            resize: vertical;
            font-family: 'Nunito Sans', sans-serif;
            font-size: 14px;
            margin-bottom: 15px;
            transition: all 0.3s;
        }

        #comment-form textarea:focus {
            outline: none;
            border-color: #4a90e2;
            box-shadow: 0 0 0 2px rgba(74,144,226,0.2);
        }

        #comment-form button[type="submit"] {
            background-color: #4a90e2;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
        }

        #comment-form button[type="submit"]:hover {
            background-color: #3a7bc8;
        }

        #comment-form button[type="submit"] i {
            margin-right: 8px;
        }

        .auth-notice {
            margin-top: 20px;
            padding: 15px;
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            color: #856404;
            border-radius: 4px;
        }

        .auth-notice i {
            margin-right: 8px;
            color: #ffc107;
        }

        .auth-notice a {
            color: #0056b3;
            text-decoration: underline;
        }

        /* Стили для глав */
        .chapter-item {
            position: relative;
            padding: 10px;
            margin-bottom: 5px;
            background: #f8f9fa;
            border-radius: 4px;
        }

        .chapter-item:hover {
            background: #e9ecef;
        }

        .delete-chapter-btn {
            position: absolute;
            right: 10px;
            background: none;
            border: none;
            color: #dc3545;
            cursor: pointer;
        }
    </style>
</head>
<body>

<div class="fix-size">
    <div class="container">
        <div class="content">
            <div class="novel-details">
                <div class="novel-cover">
                    <div class="cover-image" style="background-image: url('<?= htmlspecialchars($book['cover_url']) ?>');"></div>
                </div>
                <div class="novel-info">
                    <h1 class="novel-title"><?= htmlspecialchars($book['title']) ?></h1>
                    <h2 class="novel-subtitle"><?= htmlspecialchars($book['author']) ?></h2>
                    <div class="novel-stats">
                        <div class="stat-item">
                            <div class="stat-icon"><i class="fas fa-book-open"></i></div>
                            <span class="stat-text"><?= htmlspecialchars($book['book_status']) ?></span>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon"><i class="fas fa-list-ol"></i></div>
                            <span class="stat-text"><?= htmlspecialchars($book['chapter_count']) ?> глав</span>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon"><i class="far fa-calendar-alt"></i></div>
                            <span class="stat-text">добавлено <?= htmlspecialchars($book['time']) ?></span>
                        </div>
                    </div>
                    <div class="novel-rating">
                        <button id="like-button" class="like-count">
                            <i class="far fa-heart"></i> <span id="like-count"><?= $like_count ?></span> лайков
                        </button>
                        <span class="rating-count"><i class="far fa-comment"></i> <?= $comment_count ?> reviews</span>
                    </div>
                    <div class="novel-author">
                        <span class="author-label"><i class="fas fa-user-edit"></i> Author:</span>
                        <span class="author-name"><?= htmlspecialchars($book['author']) ?></span>
                    </div>
                    <div class="novel-actions">
                        <?php if ($first_chapter): ?>
                            <a href="chapter.php?id=<?= $book_id ?>&chapter=<?= $first_chapter['chapter'] ?>" class="action-button start-reading"><i class="fas fa-book-reader"></i> начать чтение</a>
                        <?php else: ?>
                            <button class="action-button start-reading" disabled><i class="fas fa-book-reader"></i> нет глав</button>
                        <?php endif; ?>
                        <form method="POST" action="handle_library.php" style="display: inline;">
                            <input type="hidden" name="book_id" value="<?= $book_id ?>">
                            <input type="hidden" name="action" value="add_to_library">
                            <button type="submit" class="action-button"><i class="fas fa-plus-circle"></i> В библиотеку</button>
                        </form> 
                        <?php if ($is_admin): ?>
                            <form method="POST" action="" style="display: inline;">
                                <input type="hidden" name="book_id" value="<?= $book_id ?>">
                                <input type="hidden" name="action" value="delete_book">
                                <button type="submit" class="action-button delete" onclick="return confirm('Вы уверены, что хотите удалить эту книгу?')">
                                    <i class="fas fa-trash-alt"></i> удалить
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Вкладки -->
            <div class="novel-tabs">
                <a href="?id=<?= $book_id ?>&tab=about" class="tab <?= $active_tab === 'about' ? 'active' : '' ?>"><i class="fas fa-info-circle"></i> о книге</a>
                <a href="?id=<?= $book_id ?>&tab=contents" class="tab <?= $active_tab === 'contents' ? 'active' : '' ?>"><i class="fas fa-list-ul"></i> содержание</a>
                <a href="?id=<?= $book_id ?>&tab=reviews" class="tab <?= $active_tab === 'reviews' ? 'active' : '' ?>"><i class="fas fa-comments"></i> коментарии</a>
            </div>

            <!-- Содержимое вкладок -->
            <div class="novel-content">
                <!-- Вкладка About -->
                <div class="tab-content <?= $active_tab === 'about' ? 'active' : '' ?>">
                    <div class="novel-summary">
                        <h2 class="summary-title">описание</h2>
                        <p class="summary-text">
                            <?= htmlspecialchars($annotation) ?>
                        </p>
                    </div>

                    <?php if ($is_admin): ?>
                        <button class="edit-details-button" onclick="openAnnotationModal()"><i class="fas fa-edit"></i> Добавить описание</button>
                    <?php endif; ?>

                    <div class="novel-details-section">
                        <h2 class="details-title">детали</h2>
                        <div class="details-content">
                            <div class="detail-item">
                                <span class="detail-label">название</span>
                                <span class="detail-value"><?= htmlspecialchars($book['title']) ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">статус книги</span>
                                <span class="detail-value"><?= htmlspecialchars($book['book_status']) ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">автор</span>
                                <span class="detail-value"><?= htmlspecialchars($book['author']) ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">жанры</span>
                                <span class="detail-value"><?= htmlspecialchars($book['main_genre']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Вкладка Contents -->
                <div class="tab-content <?= $active_tab === 'contents' ? 'active' : '' ?>">
                    <h2><i class="fas fa-list-ul"></i> содержание</h2>
                    <div class="chapters-list">
                        <?php foreach ($chapters as $chapter): ?>
                            <div class="chapter-item">
                                <a href="chapter.php?id=<?= $book_id ?>&chapter=<?= $chapter['chapter'] ?>">
                                    <i class="fas fa-bookmark"></i> 
                                    <?php if (!empty($chapter['title'])): ?>
                                        <?= htmlspecialchars($chapter['title']) ?>
                                    <?php else: ?>
                                        Глава <?= htmlspecialchars($chapter['chapter']) ?>
                                    <?php endif; ?>
                                </a>
                                <?php if ($is_admin): ?>
                                    <form method="POST" action="" class="delete-chapter-form" style="display: inline;">
                                        <input type="hidden" name="chapter_id" value="<?= $chapter['id'] ?>">
                                        <input type="hidden" name="book_id" value="<?= $book_id ?>">
                                        <input type="hidden" name="action" value="delete_chapter">
                                        <button type="submit" class="delete-chapter-btn" onclick="return confirm('Вы уверены, что хотите удалить эту главу?')">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($is_admin): ?>
                        <h3><i class="fas fa-plus"></i> добавить главу</h3>
                        <a href="add_chapter.php?book_id=<?= $book_id ?>"><i class="fas fa-plus-circle"></i> Добавить главу</a>
                    <?php endif; ?>
                </div>

                <!-- Вкладка Reviews -->
                <div class="tab-content <?= $active_tab === 'reviews' ? 'active' : '' ?>">
                    <div id="comments-container">
                        <?php foreach ($comments as $comment): ?>
                            <div class="comment" data-comment-id="<?= $comment['id'] ?>">
                                <div class="comment-author">
                                    <i class="fas fa-user-circle"></i> <?= htmlspecialchars($comment['nickname']) ?>
                                </div>
                                <div class="comment-text">
                                    <?= htmlspecialchars($comment['comment']) ?>
                                </div>
                                <div class="comment-date">
                                    <i class="far fa-clock"></i> <?= htmlspecialchars($comment['date']) ?>
                                </div>
                                <div class="comment-actions">
                                    <form class="like-form" method="POST" action="">
                                        <input type="hidden" name="comment_id" value="<?= $comment['id'] ?>">
                                        <input type="hidden" name="book_id" value="<?= $book_id ?>">
                                        <input type="hidden" name="action" value="like_comment">
                                        <button type="submit" class="like-btn">
                                            <i class="far fa-heart"></i> <?= $comment['like_count'] ?>
                                        </button>
                                    </form>
                                    
                                    <?php if (isset($_SESSION['user_id']) && ($is_admin || $comment['comment_user_id'] == $_SESSION['user_id'])): ?>
                                        <form class="delete-form" method="POST" action="">
                                            <input type="hidden" name="comment_id" value="<?= $comment['id'] ?>">
                                            <input type="hidden" name="book_id" value="<?= $book_id ?>">
                                            <input type="hidden" name="action" value="delete_comment">
                                            <button type="submit" class="delete-btn"><i class="fas fa-trash-alt"></i> Удалить</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Форма добавления комментария -->
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="comment-form-container">
                            <h3 class="comment-form-title"><i class="fas fa-comment-dots"></i> Оставить комментарий</h3>
                            <form id="comment-form" method="POST" action="">
                                <textarea name="comment_text" placeholder="Поделитесь вашими мыслями о книге..." required></textarea>
                                <input type="hidden" name="book_id" value="<?= $book_id ?>">
                                <input type="hidden" name="action" value="add_comment">
                                <button type="submit"><i class="fas fa-paper-plane"></i> Отправить комментарий</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="auth-notice">
                            <p><i class="fas fa-exclamation-circle"></i> Чтобы оставить комментарий, пожалуйста <a href="login.php">войдите</a> в систему.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Модальное окно для добавления описания -->
    <div id="annotationModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAnnotationModal()">&times;</span>
            <h2><i class="fas fa-edit"></i> Добавить описание</h2>
            <form action="add_annotation.php" method="POST">
                <textarea name="annotation" rows="10" cols="50" placeholder="Введите описание книги"></textarea>
                <input type="hidden" name="book_id" value="<?= $book_id ?>">
                <button type="submit"><i class="fas fa-save"></i> Сохранить</button>
            </form>
        </div>
    </div>
</div>

<script>
    function openAnnotationModal() {
        document.getElementById('annotationModal').style.display = 'block';
    }

    function closeAnnotationModal() {
        document.getElementById('annotationModal').style.display = 'none';
    }

    // AJAX для лайков книги
    document.getElementById('like-button').addEventListener('click', function() {
        const bookId = <?= $book_id ?>;
        const userId = <?= $_SESSION['user_id'] ?? 0 ?>;
        
        if (!userId) {
            alert('Пожалуйста, войдите в систему, чтобы ставить лайки');
            return;
        }

        fetch('book.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=like_book_ajax&book_id=${bookId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
            } else {
                const likeButton = document.getElementById('like-button');
                const likeCount = document.getElementById('like-count');
                
                likeCount.textContent = data.like_count;
                
                if (data.action === 'liked') {
                    likeButton.innerHTML = '<i class="fas fa-heart"></i> ' + data.like_count + ' лайков';
                } else {
                    likeButton.innerHTML = '<i class="far fa-heart"></i> ' + data.like_count + ' лайков';
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    });
</script>

<?php
require_once 'includes/footer.php';
ob_end_flush();
?>
</body>
</html>