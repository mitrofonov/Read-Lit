<?php
session_start();

require_once 'includes/config.php';
require_once 'includes/header.php';

// Получение параметров сортировки, поиска, пагинации, статуса и жанра
$sort = $_GET['sort'] ?? 'time'; // По умолчанию сортируем по дате добавления
$order = $_GET['order'] ?? 'DESC'; // По умолчанию сортируем по убыванию
$search = $_GET['search'] ?? ''; // Поисковый запрос
$status = $_GET['status'] ?? 'all'; // Статус книги (all, 1, 2)
$genre = $_GET['genre'] ?? 'all'; // Жанр книги (all или ID жанра)
$page = $_GET['page'] ?? 1; // Текущая страница
$perPage = 10; // Количество книг на странице

// Подготовка SQL-запроса с учетом сортировки, поиска, статуса и жанра
$sql = "
    SELECT 
        b.id, 
        b.title, 
        b.author, 
        b.time, 
        g.genre AS main_genre, 
        c.URL AS cover_url,
        sb.`book status` AS book_status,
        (SELECT COUNT(*) FROM chapter ch WHERE ch.book_id = b.id) AS chapter_count
    FROM book b
    JOIN genre g ON b.`main genre` = g.id
    JOIN cover c ON b.cover_id = c.id
    JOIN status_book sb ON b.status = sb.id
    WHERE b.title LIKE :search
";

// Добавляем условия для статуса
if ($status !== 'all') {
    $sql .= " AND b.status = :status";
}

// Добавляем условия для жанра
if ($genre !== 'all') {
    $sql .= " AND b.`main genre` = :genre";
}

$sql .= "
    ORDER BY $sort $order
    LIMIT :offset, :perPage
";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);

// Привязываем параметры статуса и жанра, если они заданы
if ($status !== 'all') {
    $stmt->bindValue(':status', $status, PDO::PARAM_INT);
}
if ($genre !== 'all') {
    $stmt->bindValue(':genre', $genre, PDO::PARAM_INT);
}

$stmt->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
$stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
$stmt->execute();
$books = $stmt->fetchAll();

// Получение общего количества книг для пагинации
$sqlCount = "SELECT COUNT(*) as total FROM book b WHERE b.title LIKE :search";

if ($status !== 'all') {
    $sqlCount .= " AND b.status = :status";
}
if ($genre !== 'all') {
    $sqlCount .= " AND b.`main genre` = :genre";
}

$stmtCount = $pdo->prepare($sqlCount);
$stmtCount->bindValue(':search', "%$search%", PDO::PARAM_STR);

if ($status !== 'all') {
    $stmtCount->bindValue(':status', $status, PDO::PARAM_INT);
}
if ($genre !== 'all') {
    $stmtCount->bindValue(':genre', $genre, PDO::PARAM_INT);
}

$stmtCount->execute();
$totalBooks = $stmtCount->fetch()['total'];
$totalPages = ceil($totalBooks / $perPage);

// Получение списка жанров для фильтра
$genres = $pdo->query("SELECT * FROM genre WHERE id != 0")->fetchAll();

// Получение списка статусов книг
$statuses = $pdo->query("SELECT * FROM status_book")->fetchAll();
?>

