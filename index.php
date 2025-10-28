<?php
session_start();

$servername = "localhost";
$db_username = "root"; 
$db_password = "";     
$dbname = "cap101"; 

$conn = new mysqli($servername, $db_username, $db_password, $dbname);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Homepage</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-LN+7fdVzj6u52u30Kp6M/trliBMCMKTyK833zpbD+pXdCLuTusPj697FH4R/5mcr" crossorigin="anonymous">
    <!-- FONT -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,100;0,200;0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,500;1,600;1,700;1,800;1,900&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet">

    <!-- icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer">

    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f0f2f5; /* Light gray background, matching login */
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .headerHome {
            background-color: #19860f !important; /* Green header, matching login accents */
            padding: 1rem 0 !important;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .headerHome .navbar-brand {
            margin-right: auto;
            display: flex; /* Use flex to align logo and text */
            align-items: center;
        }

        .headerHome .navbar-brand img {
            height: 50px;
            width: auto;
            margin-right: 15px;
        }

        .headerHome .navbar-brand .logo-text {
            color: #fff;
            font-weight: 600;
            font-size: 1.8rem; /* Slightly larger for prominence */
        }

        .hero-section {
            flex-grow: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 4rem 0; /* More vertical padding */
            background: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('https://via.placeholder.com/1920x1080/c8e6c9/19860f?text=Green+Farm+Landscape') no-repeat center center/cover; /* Darker overlay for better text contrast */
            position: relative;
        }

        .container.my-5 {
            max-width: 1100px; /* Slightly wider container */
            position: relative;
            z-index: 10; /* Ensure content is above background overlay */
        }

        .card {
            border: none;
            border-radius: 0.75rem; /* Matching login page's border-radius */
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1); /* Matching login page's shadow */
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            overflow: hidden;
            min-height: 400px; /* Fixed height for consistency */
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background-color: #fff; /* Explicit white background */
        }

        .card:hover {
            transform: translateY(-8px); /* More pronounced hover effect */
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15); /* Stronger shadow on hover */
        }

        .card-body {
            padding: 2rem; /* Increased padding */
            text-align: center;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .card-title {
            font-size: 1.5rem; /* Larger title */
            font-weight: 700;
            color: #19860f; /* Green title, matching login h1 */
            margin-bottom: 1.5rem; /* More space below title */
        }

        .card-img-top {
            width: 100%;
            height: 180px;
            object-fit: contain;
            padding: 15px; /* Slightly more padding around image */
            background-color: #fdfdfd;
            border-bottom: 1px solid #eee;
            margin-top: auto; /* Push image to bottom if content is short */
        }

        .btn-outline-primary {
            color: #19860f;
            border-color: #19860f;
            border-radius: 0.5rem; /* Matching login page button radius */
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            transition: all 0.2s ease;
            margin: 0 2rem 2rem 2rem; /* Consistent margins for buttons */
        }

        .btn-outline-primary:hover {
            background-color: #19860f;
            color: #fff;
            box-shadow: 0 2px 8px rgba(25, 134, 15, 0.4);
        }

        .footer {
            background-color: #19860f !important; /* Green footer */
            color: #fff;
            padding: 1.25rem 0; /* Slightly more padding */
            text-align: center; /* Center copyright */
            box-shadow: 0 -2px 4px rgba(0, 0, 0, 0.1);
        }
        .footer p {
            margin-bottom: 0;
            font-size: 0.95rem;
        }

        /* Adjustments for smaller screens */
        @media (max-width: 768px) {
            .hero-section .card {
                margin-bottom: 2rem; /* Add more space between cards on mobile */
            }
            .headerHome .navbar-brand .logo-text {
                font-size: 1.5rem;
            }
            .headerHome .navbar-brand img {
                height: 45px;
            }
            .card-title {
                font-size: 1.3rem;
            }
            .card-body {
                padding: 1.5rem;
            }
            .btn-outline-primary {
                margin: 0 1.5rem 1.5rem 1.5rem;
            }
        }

        @media (max-width: 576px) {
            .login-container h1 {
                font-size: 1.5rem;
            }
            .card-img-top {
                height: 150px;
            }
        }
    </style>
</head>

<body>

    <header class="headerHome">
        <div class="container">
            <nav class="navbar navbar-expand-lg navbar-dark p-0">
                <a href="index.php" class="navbar-brand">
                    <img src="photos/Department_of_Agriculture_of_the_Philippines.png" alt="Department of Agriculture Logo">
                    <span class="logo-text">Province of Antique</span>
                </a>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <main class="hero-section">
        <div class="container">
            <div class="row justify-content-center gx-4 gy-4"> 
                
                <div class="col-sm-10 col-md-6 col-lg-4 d-flex">
                    <div class="card w-100">
                        <div class="card-body">
                            <h5 class="card-title">FARMERS LOGIN</h5>
                        </div>
                        <img src="photos/PAgri.png" class="card-img-top" alt="Farmer Icon">
                        <a href="pages/farmers-login.php" class="btn btn-outline-primary">Log-in Here</a>
                    </div>
                </div>

                <div class="col-sm-10 col-md-6 col-lg-4 d-flex">
                    <div class="card w-100">
                        <div class="card-body">
                            <h5 class="card-title">MUNICIPAL AGRICULTURIST'S LOGIN</h5>
                        </div>
                        <img src="photos/MAgri.png" class="card-img-top" alt="Municipal Agriculturist Icon">
                        <a href="pages/municipal-login.php" class="btn btn-outline-primary">Log-in Here</a>
                    </div>
                </div>
                
                <div class="col-sm-10 col-md-6 col-lg-4 d-flex">
                    <div class="card w-100">
                        <div class="card-body">
                            <h5 class="card-title">SYSTEM ADMIN LOGIN</h5>
                        </div>
                        <img src="photos/SAdmin.png" class="card-img-top" alt="System Admin Icon">
                        <a href="pages/admin-login.php" class="btn btn-outline-primary">Log-in Here</a>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="footer text-white">
        <div class="container">
            <p>&copy; BSIT-4. All Rights Reserved.</p>
        </div>
    </footer>

    <!--SCRIPTS BOOTSTRAP -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-ndDqU0Gzau9qJ1lfW4pNLlhNTkCfHzAVBReH9diLvGRem5+R9g2FzA8ZGN954O5Q"
        crossorigin="anonymous"></script>

</body>

</html>