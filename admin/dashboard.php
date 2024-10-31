<?php

	include_once 'connect.php'; // Include your database connection script
	$conn = connect(); // Assuming you have a database connection established already
	session_start();


	if (!isset($_SESSION['user_id'])) {
		// Redirect to login page if not logged in
		header("Location: login.php");
		exit();
	}	
	
	if ($conn) {
		// Query to count the total number of cars owned by the logged-in owner
		$sql = "SELECT COUNT(*) AS total FROM `vehicles`;";
		$sql2 = "SELECT COUNT(*) AS total FROM `booking`;";
		$sql3 = "SELECT COUNT(*) AS total FROM `book_cancel`;";
		$sql4 = "SELECT COUNT(*) AS total FROM `booking` WHERE status = 1;";
		$sql5 = "SELECT COUNT(DISTINCT car_name) AS total FROM `booking`;";
		$result = $conn->query($sql);
		$result2 = $conn->query($sql2);
		$result3 = $conn->query($sql3);
		$result4 = $conn->query($sql4);
		$result5 = mysqli_query($conn, $sql5);

		// Check if the query was successful
		if ($result && $result->num_rows > 0) {
			$row = $result->fetch_assoc();
			$totalRows = $row['total'];
		} else {
			echo "Error: Query failed.";
		}
		if ($result2 && $result2->num_rows > 0) {
			$row = $result2->fetch_assoc();
			$totalRows2 = $row['total'];
		} else {
			echo "Error: Query failed.";
		}
		if ($result3 && $result3->num_rows > 0) {
			$row = $result3->fetch_assoc();
			$totalRows3 = $row['total'];
		} else {
			echo "Error: Query failed.";
		}
		if ($result4 && $result4->num_rows > 0) {
			$row = $result4->fetch_assoc();
			$totalRows4 = $row['total'];
		} else {
			echo "Error: Query failed.";
		}
		if ($result5 && $result5->num_rows > 0) {
			$row = $result5->fetch_assoc();
			$totalRows5 = $row['total']; // This will be the total unique rented cars
		} else {
			echo "Error: Query failed.";
		}
	} else {
		echo "Database connection failed.";
	}

	// Handle AJAX Requests
if (isset($_GET['action'])) {
    $action = $_GET['action'];

    if ($action == 'check_new_bookings') {
        // Count new (unviewed) bookings
        $sql = "SELECT COUNT(*) AS new_bookings FROM booking WHERE viewed = 0";
        $result = $conn->query($sql);
        $newBookings = 0;
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $newBookings = $row['new_bookings'];
        }
        echo json_encode(['new_bookings' => $newBookings]);
        exit;
    }

    if ($action == 'fetch_notifications') {
        // Fetch the latest unread bookings
		$sql = "SELECT book_id, name, from_date, created_at FROM booking WHERE viewed = 0 ORDER BY created_at DESC LIMIT 10";
		$result = $conn->query($sql);

		$notifications = [];

		if ($result && $result->num_rows > 0) {
			while ($row = $result->fetch_assoc()) {
				$createdAt = strtotime($row['created_at']);
				$timeDiff = time() - $createdAt;
		
				// Calculate the time difference in minutes
				$minutesAgo = floor($timeDiff / 60);
		
				// Prepare the notification data
				$notifications[] = [
					'book_id' => $row['book_id'],
					'name' => htmlspecialchars($row['name']),
					'date' => ($minutesAgo > 0) ? $minutesAgo . ' minutes ago' : 'just now'
				];
			}
		}
		

		// Return notifications as JSON
		echo json_encode(['notifications' => $notifications]);
        exit;
    }

    if ($action == 'mark_as_read') {
        // Mark all unread bookings as viewed
        $sql = "UPDATE booking SET viewed = 1 WHERE viewed = 0";
        if ($conn->query($sql) === TRUE) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => $conn->error]);
        }
        exit;
    }

    // If action not recognized
    echo json_encode(['status' => 'invalid_action']);
    exit;
}

// Fetch Dashboard Data
// Total Cars
$sql = "SELECT COUNT(*) AS total FROM `vehicles`;";
$result = $conn->query($sql);
$totalCars = 0;
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $totalCars = $row['total'];
}

