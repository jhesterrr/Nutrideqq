<?php
// debug_upload.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>Debug Upload Result</h2>";
    echo "<pre>";
    print_r($_FILES);
    print_r($_POST);
    echo "</pre>";

    if (isset($_FILES['attachment'])) {
        $uploadDir = 'uploads/';
        if (!file_exists($uploadDir))
            mkdir($uploadDir, 0777, true);

        $dest = $uploadDir . basename($_FILES['attachment']['name']);
        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $dest)) {
            echo "<p style='color:green'>File moved successfully to $dest</p>";
        } else {
            echo "<p style='color:red'>Failed to move file. Check permissions.</p>";
        }
    }
}
?>
<form method="POST" enctype="multipart/form-data">
    <input type="file" name="attachment">
    <button type="submit">Test Upload</button>
</form>