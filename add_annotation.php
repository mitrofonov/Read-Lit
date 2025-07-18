<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access.");
}


require_once 'includes/config.php';

$book_id = $_POST['book_id'];
$annotation = $_POST['annotation'];

// Проверка, является ли пользователь администратором
$user_id = $_SESSION['user_id'];
$sql = "SELECT status_id FROM user WHERE id = :user_id";
$stmt = $pdo->prepare($sql);
$stmt->execute(['user_id' => $user_id]);
$user = $stmt->fetch();

if ($user && $user['status_id'] == 1) { // 1 - статус admin
    // Проверка, существует ли уже аннотация для этой книги
    $sql = "SELECT id FROM annotation WHERE book_id = :book_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['book_id' => $book_id]);
    $existing_annotation = $stmt->fetch();

    if ($existing_annotation) {
        // Обновление существующей аннотации
        $sql = "UPDATE annotation SET annotation = :annotation WHERE book_id = :book_id";
    } else {
        // Вставка новой аннотации
        $sql = "INSERT INTO annotation (annotation, book_id) VALUES (:annotation, :book_id)";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['annotation' => $annotation, 'book_id' => $book_id]);

    header("Location: book.php?id=$book_id");
    exit();
} else {
    die("Access denied.");
}