<?php
session_start(); // Start the session at the very beginning of the script

// Redirect to login page if user is not logged in
if (!isset($_SESSION['username'])) {
    header('Location: register.php');
    exit();
}

// Get username for display
$display_username = isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Guest';

$servername = 'localhost';
$db_username = 'root'; // Renamed to avoid conflict with session $display_username
$password = '';
$dbname = 'tampering';

// Create database connection
$conn = new mysqli($servername, $db_username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die('Database Connection Failed: ' . $conn->connect_error);
}

$locations = [];
$sql = 'SELECT * FROM meter_data';
$result = $conn->query($sql);

if ($result !== FALSE) {
    while ($row1 = $result->fetch_assoc()) {
        // Sanitize data for HTML output
        $name = htmlspecialchars($row1['name']);
        $latitude = htmlspecialchars($row1['latitude']);
        $longitude = htmlspecialchars($row1['longitude']);
        $tampering_label = htmlspecialchars($row1['tampering_label']);
        $tampering_reason = htmlspecialchars($row1['tampering_reason'] ?? 'N/A'); // Use null coalescing for PHP 7+
        $status = htmlspecialchars($row1['status']);
        $sim_ip = htmlspecialchars($row1['SIM_IP']);

        // Determine marker icon based on status and tampering_label
        $marker_icon_url = 'WM.png'; // Default icon
        if ($status === 'Non Reporting') {
            $marker_icon_url = 'WM_red.png'; // Prioritize non-reporting status
        } elseif ($tampering_label == 1) {
            // If tampered but reporting, use default icon (as per original logic)
            $marker_icon_url = 'WM.png';
        }

        // Construct info window content
        $info_content = '<h4 style="color:blue">Meter: ' . $name . '</h4>' .
                        '<p><strong>Status:</strong> ' . $status . '</p>' .
                        '<p><strong>Location:</strong> ' . $latitude . ', ' . $longitude . '</p>' .
                        '<p><strong>SIM IP:</strong> ' . $sim_ip . '</p>' .
                        '<p><strong>Tampered:</strong> ' . ($tampering_label == 1 ? 'Yes' : 'No') . '</p>';

        if ($tampering_label == 1 && $tampering_reason !== 'N/A') {
            $info_content .= '<p><strong>Reason:</strong> ' . $tampering_reason . '</p>';
        }

        // Add alerts based on status/tampering
        if ($status === 'Non Reporting') {
            $info_content .= '<p style="color:red; font-weight:bold; margin-top:10px;">ALERT: This Meter is Not Reporting!</p>';
        } elseif ($tampering_label == 1) {
            $info_content .= '<p style="color:orange; font-weight:bold; margin-top:10px;">ALERT: This Meter is Tampered!</p>';
        }

        // Add to locations array for JavaScript
        $locations[] = [$info_content, $latitude, $longitude, $marker_icon_url];
    }
    $result->free(); // Free result set
}

// Encode PHP array to JSON for JavaScript
$data = json_encode($locations);

