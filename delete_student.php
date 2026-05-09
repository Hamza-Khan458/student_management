<?php
/**
 * Delete Student Page
 * This file only accepts POST requests so records are not deleted by opening a link.
 */
include 'db_config.php';

// Session setup: delete runs before the shared header, so we start the session here.
$session_path = __DIR__ . '/sessions';
if (!file_exists($session_path)) {
    mkdir($session_path, 0777, true);
}
session_save_path($session_path);
session_start();

// Page guard: only logged-in admins can delete records.
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

// Request guard: delete must come from the form in view_students.php.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: view_students.php");
    exit();
}

// CSRF guard: this confirms the delete form came from our app session.
$submitted_token = $_POST['csrf_token'] ?? '';
$session_token = $_SESSION['csrf_token'] ?? '';
if (!$session_token || !hash_equals($session_token, $submitted_token)) {
    header("Location: view_students.php?msg=invalid");
    exit();
}

// Delete record: prepared statements keep the student id safe.
$id = (int) ($_POST['id'] ?? 0);
if ($id > 0) {
    $sql = "DELETE FROM students WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        header("Location: view_students.php?msg=deleted");
        exit();
    }
}

// Fallback: return to the list if something was missing or invalid.
header("Location: view_students.php");
exit();
?>
