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





// Initialize an array to hold the earnings for each month
$monthlyEarnings = array_fill(0, 12, 0); // Array for 12 months
$totalEarnings = 0; // Variable to hold total earnings
$monthToMonthChanges = array_fill(0, 11, 0); // Array for month-to-month changes

// Optionally, set a specific year to filter results
$selectedYear = date('Y'); // Default to current year, or set as needed

// Fetch earnings grouped by month
$sql = "SELECT MONTH(date) AS month, SUM(price) AS total_earnings 
        FROM payment 
        WHERE YEAR(date) = ? 
        GROUP BY MONTH(date)";
$stmt = $conn->prepare($sql);

// Check if statement preparation was successful
if ($stmt === false) {
    die("SQL statement preparation failed: " . $conn->error);
}

$stmt->bind_param("i", $selectedYear); // Bind the year parameter
$stmt->execute();
$result = $stmt->get_result();

// Populate the monthly earnings array and calculate total earnings
while ($row = $result->fetch_assoc()) {
    $monthIndex = $row['month'] - 1; // month is 1-indexed
    $monthlyEarnings[$monthIndex] = (int)$row['total_earnings']; // Store monthly earnings
    $totalEarnings += $monthlyEarnings[$monthIndex]; // Accumulate total earnings
}

// Calculate month-to-month changes
for ($i = 1; $i < 12; $i++) {
    $monthToMonthChanges[$i - 1] = $monthlyEarnings[$i] - $monthlyEarnings[$i - 1]; // Difference from previous month
}

// Close the connection
$stmt->close();

// Output the earnings data as JSON
$earningsData = json_encode($monthlyEarnings);
$monthToMonthData = json_encode($monthToMonthChanges);


$sql = "SELECT DISTINCT YEAR(date) AS year FROM payment ORDER BY year DESC";
$result = $conn->query($sql);

// Initialize an array to hold the years
$years = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $years[] = $row['year']; // Add each year to the array
    }
}

// Array to hold total renters for each month (Jan to Dec)
$renterData = [];

for ($i = 1; $i <= 12; $i++) {
    // Query to get the total renters for each month, based on `from_date`
    $query = "SELECT COUNT(*) AS total FROM booking WHERE MONTH(from_date) = $i";
    $result = $conn->query($query);

    // Fetch result
    $row = $result->fetch_assoc();
    // Append result to renterData, defaulting to 0 if no rows are found
    $renterData[] = (int)$row['total'] ?? 0;
}

// Convert PHP array to JSON for use in JavaScript
$rentersData = json_encode($renterData);

$sql = "SELECT DISTINCT YEAR(from_date) AS year FROM booking ORDER BY year DESC";
$result = $conn->query($sql);

// Initialize an array to hold the years
$years = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $years[] = $row['year']; // Add each year to the array
    }
}




// Initialize $top_cars as an empty array
$top_cars = [];

// Query to get top rented cars based on car_name and include the car image
$sql = "SELECT image, car_name, COUNT(*) as rental_count
        FROM booking
        GROUP BY car_name, image
        ORDER BY rental_count DESC
        LIMIT 2"; // Modify LIMIT if you want more cars

$result = $conn->query($sql);

// Check if the query returns rows
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $top_cars[] = $row; // Store car data including the image in the $top_cars array
    }
}

// Get the total number of rentals for percentage calculation
$total_sql = "SELECT COUNT(*) as total_rentals FROM booking";
$total_result = $conn->query($total_sql);
$total_rentals = $total_result->num_rows > 0 ? $total_result->fetch_assoc()['total_rentals'] : 0;





/// Query to count each status
$queryAccepted = "SELECT COUNT(*) FROM booking WHERE status = 1"; // Count of accepted bookings
$queryPending = "SELECT COUNT(*) FROM booking WHERE status = 0";   // Count of pending bookings
$queryCancel = "SELECT COUNT(*) FROM booking WHERE cancel = 1"; // Count of canceled bookings