// Total Bookings
$sql2 = "SELECT COUNT(*) AS total FROM `booking`;";
$result2 = $conn->query($sql2);
$totalBookings = 0;
if ($result2 && $result2->num_rows > 0) {
    $row2 = $result2->fetch_assoc();
    $totalBookings = $row2['total'];
}

// Count New Bookings
$sql = "SELECT COUNT(*) as new_bookings FROM booking WHERE viewed = 0"; 
$result = $conn->query($sql);
$newBookings = 0;
if ($result && $result->num_rows > 0) {
    $data = $result->fetch_assoc();
    $newBookings = $data['new_bookings'];
}

	
$query = "SELECT SUM(price) AS total_sales FROM payment"; // Adjust the query based on your actual table and column names
$result = mysqli_query($conn, $query); // $connection is your database connection variable

// Fetch the result
$row = mysqli_fetch_assoc($result);
$totalSales = $row['total_sales'] ? $row['total_sales'] : 0;
?>


<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">

	<!-- Boxicons -->
	<link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
	<!-- My CSS -->
	<link rel="stylesheet" href="style.css">
	<link rel="icon" type="image/x-icon" href="../image/logo.jpg">
	<title>AdminHub</title>
	<style>
		/* Notification Dropdown Styles */
		.notification-dropdown {
			display: none;
			position: absolute;
			top: 50px; /* Adjust based on your navbar height */
			right: 20px; /* Adjust based on your navbar padding */
			background-color: white;
			min-width: 300px;
			box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
			border: 1px solid #ccc;
			z-index: 1000;
			max-height: 400px;
			overflow-y: auto;
			border-radius: 4px;
		}

		.notification-dropdown ul {
			list-style-type: none;
			padding: 0;
			margin: 0;
		}

		.notification-dropdown ul li {
			padding: 10px 15px;
			border-bottom: 1px solid #ddd;
		}

		.notification-dropdown ul li:last-child {
			border-bottom: none;
		}

		.notification-dropdown ul li:hover {
			background-color: #f1f1f1;
		}

		.num {
			background-color: red;
			color: white;
			padding: 2px 6px;
			border-radius: 50%;
			position: absolute;
			top: -5px;
			right: -5px;
			font-size: 12px;
		}
		.table-container {
            max-height: 400px; /* Set the maximum height for the table */
            overflow-y: auto; /* Enable vertical scrolling */
            overflow-x: hidden; /* Hide horizontal overflow */
            border: 1px solid #ccc; /* Optional: Add border to the container */
            border-radius: 5px; /* Optional: Rounded corners */
        }

        table {
            width: 100%; /* Set table to full width */
            border-collapse: collapse; /* Combine borders for a cleaner look */
        }

        th, td {
            padding: 10px; /* Add some padding */
            text-align: left; /* Align text to the left */
            border-bottom: 1px solid #ddd; /* Add a bottom border to table cells */
        }

        th {
            background-color: #f2f2f2; /* Light gray background for table headers */
        }

	</style>
