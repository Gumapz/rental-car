<?php
include_once 'connect.php';
$conn = connect();
session_start();

// Handle the return car
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['return']) && !empty($_POST['return'])) {
        $carId = $conn->real_escape_string($_POST['return']);
        
            $updateDate = date('Y-m-d');
            $returnDate = "Returned"; 

            $sql = "UPDATE booking SET  returned = ?, date = ? WHERE book_id = ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param("ssi", $returnDate, $updateDate, $carId);
                if ($stmt->execute()) {
                    $_SESSION['alertMessage'] = "The Car is return!";
                    $_SESSION['alertType'] = "success";
                    header("Location: accepted.php");
                    exit;
                } else {
                    $_SESSION['alertMessage'] = "Code error return car.";
                    $_SESSION['alertType'] = "error";
                }
            } else {
                $_SESSION['alertMessage'] = "Failed to prepare SQL statement.";
                $_SESSION['alertType'] = "error";
            }
    }else {
        $_SESSION['alertMessage'] = "car ID not found.";
        $_SESSION['alertType'] = "error";
    }
}else {
    $_SESSION['alertMessage'] = "Failed to prepare SQL statement.";
    $_SESSION['alertType'] = "error";
}
?>