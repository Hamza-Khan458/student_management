<?php
/**
 * Edit Student Page
 * This page allows the admin to update existing student records.
 */
include 'db_config.php';
include 'header.php';

// Page guard: only logged-in admins can edit student records.
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: login.php");
    exit();
}

// Form options: these mirror the registration form so records stay consistent.
$course_options = ['Computer Science', 'Software Engineering', 'Web Development', 'Data Science', 'Business'];
$status_options = ['Active', 'Inactive', 'Graduated'];

// Page state: holds messages, validation errors, and the current student row.
$message = "";
$errors = [];
$student = null;

// Validation helper: used before any update reaches the database.
function validate_student_input($name, $email, $course, $phone, $status, $course_options, $status_options) {
    $errors = [];

    if (strlen($name) < 3 || strlen($name) > 100) {
        $errors[] = "Full name must be between 3 and 100 characters.";
    }

    if (!preg_match("/^[a-zA-Z .'-]+$/", $name)) {
        $errors[] = "Full name can only contain letters, spaces, dots, apostrophes, and hyphens.";
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
        $errors[] = "Please enter a valid email address.";
    }

    if (!in_array($course, $course_options, true)) {
        $errors[] = "Please choose a valid course.";
    }

    if (!preg_match('/^[0-9+\-\s()]{10,20}$/', $phone)) {
        $errors[] = "Phone number must be 10 to 20 characters and may contain digits, spaces, +, -, or parentheses.";
    }

    if (!in_array($status, $status_options, true)) {
        $errors[] = "Please choose a valid student status.";
    }

    return $errors;
}

// Load the existing student record for the edit form.
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $sql = "SELECT * FROM students WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
}

// Update submission: validate, check duplicate email, then save the changes.
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = (int) ($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $course = trim($_POST['course'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $status = trim($_POST['status'] ?? 'Active');
    $student = compact('id', 'name', 'email', 'course', 'phone', 'status');

    if ($id <= 0) {
        $errors[] = "Invalid student record.";
    }

    $errors = array_merge($errors, validate_student_input($name, $email, $course, $phone, $status, $course_options, $status_options));

    // Duplicate check: allow the current record's email, but block another student's email.
    if (!$errors) {
        $check_sql = "SELECT id FROM students WHERE email = ? AND id != ? LIMIT 1";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("si", $email, $id);
        $check_stmt->execute();
        $existing = $check_stmt->get_result();

        if ($existing->num_rows > 0) {
            $errors[] = "Another student with this email already exists.";
        }
    }

    // Save update: updated_at is handled automatically by MySQL.
    if (!$errors) {
        $sql = "UPDATE students SET name = ?, email = ?, course = ?, phone = ?, status = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssi", $name, $email, $course, $phone, $status, $id);

        if ($stmt->execute()) {
            header("Location: view_students.php?msg=updated");
            exit();
        } else {
            $message = "Error updating record: " . $conn->error;
        }
    } else {
        $message = "Please fix the highlighted validation issues.";
    }
}

// Friendly fallback: show a clear message if the requested ID does not exist.
if (!$student) {
    echo "<div class='container'><p>Student not found.</p></div>";
    include 'footer.php';
    exit();
}
?>

<div class="container">
    <div class="page-header compact">
        <div>
            <span class="eyebrow">Update record</span>
            <h2>Edit Student Record</h2>
            <p>Keep student contact and course information current.</p>
        </div>
    </div>
    <?php if($message): ?>
        <p class="alert alert-error"><?php echo h($message); ?></p>
    <?php endif; ?>
    <?php if($errors): ?>
        <ul class="alert-list">
            <?php foreach($errors as $error): ?>
                <li><?php echo h($error); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <!-- Student form: update the fields that are safe for admins to maintain. -->
    <form action="edit_student.php" method="POST" class="form-card">
        <input type="hidden" name="id" value="<?php echo h($student['id']); ?>">
        
        <label for="name">Full Name:</label>
        <input type="text" name="name" id="name" value="<?php echo h($student['name']); ?>" minlength="3" maxlength="100" required>
        
        <label for="email">Email Address:</label>
        <input type="email" name="email" id="email" value="<?php echo h($student['email']); ?>" maxlength="100" required>
        
        <label for="course">Course:</label>
        <select name="course" id="course" required>
            <option value="">Select a course</option>
            <?php foreach ($course_options as $course_option): ?>
                <option value="<?php echo h($course_option); ?>" <?php echo $student['course'] === $course_option ? 'selected' : ''; ?>>
                    <?php echo h($course_option); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <label for="phone">Phone Number:</label>
        <input type="text" name="phone" id="phone" value="<?php echo h($student['phone']); ?>" minlength="10" maxlength="20" required>

        <label for="status">Student Status:</label>
        <select name="status" id="status" required>
            <?php foreach ($status_options as $status_option): ?>
                <option value="<?php echo h($status_option); ?>" <?php echo $student['status'] === $status_option ? 'selected' : ''; ?>>
                    <?php echo h($status_option); ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <button type="submit" class="btn btn-primary btn-full">Update Record</button>
    </form>
    <p class="form-link"><a href="view_students.php">Back to List</a></p>
</div>

<?php include 'footer.php'; ?>
