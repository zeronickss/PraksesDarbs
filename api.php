<?php
session_start();
header('Content-Type: application/json');

$host = 'localhost';
$dbname = 'flappy_bird';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Exception $e) {
    die(json_encode(['error' => 'DB Connection Failed']));
}

$action = $_GET['action'] ?? '';
$body = json_decode(file_get_contents('php://input'), true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if ($action === 'register') {
        $username = trim($body['username'] ?? '');
        $password = $body['password'] ?? '';
        
        if (strlen($username) < 3 || strlen($password) < 3) {
            die(json_encode(['error' => 'Vārdam un parolei jābūt vismaz 3 simbolus gariem.']));
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            $stmt = $pdo->prepare('INSERT INTO players (username, password, high_score) VALUES (?, ?, 0)');
            $stmt->execute([$username, $hash]);
            
            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['username'] = $username;
            echo json_encode(['success' => true, 'username' => $username, 'best' => 0]);
        } catch (Exception $e) {
            echo json_encode(['error' => 'Šis lietotājvārds jau ir aizņemts!']);
        }
        exit;
    }

    if ($action === 'login') {
        $username = trim($body['username'] ?? '');
        $password = $body['password'] ?? '';

        $stmt = $pdo->prepare('SELECT id, username, password, high_score FROM players WHERE username = ?');
        $stmt->execute([$username]);
        $player = $stmt->fetch();

        if ($player && password_verify($password, $player['password'])) {
            $_SESSION['user_id'] = $player['id'];
            $_SESSION['username'] = $player['username'];
            echo json_encode(['success' => true, 'username' => $player['username'], 'best' => $player['high_score']]);
        } else {
            echo json_encode(['error' => 'Nepareizs lietotājvārds vai parole!']);
        }
        exit;
    }

    if ($action === 'save') {
        if (!isset($_SESSION['user_id'])) {
            die(json_encode(['error' => 'Nav autorizēts!']));
        }
        $userId = $_SESSION['user_id'];
        $score = (int)($body['score'] ?? 0);
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT high_score FROM players WHERE id = ?');
            $stmt->execute([$userId]);
            $player = $stmt->fetch();
            $best = $player['high_score'];
            if ($score > $best) {
                $pdo->prepare('UPDATE players SET high_score = ? WHERE id = ?')->execute([$score, $userId]);
                $best = $score;
            }
            $stmt = $pdo->prepare('INSERT INTO game_history (player_id, score) VALUES (?, ?)');
            $stmt->execute([$userId, $score]);
            $pdo->commit();
            echo json_encode(['success' => true, 'best' => $best]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['error' => 'Kļūda saglabājot']);
        }
        exit;
    }
}

if ($action === 'leaderboard') {
    $stmt = $pdo->query('SELECT username, high_score AS best_score FROM players ORDER BY high_score DESC LIMIT 10');
    echo json_encode(['leaderboard' => $stmt->fetchAll()]);
    exit;
}