$resultAccepted = $conn->query($queryAccepted);
$resultPending = $conn->query($queryPending);
$resultCancel = $conn->query($queryCancel);

$countAccepted = $resultAccepted->fetch_row()[0];
$countPending = $resultPending->fetch_row()[0];
$countCancel = $resultCancel->fetch_row()[0];

// Pass these counts to JavaScript
echo "<script>
    const counts = {
        accepted: $countAccepted,
        pending: $countPending,
        cancel: $countCancel
    };
</script>";
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">

	<!-- Boxicons -->
	<link href='https://unpkg.com/boxicons@2.0.9/css/boxicons.min.css' rel='stylesheet'>
	<!-- My CSS -->
	<link rel="stylesheet" href="style.css">
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
	<link rel="icon" type="image/x-icon" href="../image/logo.jpg">
	<title>AdminHub</title>

	<style>
		/* Main layout with two rows */
		.report-grid {
			display: grid;
			grid-template-columns: 2fr 0.8fr; /* Left row larger (3 parts) than right row (1 part) */
			gap: 20px; /* Space between columns */
			margin-top: 20px;
		}

		/* Box styles */
		.report-box {
			background-color: #fff;
			border-radius: 8px;
			box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
			padding: 20px;
			display: flex;
			align-items: center;
			gap: 20px; /* Space between icon and text */
		}

		.report-box h3 {
			font-size: 24px;
			font-weight: 600;
			color: #342E37;
		}

		.report-box p {
			color: #342E37;
		}

		.report-box .value {
			font-size: 24px;
			font-weight: 600;
			color: #342E37;
		}

		/* Grid layout for the boxes */
		.report-left {
			display: grid;
			grid-template-columns: 1fr 1fr; /* Two columns */
			gap: 20px; /* Space between boxes */
		}

		/* Example icons in boxes */
		.report-box i {
			width: 60px;
			height: 60px;
			border-radius: 10px;
			font-size: 36px;
			display: flex;
			justify-content: center;
			align-items: center;
		}

		/* Custom colors for each box icon */
		.report-box:nth-child(1) i {
			background: #CFE8FF;
			color: #3C91E6;
		}

		.report-box:nth-child(2) i {
			background: var(--light-yellow);
			color: var(--yellow);
		}

		.report-box:nth-child(3) i {
			background: var(--light-orange);
			color: var(--orange);
		}

		.report-box:nth-child(4) i {
			background: var(--light-blue);
			color: var(--blue);
		}

		/* Full-width box for Earnings Summary */
		.full-width {
			grid-column: span 2; /* Make the box span both columns */
			background-color: #fff;
			border-radius: 8px;
			padding: 20px;
			box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
			overflow: auto; /* Adds a scrollbar when content overflows */
			max-width: 100%; /* Ensures the box does not exceed its container width */
			box-sizing: border-box; /* Include padding and border in the element's total width and height */
			display: flex; /* Enable flexbox */
			flex-direction: column; /* Stack children vertically */
			align-items: center; /* Center items horizontally */
			text-align: center; /* Center text inside elements */
		}

		/* Style for the Earnings Summary title */
		.full-width h3 {
			font-size: 18px;
			color: #333;
			margin-bottom: 20px; /* Space below the title */
		}
		

		#yearSelect {
			padding: 8px 12px;
			border: 1px solid #ddd;
			border-radius: 4px;
			font-size: 16px;
			color: #342E37; /* Matches the text color */
		}
		/* Make sure the container doesn't force full width */
		.report-right {
			display: flex;        /* Use flexbox to control alignment */
			flex-direction: column; /* Stack elements vertically */
			gap: 20px; /* Space between boxes */
		}

		.report-right .report-box {
			width: 300px;         /* Set a fixed width for the Rent Status box */
			background-color: #fff;
			border-radius: 8px;
			padding: 20px;
			box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
			margin: 0;            /* Remove any extra margin */
			
		}

		

		/* Set the canvas height */
		#pieChart {
			width: 100%;   /* Ensure it scales with the container */
			height: 180px; /* Set the desired height for the pie chart */
		}

		/* Optional: ensure the canvas stays inside its container */
		.scroll-container {
			display: flex;
			justify-content: center;
			align-items: center; /* Center the chart vertically */
			margin-top: 40px;
			display: flex;
			justify-content: center; /* Center the chart horizontally */
			width: 100%; 
		}


		/* Legend styling */
		.legend-container {
			display: flex;
			flex-direction: column;  /* Arrange the legend items vertically */
			align-items: left;     /* Center them under the pie chart */
			margin-top: 20px;        /* Space between the chart and the legend */
			
		}

		.legend-item {
			display: flex;
			align-items: center;
			margin-bottom: 10px;     /* Space between each item */
		}

		.legend-color {
			width: 20px;
			height: 20px;
			margin-right: 10px;
			border-radius: 50%;
		}

		/* Legend colors matching the pie chart */
		.legend-color.rented {
			background-color: #342E37;  /* Color for Rented */
		}

		.legend-color.available {
			background-color: #DB504A;  /* Color for Available */
		}

		.legend-color.maintenance {
			background-color: #eee;  /* Color for Maintenance */
		}

		.box-content {
			display: flex;
			flex-direction: column; /* Stack content vertically */
			position: relative; /* Maintain positioning for inner elements */
			width: 100%; 
		}
		.report-box {
			width: 100%; /* Ensure the box uses full width */
		}
		.car-status-box {
			border: 1px solid #ddd; /* Rectangle border */
			border-radius: 10px; /* Rounded corners */
			padding: 15px; /* Space inside the box */
			background-color: #f9f9f9; /* Background color */
			display: flex; /* Use flexbox for layout */
			align-items: flex-start; /* Align items to the top */
			margin-bottom: 20px; /* Space below the box */
			width: 260px; /* Ensure it stays within the container */
			
			box-sizing: border-box; /* Include padding and border in the element's total width */
		}

		.car-status-content {
			display: flex; /* Flexbox for inner content */
			width: 100%; /* Use full width of parent */
		}

		.car-image {
			width: auto; /* Image width */
			height: 50px; /* Maintain aspect ratio */
			margin-right: 15px; /* Space between image and text */
		}

		.car-details {
			display: flex;
			flex-direction: column; /* Stack details vertically */
			justify-content: flex-start; /* Align items to the top */
			flex-grow: 1; /* Allow to take available space */
		}

		.car-info {
			display: flex; /* Use flexbox for name and percentage */
			justify-content: space-between; /* Space between elements */
			align-items: flex-start; /* Align items to the top */
			margin-bottom: 5px; /* Space below the car info */
		}

		.car-name {
			font-size: 12px; /* Increase the font size for better readability */
		}

		.loading-bar-container {
			background-color: #e0e0e0; /* Background for loading bar */
			border-radius: 4px; /* Rounded corners */
			height: 10px; /* Height of the loading bar */
			overflow: hidden; /* Hide overflow */
			margin-top: 5px; /* Space above the loading bar */
		}

		.loading-bar {
			background-color: #342E37; /* Loading bar color */
			height: 100%; /* Fill the height of the container */
			border-radius: 4px; /* Rounded corners */
		}

		.percentage {
			font-weight: bold; /* Bold text for percentage */
			font-size: 12px; /* Increase the font size */
		}

		.legend-container {
			margin-top: 20px; /* Space between chart and legend */
			display: flex; /* Use flexbox for layout */
			flex-direction: column; /* Stack items vertically */
		}

		.legend-item {
			display: flex;
			align-items: center; /* Center the items vertically */
			margin-bottom: 5px; /* Space between legend items */
		}

		.legend-color {
			width: 20px; /* Width of the color box */
			height: 20px; /* Height of the color box */
			border-radius: 3px; /* Optional: Rounded corners */
			margin-right: 10px; /* Space between color box and text */
		}

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
			<li>
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

			<li class="active">
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
					<h1>Report</h1>
					
				</div>
				
			</div>

			<!-- Two-row layout -->
			<div class="report-grid">
				<!-- Left side with four boxes -->
				<div class="report-left">
					<!-- First column -->
					<div class="report-box">
						<i class='bx bx-money'></i>
						<div class="box-content">
							<p>Income</p>
							<span class="value"><?php echo '₱' . number_format($totalSales, 2); ?></span>
						</div>
					</div>

					<div class="report-box">
						<i class='bx bx-book-bookmark'></i>
						<div class="box-content">
							<p>New Booking</p>
							<span class="value"><?php echo $totalRows2;?></span>
						</div>
					</div>

					<!-- Second column -->
					<div class="report-box">
						<i class='bx bx-car'></i>
						<div class="box-content">
							<p>Rented Cars</p>
							<span class="value"><?php echo $totalRows5;?></span>
						</div>
					</div>

					<div class="report-box">
						<i class='bx bx-car'></i>
						<div class="box-content">
							<p>Available Cars</p>
							<span class="value"><?php echo $totalRows;?></span>
						</div>
					</div>

					<div class="report-box full-width">
						<div class="box-content" style="position: relative;">
							<h3 style="margin-bottom: 20px;">Earnings Summary</h3>
							<select id="yearSelect" name="selectedYear" style="position: absolute; top: 0; right: 20px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
								<?php
								// Generate options for each year
								foreach ($years as $year) {
									echo "<option value='$year' " . ($year == $selectedYear ? 'selected' : '') . ">$year</option>";
								}
								?>
							</select>
							<div class="scroll-container" style="margin-top: 40px;">
								<canvas id="areaChart" width="650" height="300"></canvas> <!-- Area Chart -->
							</div>
							
							<div style="max-height: 300px; overflow-y: auto; margin-top: 40px; border: 1px solid #ddd; border-radius: 4px;">
								<table style="width: 100%; border-collapse: collapse;">
									<thead>
										<tr>
											<th style="border: 1px solid #ddd; padding: 8px;">Month</th>
											<th style="border: 1px solid #ddd; padding: 8px;">Total Earnings</th>
										</tr>
									</thead>
									<tbody>
										<?php
										// Month names for display
										$monthNames = [
											'January', 'February', 'March', 'April', 'May', 
											'June', 'July', 'August', 'September', 'October', 
											'November', 'December'
										];
										
										// Populate the table rows based on monthly earnings
										foreach ($monthlyEarnings as $index => $earning) {
											if ($earning > 0) { // Only display months with earnings
												echo "<tr>
													<td style='border: 1px solid #ddd; padding: 8px; text-align: center;'>{$monthNames[$index]}</td>
													<td style='border: 1px solid #ddd; padding: 8px; text-align: center;'>₱" . number_format($earning, 2) . "</td>
												</tr>";
											}
										}
										?>
									</tbody>
								</table>
								
							</div>
							<br>
								<p style="text-align: right; font-size: 20px;"><strong>Monthly Total: </strong><?php echo '₱' . number_format($totalSales, 2); ?></p>
						</div>
					</div>
					<div class="report-box full-width">
						<div class="box-content" style="position: relative;">
							<h3 style="margin-bottom: 20px;">Bookings Overview</h3>
							<select id="yearSelect" name="selectedYear" style="position: absolute; top: 0; right: 20px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
								<?php
								// Generate options for each year
								foreach ($years as $year) {
									echo "<option value='$year' " . ($year == $selectedYear ? 'selected' : '') . ">$year</option>";
								}
								?>
							</select>
							<div class="scroll-container" style="margin-top: 20px;">
								<canvas id="barChart" width="650" height="300"></canvas> <!-- Bar Chart -->
							</div>
						</div>
					</div>
					
					
					
				</div>

				<div class="report-right">
					<div class="report-box">
						<div class="box-content" style="position: relative;">
							<h4 style="margin-bottom: 20px; text-align: center; font-size: 20px">Rent Status</h4>
							<div class="scroll-container" style="margin-top: 20px;">
								<canvas id="pieChart" width="500" height="300"></canvas> <!-- Pie Chart -->
							</div>
							<script>
								const legendContainer = document.createElement('div');
									legendContainer.classList.add('legend-container');
									legendContainer.innerHTML = `
										<div class="legend-item">
											<span class="legend-color" style="background-color: green;"></span> Accepted: ${counts.accepted}
										</div>
										<div class="legend-item">
											<span class="legend-color" style="background-color: orange;"></span> Pending: ${counts.pending}
										</div>
										<div class="legend-item">
											<span class="legend-color" style="background-color: red;"></span> Cancel: ${counts.cancel}
										</div>
									`;
									document.querySelector('.report-right').appendChild(legendContainer);
							</script>
						</div>
					</div>

					<!-- New Top Rented Car Box -->
					<div class="report-box">
						<div class="box-content">
							<h4 style="margin-bottom: 20px;">Top Rented Cars</h4>
							<?php if (!empty($top_cars)): ?>
								<?php foreach ($top_cars as $index => $car): ?>
								<div class="car-status-box">
									<div class="car-status-content">
										<!-- Use the car_image column from the booking table -->
										<img src="uploads/<?php echo $car['image']; ?>" alt="Car Image" class="car-image">
										<div class="car-details">
											<div class="car-info">
												<h4 class="car-name"><?php echo $car['car_name']; ?></h4>
												<p class="percentage">
													<?php
													if ($total_rentals > 0) {
														echo round(($car['rental_count'] / $total_rentals) * 100);
													} else {
														echo 0;
													}
													?>%
												</p>
											</div>
											<div class="loading-bar-container">
												<div class="loading-bar" style="width: 
													<?php
													if ($total_rentals > 0) {
														echo round(($car['rental_count'] / $total_rentals) * 100);
													} else {
														echo 0;
													}
													?>%;"></div>
											</div>
										</div>
									</div>
								</div>
								<?php endforeach; ?>
							<?php else: ?>
								<p>No cars have been rented yet.</p>
							<?php endif; ?>
						</div>
					</div>
				</div>
				
				
			</div>

		</main>
		<!-- MAIN -->
	</section>
	<!-- CONTENT -->
	

	<script src="script.js"></script>
	<script>
		// Use the earnings data in JavaScript
		const monthlyEarnings = <?php echo $earningsData; ?>;
		const totalEarnings = <?php echo $totalEarnings; ?>; // Pass total earnings to JavaScript
		const monthToMonthChanges = <?php echo $monthToMonthData; ?>; // Pass month-to-month changes to JavaScript

		// Area Chart
		const ctxArea = document.getElementById('areaChart').getContext('2d');
		const areaChart = new Chart(ctxArea, {
			type: 'line',
			data: {
				labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
				datasets: [{
					label: 'Earnings',
					data: monthlyEarnings, // Use the fetched earnings data
					backgroundColor: '#eee', 
					borderColor: '#DB504A', 
					borderWidth: 2,
					fill: true,
					tension: 0.3,
					pointBackgroundColor: '#342E37',
					pointRadius: 5,
					hoverRadius: 7
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false, // Allows flexible size
				scales: {
					x: {
						type: 'category',
						display: true,
						grid: {
							display: false
						},
						title: {
							display: true,
							text: 'Month', // Title for x-axis
							font: {
								size: 14,
								weight: 'bold'
							}
						}
					},
					y: {
						beginAtZero: true,
						grid: {
							color: '#ddd' // Light grid lines
						},
						ticks: {
							callback: function(value) {
								return '₱' + value.toLocaleString(); // Add "Sales" next to the amount
							}
						},
						title: {
							display: true,
							text: 'Earnings', // Title for y-axis
							font: {
								size: 14,
								weight: 'bold'
							}
						}
					}
				},
				plugins: {
					legend: {
						display: true,
						labels: {
							color: '#333',
							font: {
								size: 14
							}
						}
					},
					tooltip: {
						backgroundColor: '#4bc0c0',
						titleFont: { size: 16 },
						bodyFont: { size: 14 },
						bodyColor: '#fff',
						cornerRadius: 5,
						callbacks: {
							label: function(tooltipItem) {
								return '₱' + tooltipItem.raw.toLocaleString(); // Format tooltip as Philippine pesos with "Sales"
							}
						}
					}
				}
			}
		});


		// Use the total renters data in JavaScript
		const monthlyRenters = <?php echo $rentersData; ?>; // PHP passes JSON-encoded data

		// Bar Chart
		const ctxBar = document.getElementById('barChart').getContext('2d');
		const barChart = new Chart(ctxBar, {
			type: 'bar',
			data: {
				labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
				datasets: [{
					label: 'Total Renters',
					data: monthlyRenters, // Use the renters data
					backgroundColor: '#342E37', // Bar color
					borderColor: '#DB504A', // Border color for bars
					borderWidth: 1,
					borderRadius: 7,
					hoverBackgroundColor: 'rgba(255, 99, 132, 0.8)', // Hover background color
					hoverBorderColor: 'rgba(255, 99, 132, 1)', // Hover border color
					hoverBorderWidth: 2 // Border width when hovered
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				scales: {
					x: {
						grid: {
							display: false // Hide x-axis grid lines
						},
						title: {
							display: true,
							text: 'Month',
							font: {
								size: 14,
								weight: 'bold'
							}
						}
					},
					y: {
						beginAtZero: true,
						grid: {
							color: '#ddd' // Light grid lines
						},
						ticks: {
							callback: function(value) {
								return value.toLocaleString(); // Format y-axis ticks
							}
						},
						title: {
							display: true,
							text: 'Total Renters', // Title for y-axis
							font: {
								size: 14,
								weight: 'bold'
							}
						}
					}
				},
				plugins: {
					legend: {
						display: true,
						labels: {
							color: '#333',
							font: {
								size: 14
							}
						}
					},
					tooltip: {
						backgroundColor: '#36A2EB',
						titleFont: { size: 16 },
						bodyFont: { size: 14 },
						bodyColor: '#fff',
						cornerRadius: 5,
						callbacks: {
							label: function(tooltipItem) {
								return tooltipItem.raw.toLocaleString() + ' renters'; // Format tooltip
							}
						}
					}
				}
			}
		});

//=============================================================================================================================

		// Assuming you have the counts object from PHP
		const ctxPie = document.getElementById('pieChart').getContext('2d');

		// Calculate total
		const total = counts.accepted + counts.pending + counts.cancel;

		// Calculate percentages
		const data = [
			(counts.accepted / total) * 100,
			(counts.pending / total) * 100,
			(counts.cancel / total) * 100
		];

		// Pie Chart for Rent Status
		const pieChart = new Chart(ctxPie, {
			type: 'pie',
			data: {
				labels: ['Accepted', 'Pending', 'Cancel'],
				datasets: [{
					label: 'Rent Status',
					data: data,  // Use the calculated percentages
					backgroundColor: [
						'green',  // Accepted - dark gray
						'orange',  // Pending - red
						'red'      // Cancel - light gray
					],
					borderColor: [
						'rgba(75, 192, 192, 1)',
						'rgba(255, 205, 86, 1)',
						'rgba(255, 99, 132, 1)'
					],
					borderWidth: 1
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: {
						display: false, // Disable the default legend
					},
					tooltip: {
						backgroundColor: '#36A2EB',
						titleFont: { size: 16 },
						bodyFont: { size: 14 },
						bodyColor: '#fff',
						cornerRadius: 5
					}
				}
			}
		});

		//=====================================================================================================================================================================
		

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