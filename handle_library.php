<?php
session_start();
require_once 'includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $user_id = $_SESSION['user_id'];
    $book_id = $_POST['book_id'] ?? 0;
    
    if ($_POST['action'] === 'add_to_library') {
        // Проверяем, есть ли книга уже в библиотеке
        $sql = "SELECT * FROM user_library WHERE user_id = :user_id AND book_id = :book_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['user_id' => $user_id, 'book_id' => $book_id]);
        
        if (!$stmt->fetch()) {
            // Добавляем книгу в библиотеку
            $sql = "INSERT INTO user_library (user_id, book_id, last_visited) 
                    VALUES (:user_id, :book_id, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['user_id' => $user_id, 'book_id' => $book_id]);
        }
    }
    
    header("Location: book.php?id=" . $book_id);
    exit;
}