<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://fonts.googleapis.com/css?family=Nunito+Sans&display=swap" rel="stylesheet">
    <link href="./css/main.css" rel="stylesheet">
    <link href="./css/books.css" rel="stylesheet">
    <title>Все книги - Read-Lit</title>
    <style>
        /* Стили для поиска и фильтров */
        .search-filter {
            margin-bottom: 20px;
        }
        
        .search-filter .input-group {
            display: flex;
            width: 100%;
        }
        
        .search-filter input.form-control {
            flex: 1;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px 0 0 4px;
            font-size: 14px;
        }
        
        .search-filter .btn {
            padding: 8px 16px;
            border-radius: 0 4px 4px 0;
            font-size: 14px;
        }
        
        .filters-container {
            display: none;
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .filters-container.visible {
            display: block;
        }
        
        .filter-toggle {
            background: #343a40;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-toggle .icon {
            width: 16px;
            height: 16px;
        }
        
        .order-title {
            display: block;
            margin: 15px 0 8px;
            font-weight: 600;
        }
        
        .btn-group-sm {
            margin-bottom: 15px;
        }
        
        .genre-filter {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 15px;
        }
        
        .genre-item {
            padding: 6px 12px;
            background: #f8f9fa;
            border-radius: 4px;
            color: #343a40;
            text-decoration: none;
            font-size: 14px;
            border: 1px solid #ddd;
        }
        
        .genre-item.active, .genre-item:hover {
            background: #343a40;
            color: white;
            border-color: #343a40;
        }
    </style>
</head>

<body>
<div class="fix-size">
    <div class="container">
        <div class="content">
            <h1>Все книги</h1>

            <!-- Кнопка для показа/скрытия фильтров -->
            <button class="filter-toggle" id="filterToggle">
                <svg class="icon" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                </svg>
                Фильтры
            </button>

            <!-- Контейнер с фильтрами (изначально скрыт) -->
            <div class="filters-container" id="filtersContainer">
                <div class="card-body">
                    <div class="page-title">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24">
                            <path d="M9 3v15h3V3zm3 2 4 13 3-1-4-13zM5 5v13h3V5zM3 19v2h18v-2z"></path>
                        </svg>Фильтрация книг
                    </div>
                    <hr>
                    
                    <!-- Поиск по названию -->
                    <div class="search-filter">
                        <form method="GET" action="">
                            <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                            <input type="hidden" name="order" value="<?= htmlspecialchars($order) ?>">
                            <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
                            <input type="hidden" name="genre" value="<?= htmlspecialchars($genre) ?>">
                            <input type="hidden" name="page" value="1">
                            
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" placeholder="Поиск по названию книги" value="<?= htmlspecialchars($search) ?>">
                                <button class="btn btn-dark" type="submit">Найти</button>
                                <?php if ($search): ?>
                                    <a href="?sort=<?= $sort ?>&order=<?= $order ?>&status=<?= $status ?>&genre=<?= $genre ?>&page=1" class="btn btn-outline-secondary">Сбросить</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                    <hr>
                    
                    <!-- Сортировка -->
                    <span class="order-title">Сортировать по:</span>
                    <div role="group" class="btn-group btn-group-sm">
                        <a role="button" tabindex="0" 
                           href="?sort=time&order=<?= $order ?>&status=<?= $status ?>&genre=<?= $genre ?>&search=<?= urlencode($search) ?>&page=1" 
                           class="btn <?= $sort === 'time' ? 'btn-dark' : 'btn-outline-secondary' ?>">
                            Дате добавления
                        </a>
                        <a role="button" tabindex="0" 
                           href="?sort=title&order=<?= $order ?>&status=<?= $status ?>&genre=<?= $genre ?>&search=<?= urlencode($search) ?>&page=1" 
                           class="btn <?= $sort === 'title' ? 'btn-dark' : 'btn-outline-secondary' ?>">
                            Названию
                        </a>
                        <a role="button" tabindex="0" 
                           href="?sort=author&order=<?= $order ?>&status=<?= $status ?>&genre=<?= $genre ?>&search=<?= urlencode($search) ?>&page=1" 
                           class="btn <?= $sort === 'author' ? 'btn-dark' : 'btn-outline-secondary' ?>">
                            Автору
                        </a>
                        <a role="button" tabindex="0" 
                           href="?sort=chapter_count&order=<?= $order ?>&status=<?= $status ?>&genre=<?= $genre ?>&search=<?= urlencode($search) ?>&page=1" 
                           class="btn <?= $sort === 'chapter_count' ? 'btn-dark' : 'btn-outline-secondary' ?>">
                            Количеству глав
                        </a>
                    </div>
                    
                    <!-- Порядок сортировки -->
                    <span class="order-title">Порядок:</span>
                    <div role="group" class="btn-group btn-group-sm">
                        <a role="button" tabindex="0" 
                           href="?sort=<?= $sort ?>&order=DESC&status=<?= $status ?>&genre=<?= $genre ?>&search=<?= urlencode($search) ?>&page=1" 
                           class="btn <?= $order === 'DESC' ? 'btn-dark' : 'btn-outline-secondary' ?>">
                            По убыванию
                        </a>
                        <a role="button" tabindex="0" 
                           href="?sort=<?= $sort ?>&order=ASC&status=<?= $status ?>&genre=<?= $genre ?>&search=<?= urlencode($search) ?>&page=1" 
                           class="btn <?= $order === 'ASC' ? 'btn-dark' : 'btn-outline-secondary' ?>">
                            По возрастанию
                        </a>
                    </div>
                    
                    <!-- Фильтр по статусу -->
                    <span class="order-title">Статус:</span>
                    <div role="group" class="btn-group btn-group-sm">
                        <a role="button" tabindex="0" 
                           href="?sort=<?= $sort ?>&order=<?= $order ?>&status=all&genre=<?= $genre ?>&search=<?= urlencode($search) ?>&page=1" 
                           class="btn <?= $status === 'all' ? 'btn-dark' : 'btn-outline-secondary' ?>">
                            Все
                        </a>
                        <?php foreach ($statuses as $statusItem): ?>
                            <a role="button" tabindex="0" 
                               href="?sort=<?= $sort ?>&order=<?= $order ?>&status=<?= $statusItem['id'] ?>&genre=<?= $genre ?>&search=<?= urlencode($search) ?>&page=1" 
                               class="btn <?= $status == $statusItem['id'] ? 'btn-dark' : 'btn-outline-secondary' ?>">
                                <?= htmlspecialchars($statusItem['book status']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <hr>
                    
                    <!-- Фильтр по жанрам -->
                    <span class="order-title">Жанр:</span>
                    <div class="genre-filter d-none d-lg-flex">
                        <a class="genre-item <?= $genre === 'all' ? 'active' : '' ?>" 
                           href="?sort=<?= $sort ?>&order=<?= $order ?>&status=<?= $status ?>&genre=all&search=<?= urlencode($search) ?>&page=1">
                            Все
                        </a>
                        <?php foreach ($genres as $genreItem): ?>
                            <a class="genre-item <?= $genre == $genreItem['id'] ? 'active' : '' ?>" 
                               href="?sort=<?= $sort ?>&order=<?= $order ?>&status=<?= $status ?>&genre=<?= $genreItem['id'] ?>&search=<?= urlencode($search) ?>&page=1">
                                <?= htmlspecialchars($genreItem['genre']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Для мобильных устройств -->
                    <div class="d-flex">
                        <div class="d-flex d-lg-none dropdown">
                            <button type="button" aria-expanded="false" class="dropdown-toggle btn btn-outline-dark btn-sm">
                                <?= $genre === 'all' ? 'Все' : $genres[array_search($genre, array_column($genres, 'id'))]['genre'] ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Список книг -->
            <div class="books-list">
                <?php if (empty($books)): ?>
                    <div class="alert alert-info">Книги не найдены. Попробуйте изменить параметры поиска.</div>
                <?php else: ?>
                    <?php foreach ($books as $book): ?>
                        <div class="book-item">
                            <div class="book-cover">
                                <img src="<?= htmlspecialchars($book['cover_url']) ?>" alt="<?= htmlspecialchars($book['title']) ?>">
                            </div>
                            <div class="book-info">
                                <h2><?= htmlspecialchars($book['title']) ?></h2>
                                <p>Автор: <?= htmlspecialchars($book['author']) ?></p>
                                <p>Жанр: <?= htmlspecialchars($book['main_genre']) ?></p>
                                <p>Количество глав: <?= htmlspecialchars($book['chapter_count']) ?></p>
                                <p>Статус: <span class="book-status"><?= htmlspecialchars($book['book_status']) ?></span></p>
                                <p>Дата добавления: <?= htmlspecialchars($book['time']) ?></p>
                                <a href="book.php?id=<?= $book['id'] ?>" class="details-button">Подробнее</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Пагинация -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page-1 ?>&sort=<?= $sort ?>&order=<?= $order ?>&status=<?= $status ?>&genre=<?= $genre ?>&search=<?= urlencode($search) ?>">
                            &laquo;
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?= $i ?>&sort=<?= $sort ?>&order=<?= $order ?>&status=<?= $status ?>&genre=<?= $genre ?>&search=<?= urlencode($search) ?>" 
                           class="<?= $i == $page ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page+1 ?>&sort=<?= $sort ?>&order=<?= $order ?>&status=<?= $status ?>&genre=<?= $genre ?>&search=<?= urlencode($search) ?>">
                            &raquo;
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    document.getElementById('filterToggle').addEventListener('click', function() {
        const filtersContainer = document.getElementById('filtersContainer');
        filtersContainer.classList.toggle('visible');
        
        // Меняем иконку при открытии/закрытии
        const icon = this.querySelector('.icon');
        if (filtersContainer.classList.contains('visible')) {
            icon.innerHTML = '<path d="M18 15l-6-6-6 6"/>';
        } else {
            icon.innerHTML = '<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>';
        }
    });
</script>

<?php
require_once 'includes/footer.php';
?>
</body>
</html>