// Close database connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="stylesheet" href="css/styles.css">

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AMR SIM Map Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

    <style>
        /* Embedded CSS from your styles.css with responsive adjustments */
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: #f8fff8;
            margin: 0;
            padding: 0;
        }

        /* Top Bar */
        .top-bar {
            display: flex;
            justify-content: flex-end; /* Align items to the right by default */
            align-items: center;
            gap: 30px;
            margin-bottom: 10px;
            background: #0a2239;
            color: #fff;
            padding: 12px 30px;
            font-size: 1.1em;
            border-radius: 0 0 10px 10px;
            position: relative; /* Added for dropdown positioning */
        }
        .top-bar i { margin-right: 7px; }

        /* Profile Dropdown */
        .profile-dropdown {
            position: relative;
            cursor: pointer;
            z-index: 1000;
            /* Flex properties to position it to the left side within the top-bar on desktop,
               but still respecting flex-end on the top-bar itself. */
            margin-right: auto; /* Pushes other items to the right */
        }

        .profile-icon {
            font-size: 1.5em; /* Increased size to make it more prominent */
            color: #fff;
            /* No margin-right needed here, as the parent flexbox will handle spacing */
        }

        .dropdown-content {
            display: none;
            position: absolute;
            background-color: #0a2239;
            min-width: 160px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
            left: 0; /* Align to the left of the profile icon */
            border-radius: 8px;
            overflow: hidden;
            top: 45px; /* Position below the top bar */
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .dropdown-content.show {
            display: block;
        }

        .dropdown-content p {
            color: #fff;
            padding: 12px 16px;
            margin: 0;
            font-size: 1em;
            white-space: nowrap; /* Prevent username from wrapping */
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .dropdown-content button {
            color: #fff;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            background: none;
            border: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.2s;
        }

        .dropdown-content button i {
            margin-right: 8px; /* Space out icon from text */
        }

        .dropdown-content button:hover {
            background-color: #1a3a5a;
        }


        /* Dashboard Header Row (title left, button right) */
        .dashboard-header-row, .map-header-row {
            width: 97%;
            max-width: 1800px; /* Concise shorthand */
            margin: 0 auto; /* Concise shorthand */
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 18px;
            padding-bottom: 0;
        }
        .dashboard-title {
            font-size: 2em;
            color: #0a2239;
            margin: 0 0 0 10px;
            letter-spacing: 1px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
            white-space: normal; /* Default to wrapping for small screens */
        }

        /* External Dashboard Button - Enhanced 3D Style */
        :root {
            --primary: #4681f4;    /* Main blue */
            --secondary: #55c2da;  /* Lighter blue for gradient */
            --shadow: #274690;     /* Deeper blue for shadow */
            --bevel: #e3f0ff;      /* Light blue for highlight */
        }

        .redirect-button {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 16px 38px;
            background: linear-gradient(100deg, var(--primary) 0%, var(--secondary) 100%);
            color: #fff;
            font-size: 1.18em;
            font-weight: 800;
            border: 2px solid rgba(255,255,255,0.18);
            border-radius: 18px;
            cursor: pointer;
            outline: none;
            letter-spacing: 1.5px;
            position: relative;
            z-index: 1;
            margin-right: 12px;
            box-shadow:
                0 8px 0 0 var(--shadow),
                0 16px 32px 0 rgba(60,60,70,0.15),
                0 1px 0 0 var(--bevel) inset,
                0 -4px 12px 0 rgba(255,255,255,0.10) inset;
            transition:
                background 0.22s,
                box-shadow 0.22s,
                transform 0.18s;
            overflow: hidden;
        }
        .redirect-button::before {
            content: '';
            position: absolute;
            top: 6px; left: 12px; right: 12px;
            height: 18px;
            background: linear-gradient(90deg, rgba(255,255,255,0.22), rgba(255,255,255,0.07));
            border-radius: 12px 12px 22px 22px;
            pointer-events: none;
            z-index: 2;
        }
        .redirect-button:active {
            transform: translateY(4px) scale(0.97);
            box-shadow:
                0 4px 0 0 var(--shadow),
                0 8px 18px 0 rgba(60,60,70,0.10),
                0 1px 0 0 var(--bevel) inset,
                0 -2px 8px 0 rgba(255,255,255,0.08) inset;
        }
        .redirect-button:hover {
            background: linear-gradient(100deg, var(--secondary) 0%, var(--primary) 100%);
            box-shadow:
                0 12px 0 0 var(--shadow),
                0 20px 40px 0 rgba(60,60,70,0.16),
                0 2px 0 0 var(--bevel) inset,
                0 -6px 18px 0 rgba(255,255,255,0.13) inset;
            filter: brightness(1.07);
        }
        .redirect-button i {
            font-size: 1.2em;
            margin-right: 8px;
            filter: drop-shadow(0 1px 2px rgba(0,0,0,0.18));
        }
        .report-btn {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 12px 28px;
            background: linear-gradient(90deg, #1e90ff 60%, #3498db 100%);
            color: #fff;
            border-radius: 8px;
            font-weight: bold;
            font-size: 1.08em;
            border: none;
            outline: none;
            cursor: pointer;
            box-shadow: 0 2px 8px #e0eaff;
            transition: background 0.2s, transform 0.2s;
        }
        .report-btn:hover {
            background: linear-gradient(90deg, #3498db 60%, #1e90ff 100%);
            transform: translateY(-2px) scale(1.04);
        }

        /* AMR SIM Map Tile - Outer container styles */
        .amr-map-tile {
            width: 95%;
            max-width: 1000px; /* Increased from 900px for more horizontal space */
            margin: 28px auto 0 auto;
            background: #f4faff;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(30, 144, 255, 0.10);
            padding: 22px 38px; /* Base padding */
        }
        .amr-map-left {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        /* AMR Map Icon */
        .amr-map-icon {
            font-size: 2.2em; /* Slightly larger icon for better balance */
            color: #1e90ff;
            margin-right: 10px; /* Increased margin for better separation */
        }

        /* Map title - Responsive adjustments */
        @media (min-width: 768px) {
            .amr-map-title {
                white-space: nowrap; /* Re-enabled nowrap for larger screens now that there's more space */
            }
        }
        @media (max-width: 767px) {
            .amr-map-title {
                font-size: 1.1em;
            }
        }
        .amr-map-title { /* Base styles for the title */
            font-size: 1.22em;
            font-weight: 700;
            letter-spacing: 0.5px;
            color: #0a2239;
            white-space: normal; /* This property ensures wrapping on small screens by default */
        }
        .map-link-btn {
            display: flex;
            align-items: center;
            gap: 9px;
            background: linear-gradient(90deg, #1e90ff 60%, #3498db 100%);
            color: #fff;
            font-weight: bold;
            font-size: 1.12em;
            padding: 11px 32px;
            border-radius: 22px;
            text-decoration: none;
            box-shadow: 0 2px 8px #e0eaff;
            transition: background 0.2s, transform 0.2s;
            border: none;
            outline: none;
            margin-left: 12px;
            margin-right: 12px;
        }
        .map-link-btn.active {
            filter: brightness(1.1) drop-shadow(0 0 8px #1e90ff33);
            cursor: default;
        }
        .map-link-btn i { font-size: 1.2em; }
        .map-link-btn:hover {
            background: linear-gradient(90deg, #3498db 60%, #1e90ff 100%);
            color: #fff;
            transform: translateY(-2px) scale(1.04);
            box-shadow: 0 6px 16px #b3d3fa;
        }
        .tneb-logo {
            height: 48px;
            width: auto;
            margin-left: 12px;
            border-radius: 8px;
            box-shadow: 0 2px 10px #e0eaff;
            background: #fff;
            padding: 4px 8px;
            object-fit: contain;
            transition: filter 0.3s, transform 0.3s;
        }
        .animated-logo:hover {
            filter: drop-shadow(0 0 18px #1e90ff) brightness(1.2);
            transform: scale(1.13) rotate(-8deg);
        }
        .tneb-logo.large-logo {
            height: 80px;
            width: auto;
        }
        @media (min-width: 900px) {
            .tneb-logo.large-logo {
                height: 120px;
                width: auto;
            }
        }
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.25) rotate(10deg); box-shadow: 0 0 40px 10px #00bfff88; }
            100% { transform: scale(1); }
        }
        .animated-logo-click {
            animation: pulse 0.6s ease-out;
        }

        /* Map Container */
        #map {
            width: 95vw;
            height: 480px;
            margin: 32px auto 0 auto;
            border-radius: 18px;
            box-shadow: 0 4px 18px #1e90ff22;
            max-width: 1200px;
        }

        /* Responsive styles for general dashboard elements */
        @media (max-width: 900px) {
            .dashboard-header-row, .map-header-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
                padding-top: 8px;
            }
            .amr-map-tile {
                padding: 18px 10px; /* Adjusted padding for smaller screens */
            }
            .amr-map-left { gap: 10px; }
            .tneb-logo {
                margin-left: 0;
                margin-top: 10px;
                height: 36px;
            }
            #map { height: 350px; }

            /* Adjust top bar for smaller screens */
            .top-bar {
                padding: 12px 15px;
                flex-wrap: wrap; /* Allow items to wrap if space is tight */
                justify-content: space-between; /* Distribute items */
            }
            .profile-dropdown {
                margin-right: 0; /* Remove auto margin on small screens */
            }
            .top-bar .weather-info, .top-bar .time-info {
                font-size: 0.9em;
                margin-right: 0; /* Remove potential extra margin */
            }
            .dropdown-content {
                left: auto; /* Remove left alignment for smaller screens */
                right: 0; /* Align to the right of the profile icon on small screens if desired, or adjust as needed */
            }
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="profile-dropdown">
            <span class="profile-icon" id="profileIcon"><i class="fas fa-user-circle"></i></span>
            <div class="dropdown-content" id="profileDropdownContent">
                <p>Welcome, <?= $display_username ?></p>
                <form action="logout.php" method="post" style="margin: 0;">
                    <button type="submit"><i class="fas fa-sign-out-alt"></i> Logout</button>
                </form>
            </div>
        </div>

        <div class="weather-info"><i class="fas fa-cloud-sun"></i> Chennai: 36°C, Overcast</div>
        <div class="time-info"><i class="fas fa-clock"></i> <span id="currentTime"></span></div>
    </div>

    <div class="dashboard-header-row">
        <button class="report-btn" onclick="window.location.href='index.php'">
            <i class="fas fa-table"></i> Report
        </button>
    </div>

    <div class="amr-map-tile my-3">
      <div class="row align-items-center justify-content-center">
        <div class="col-12 col-md-5 d-flex align-items-center justify-content-center justify-content-md-start mb-3 mb-md-0">
          <span class="amr-map-icon me-2"><i class="fas fa-map-marker-alt"></i></span>
          <span class="amr-map-title">AMR SIM track with Google Maps</span>
        </div>
        <div class="col-12 col-md-4 d-flex justify-content-center mb-3 mb-md-0">
          <button class="map-link-btn" id="mapBtn">
            <i class="fas fa-map"></i> MAP
          </button>
        </div>
        <div class="col-12 col-md-3 d-flex justify-content-center justify-content-md-end">
          <img src="TNEB.png" alt="TNEB Logo" class="tneb-logo large-logo animated-logo" id="tnebLogo">
        </div>
      </div>
    </div>

    <div id="map"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    // Function to update current time
    function updateTime() {
        const now = new Date();
        // Updated to reflect current time for Chennai, India (IST)
        document.getElementById('currentTime').textContent = now.toLocaleString('en-GB', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });
    }
    // Update time every second and on initial load
    setInterval(updateTime, 1000);
    updateTime(); // Initial call to display time immediately

    // Profile dropdown functionality
    document.addEventListener('DOMContentLoaded', function() {
        const profileIcon = document.getElementById('profileIcon');
        const profileDropdownContent = document.getElementById('profileDropdownContent');

        if (profileIcon && profileDropdownContent) { // Ensure elements exist before adding listeners
            profileIcon.addEventListener('click', function(event) {
                profileDropdownContent.classList.toggle('show');
                event.stopPropagation(); // Prevent the window click listener from immediately closing it
            });

            // Close the dropdown if the user clicks outside of it
            window.addEventListener('click', function(event) {
                // Check if the click was outside the profile icon AND outside the dropdown content itself
                if (!profileIcon.contains(event.target) && !profileDropdownContent.contains(event.target)) {
                    if (profileDropdownContent.classList.contains('show')) {
                        profileDropdownContent.classList.remove('show');
                    }
                }
            });
        } else {
            console.error("Profile icon or dropdown content element not found. Check IDs: profileIcon, profileDropdownContent");
        }
    });

    // PHP-generated JavaScript variable containing map marker data.
    var data = <?php echo $data; ?>;

    // Google Maps initialization
    function initMap() {
        var map = new google.maps.Map(document.getElementById('map'), {
            zoom: 7,
            center: new google.maps.LatLng(11.127123, 78.656891), // Center of Tamil Nadu
            mapTypeId: google.maps.MapTypeId.ROADMAP,
            mapTypeControl: false,
            streetViewControl: false,
            panControl: false,
            zoomControlOptions: { position: google.maps.ControlPosition.LEFT_BOTTOM }
        });

        var infowindow = new google.maps.InfoWindow({ maxWidth: 250 });
        var markers = [];

        // Loop through data to create markers
        for (var i = 0; i < data.length; i++) {
            var marker = new google.maps.Marker({
                position: new google.maps.LatLng(parseFloat(data[i][1]), parseFloat(data[i][2])),
                map: map,
                icon: {
                    url: data[i][3], // Custom marker icon URL (e.g., WM.png, WM_red.png)
                    scaledSize: new google.maps.Size(30, 30), // Size of the icon
                    origin: new google.maps.Point(0,0),
                    anchor: new google.maps.Point(15, 15) // Anchor point for the icon
                }
            });
            markers.push(marker); // Add marker to array

            // Add click listener to show info window
            google.maps.event.addListener(marker, 'click', (function(marker, i) {
                return function() {
                    infowindow.setContent(data[i][0]);
                    infowindow.open(map, marker);
                }
            })(marker, i));
        }

        // Function to automatically center and zoom map based on markers
        function autoCenter() {
            var bounds = new google.maps.LatLngBounds();
            if (markers.length > 0) {
                for (var i = 0; i < markers.length; i++) {
                    bounds.extend(markers[i].position);
                }
                map.fitBounds(bounds);
                // If only one marker, set a closer zoom level
                if (markers.length === 1) map.setZoom(12);
            } else {
                // If no markers, set a default center and zoom
                map.setCenter(new google.maps.LatLng(11.127123, 78.656891));
                map.setZoom(7);
            }
        }
        autoCenter(); // Call autocenter on map load
    }
    </script>
    <script>
    // TNEB logo animation effect on click
    document.addEventListener('DOMContentLoaded', function() {
        var logo = document.getElementById('tnebLogo');
        if (logo) { // Check if logo element exists
            logo.addEventListener('click', function() {
                logo.classList.add('animated-logo-click');
                // Remove the class after the animation completes
                setTimeout(function() {
                    logo.classList.remove('animated-logo-click');
                }, 600); // Animation duration is 0.6s
            });
        }
    });
    </script>
    <script async defer src="https://maps.googleapis.com/maps/api/js?key=AIzaSyC-ntkzWK3RNvjIhGVZNpzQAGgkHx2zeDc&callback=initMap"></script>
</body>
</html>