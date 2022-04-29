<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require __DIR__ . '/db.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [
        
    ];
}

$sql = "SELECT * FROM goods WHERE id = :id";
$stmt = $db->prepare($sql);
$stmt->execute(['id' => $_GET['id']]);
$goods = $stmt->fetch();

if (!$goods){
    exit("Unable to find goods!");
}

$_SESSION['cart'][] = $goods["id"];
header('Location: cart.php');
exit();
