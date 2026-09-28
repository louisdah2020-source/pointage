<?php
// Configuration LWS MySQL
$host = 'localhost'; // Souvent localhost chez LWS
$dbname = 'media2630237';
$username = 'media2630237';
$password = 'Majoo@7001';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die(json_encode(['success' => false, 'message' => 'Erreur connexion BDD : ' . $e->getMessage()]));
}