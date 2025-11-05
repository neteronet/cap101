<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agriconnect</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&family=Pacifico&display=swap" rel="stylesheet">
    <style>
        /* Basic Reset & Body Styling */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Montserrat', sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 20px;
        }
        /* Navbar */
        .navbar {
            background-color: rgba(255, 255, 255, 0.8); /* Slightly transparent white for the navbar */
            padding: 15px 0;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        .navbar .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .navbar .logo img {
            height: 40px; /* Adjust as needed */
        }
        .navbar nav {
            display: flex; /* To align ul and button */
            align-items: center;
        }
        .navbar nav ul {
            list-style: none;
            display: flex;
            margin-right: 20px; /* Space between nav links and sign-in button */
        }
        .navbar nav ul li {
            margin-left: 25px;
        }
        .navbar nav ul li a {
            color: #555;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }
        .navbar nav ul li a:hover {
            color: #4CAF50;
        }
        .navbar .sign-in-btn {
            background-color: #4CAF50;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s ease;
        }
        .navbar .sign-in-btn:hover {
            background-color: #45a049;
        }
        /* Hero Section */
        .hero-section {
            position: relative;
            height: 100vh; /* Full viewport height */
            background: url('background.jpg') no-repeat center center/cover; /* Make sure to replace 'background.jpg' with your image path */
            display: flex;
            align-items: center;
            justify-content: flex-start; /* Align content to the left */
            color: #fff;
            text-align: left;
            padding-top: 60px; /* Space for fixed navbar */
        }
        .hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.3); /* Slightly darken the background image */
            z-index: 1;
        }
        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 500px; /* Constrain content width */
            padding-left: 50px; /* Adjust as needed to match image */
        }
        .hero-content .welcome-text {
            font-size: 1.2em;
            letter-spacing: 2px;
            margin-bottom: 10px;
            color: #eee;
        }
        .hero-content h1 {
            font-family: 'Pacifico', cursive; /* Use Pacifico for the main title */
            font-size: 6em; /* Larger font size for the brand name */
            margin-bottom: 20px;
            line-height: 1;
            color: #fff;
            display: flex;
            align-items: flex-end; /* Align the leaf icon with the text */
        }
        .hero-content h1 span {
            display: inline-block;
            width: 40px; /* Size of the leaf */
            height: 40px;
            background: url('photos/leaf-icon.png') no-repeat center center/contain; /* Replace with your leaf icon path */
            margin-left: 10px;
            transform: translateY(-5px); /* Adjust vertical position */
        }
        .hero-content .description-text {
            font-size: 1.1em;
            margin-bottom: 30px;
            color: #ddd;
            line-height: 1.8;
        }
        .hero-content .sign-in-hero-btn {
            background-color: #4CAF50;
            color: #fff;
            border: none;
            padding: 15px 30px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 18px;
            font-weight: 700;
            transition: background-color 0.3s ease;
        }
        .hero-content .sign-in-hero-btn:hover {
            background-color: #45a049;
        }
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .navbar .container {
                flex-direction: column;
                text-align: center;
            }
            .navbar nav {
                flex-direction: column; /* Stack nav elements vertically */
                width: 100%;
                margin-top: 15px;
            }
            .navbar nav ul {
                margin-top: 15px;
                flex-wrap: wrap;
                justify-content: center;
                margin-right: 0; /* Remove right margin for nav ul */
            }

            .navbar nav ul li {
                margin: 0 10px 10px 10px;
            }

            .navbar .sign-in-btn {
                margin-top: 10px;
            }

            .hero-content {
                padding: 0 20px;
                text-align: center;
                max-width: 100%;
            }

            .hero-content h1 {
                font-size: 4em;
                justify-content: center;
            }

            .hero-content .description-text {
                font-size: 1em;
            }

            .hero-content .sign-in-hero-btn {
                padding: 12px 25px;
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <header class="navbar">
        <div class="container">
            <div class="logo">
                <!-- Removed the logo image tag -->
            </div>
            <nav>
                <ul>
                    <li><a href="#">Home</a></li>
                    <li><a href="#">About</a></li>
                    <li><a href="#">Services</a></li>
                    <li><a href="#">Projects</a></li>
                    <li><a href="#">News</a></li>
                    <li><a href="#">Shop</a></li>
                    <li><a href="#">Contact</a></li>
                </ul>
                <button class="sign-in-btn">Sign In</button>
            </nav>
        </div>
    </header>

    <main class="hero-section">
        <div class="hero-overlay"></div>
        <div class="container hero-content">
            <p class="welcome-text">WELCOME TO</p>
            <h1>Agriconnect <span></span></h1>
            <p class="description-text">
                Lorem ipsum dolor sit amet consectetur adipisicing elit. It autu,
                tincidunt nulls ullamcorps mattis, puller dapibus leu. Lorem ipsum
                dolor sit amet, consectetur adipiscing elit. Ut elit tellus,
                luctus nec ullamcorper mattis, pulvinar dapibus leo. Lorem ipsum
                dolor sit amet, consectetur adipiscing elit. Ut elit tellus,
                luctus nec ullamcorper mattis, pulvinar dapibus leo.
            </p>
            <button class="sign-in-hero-btn">Sign In</button>
        </div>
    </main>
</body>
</html>