</head>
<body>


	<!-- SIDEBAR -->
	<section id="sidebar">
		<a href="#" class="brand">
			<i class='bx bxs-smile'></i>
			<span class="text">Admin</span>
		</a>
		<ul class="side-menu top">
			<li class="active">
				<a href="#">
					<i class='bx bxs-dashboard' ></i>
					<span class="text">Dashboard</span>
				</a>
			</li>
			<li>
				<a href="#" class="dropdown-toggle">
					<i class='bx bxs-car'></i>
					<span class="text">Cars</span>
					<i class='bx bx-chevron-down'></i>
				</a>
				<ul class="dropdown-menu">
					<li><a href="postcar.php">Post Cars</a></li>
					<li><a href="managecar.php">Manage cars</a></li>
				</ul>
			</li>

			<li>
				<a href="calendar.php">
					<i class='bx bxs-calendar' ></i>
					<span class="text">Available Cars</span>
				</a>
			</li>
			
			<li>
				<a href="managebook.php">
					<i class='bx bxs-book' ></i>
					<span class="text">Manage Bookings</span>
				</a>
			</li>
			<li>
				<a href="service.php">
					<i class='bx bxs-report' ></i>
					<span class="text">Extra Service</span>
				</a>
			</li>
			<li >
				<a href="managereview.php">
					<i class='bx bxs-message' ></i>
					<span class="text">Feedback</span>
				</a>
			</li>
			<li >
				<a href="accepted.php">
					<i class='bx bxs-book' ></i>
					<span class="text">Booking Accepted</span>
				</a>
			</li>

			<li >
				<a href="cancel.php">
					<i class='bx bxs-book' ></i>
					<span class="text">Booking Canceled</span>
				</a>
			</li>

			<li>
				<a href="user.php">
					<i class='bx bxs-report' ></i>
					<span class="text">Reports</span>
				</a>
			</li>
			
		</ul>
		<ul class="side-menu">
			<li>
				<a href="logout.php" class="logout">
					<i class='bx bxs-log-out-circle'></i>
					<span class="text">Logout</span>
				</a>
			</li>
		</ul>
	</section>
	<!-- SIDEBAR -->



	<!-- CONTENT -->
	<section id="content">
		<!-- NAVBAR -->
		<nav>
			<i class='bx bx-menu' ></i>
			<a href="#" class="nav-link">Categories</a>
			<div class="notification-wrapper">
                <a href="#" class="notification" id="notificationBell">
                    <i class='bx bxs-bell'></i>
                    <?php if ($newBookings > 0) { ?>
                        <span class="num" id="notification-count"><?php echo $newBookings; ?></span>
                    <?php } else { ?>
                        <span class="num" id="notification-count" style="display: none;">0</span>
                    <?php } ?>
                </a>
                <!-- Notification Dropdown -->
                <div class="notification-dropdown" id="notificationDropdown">
                    <ul id="notificationList">
					<?php
						// Fetch latest unread bookings to display initially
						if ($newBookings > 0) {
							$sql = "SELECT book_id, name, created_at FROM booking WHERE viewed = 0 ORDER BY created_at DESC LIMIT 10";
							$result = $conn->query($sql);
							
							if ($result && $result->num_rows > 0) {
								while ($row = $result->fetch_assoc()) {
									// Create the link to managebook.php without using book_id
									echo "<li>
											<a href='managebook.php'>" . htmlspecialchars($row['name']) . " has made a booking just now</a>
										</li>";
								}
							} else {
								echo "<li>No new bookings.</li>";
							}
						} else {
							echo "<li>No new bookings.</li>";
						}
					?>
                    </ul>
                </div>
            </div>
			<a href="#" class="profile">
				<img src="../login/image/user.png">
			</a>
		</nav>
		<!-- NAVBAR -->

		<!-- MAIN -->
		<main>
			<div class="head-title">
				<div class="left">
					<h1>Dashboard</h1>
					<ul class="breadcrumb">
						<li>
							<a href="#">Dashboard</a>
						</li>
						<li><i class='bx bx-chevron-right' ></i></li>
						<li>
							<a class="active" href="#">Home</a>
						</li>
					</ul>
				</div>
				
			</div>

			<ul class="box-info">
			<li>
				<i class='bx bxs-dollar-circle'></i>
				<span class="text">
					<h3><?php echo '₱' . number_format($totalSales, 2); ?></h3> <!-- Format the total sales -->
					<p>Total Sales Monthly</p>
				</span>
			</li>		
				<li>
					<i class='bx bxs-car' ></i>
					<span class="text">
						<h3><?php echo $totalRows;?></h3>
						<p>Listed Cars</p>
					</span>
				</li>
				<li>
					<i class='bx bxs-car' ></i>
					<span class="text">
						<h3><?php echo $totalRows5;?></h3>
						<p>Rented Cars</p>
					</span>
				</li>
				<li>
					<i class='bx bxs-calendar-check' ></i>
					<span class="text">
						<h3><?php echo $totalRows2;?></h3>
						<p>Total Bookings</p>
					</span>
				</li>
				
				<li>
					<i class='bx bxs-group' ></i>
					<span class="text">
						<h3><?php echo $totalRows3;?></h3>
						<p>Cancel Booking</p>
					</span>
				</li>
				<li>
					<i class='bx bxs-group' ></i>
					<span class="text">
						<h3><?php echo $totalRows4;?></h3>
						<p>Accepted Booking</p>
					</span>
				</li>
			</ul>


			<div class="table-data">
				<div class="order">
					<div class="head">
						<h3>Recent Renters</h3>
						<i class='bx bx-filter' ></i>
					</div>
					<div class="table-container">
						<table>
							<thead>
								<tr>
									<th>Renter</th>
									<th>Date Order</th>
									<th>Status</th>
								</tr>
							</thead>
							<tbody>
							<?php
								include_once 'connect.php';
								$con = connect();

								// Check if database connection is successful
								if ($con) {
									$sql = "SELECT * FROM `booking` WHERE `from_date` ORDER BY `book_id` DESC;";
                					$result = $con->query($sql);

									// Check if there are any rows returned from the database
									if ($result && $result->num_rows > 0) {
										foreach ($result as $r) {
											?>
											<tr class="gradeX">
												<td><?php echo $r['name']; ?></td>
												<td><?php echo $r['from_date']; ?></td>
												<td><?php echo $r['status'] ? 'Accepted' : 'Pending'; ?></td>
											</tr>
											<?php
										}
									} else {
										echo "<tr><td colspan='3'>No Renters Book!</td></tr>";
									}
								} else {
									echo "<tr><td colspan='3'>Database connection failed.</td></tr>";
								}
							?>
							</tbody>
						</table>
					</div>
				</div>
				
			</div>
		</main>
		<!-- MAIN -->
	</section>
	<!-- CONTENT -->
	

	<script src="script.js"></script>
	<script>
        document.addEventListener('DOMContentLoaded', function() {
			const notificationBell = document.getElementById('notificationBell');
			const notificationDropdown = document.getElementById('notificationDropdown');
			const notificationList = document.getElementById('notificationList');
			const notificationCount = document.getElementById('notification-count');

			// Function to check for new bookings
			function checkNewBookings() {
				fetch('?action=check_new_bookings')
					.then(response => response.json())
					.then(data => {
						console.log('New bookings response:', data); // Debugging line
						const count = data.new_bookings;
						if (count > 0) {
							notificationCount.textContent = count;
							notificationCount.style.display = 'inline';
						} else {
							notificationCount.style.display = 'none';
						}
					})
					.catch(error => console.error('Error:', error));
			}

			// Function to fetch notifications
			function fetchNotifications() {
				fetch('?action=fetch_notifications')
					.then(response => response.json())
					.then(data => {
						console.log('Notifications fetched:', data); // Debugging line
						// Clear existing notifications
						notificationList.innerHTML = '';

						if (data.notifications.length > 0) {
							data.notifications.forEach(notification => {
								const li = document.createElement('li');
								li.innerHTML = `${notification.message} <br><small>${notification.date}</small>`;
								notificationList.appendChild(li);
							});
						} else {
							const li = document.createElement('li');
							li.textContent = 'No new bookings.';
							notificationList.appendChild(li);
						}
					})
					.catch(error => console.error('Error:', error));
			}

			// Function to mark notifications as read
			function markAsRead() {
				fetch('?action=mark_as_read', {
					method: 'POST',
				})
				.then(response => response.json())
				.then(data => {
					console.log('Mark as read response:', data); // Debugging line
					if (data.status === 'success') {
						notificationCount.style.display = 'none';
					}
				})
				.catch(error => console.error('Error:', error));
			}

			// Toggle notification dropdown
			notificationBell.addEventListener('click', function(e) {
				e.preventDefault();
				if (notificationDropdown.style.display === 'none' || notificationDropdown.style.display === '') {
					fetchNotifications();
					notificationDropdown.style.display = 'block';
					markAsRead();
					checkNewBookings(); // Update the count after marking as read
				} else {
					notificationDropdown.style.display = 'none';
				}
			});

			// Periodically check for new bookings every 10 seconds
			setInterval(checkNewBookings, 10000);

			// Initial check
			checkNewBookings();
		});


		document.addEventListener('click', function(event) {
			// Check if the clicked element is a link inside the notification dropdown
			if (event.target.closest('.notification-dropdown a')) {
				// Allow the default action (navigation)
				return;
			}
			
			// Existing code to hide the dropdown
			if (!event.target.closest('.notification-wrapper')) {
				document.getElementById('notificationDropdown').style.display = 'none';
			}
		});


    </script>

</body>
</html>