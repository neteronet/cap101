<?php
// No specific PHP data needed for this static page, but keep the structure.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Agriconnect - Subsidy System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* ================================================= */
        /* == CORE STYLES FROM index.php (FOR CONSISTENCY) == */
        /* ================================================= */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f7fff6; /* Light background for the page content */
            scroll-behavior: smooth; 
        }
        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 25px;
        }
        /* Navbar */
        .navbar {
            background-color: #fff;
            height: 60px;
            padding: 0 1.25rem;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            border-bottom: 1px solid #ddd;
        }
        .navbar .container {
            max-width: 1200px;
            height: 60px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 0 auto;
            padding: 0;
        }
        .navbar .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }
        .navbar .logo img {
            width: 44px;
            height: 44px;
            max-width: none;
            padding: 0;
            border-radius: 6px;
            background: transparent;
            object-fit: contain;
        }
        .navbar .logo div {
            font-size: 0.95rem;
            font-weight: 600;
            color: #19860f;
            line-height: 1.2;
            margin-top: 0;
        }
        .navbar nav {
            display: flex;
            align-items: center;
            gap: 24px;
        }
        .navbar nav ul {
            list-style: none;
            display: flex;
            margin: 0;
            padding: 0;
            gap: 24px;
        }
        .navbar nav ul li {
            margin: 0;
        }
        .navbar nav ul li a {
            color: #19860f;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            padding: 6px 0;
            transition: color 0.3s ease;
            position: relative;
        }
        /* Active link style */
        .navbar nav ul li a.active,
        .navbar nav ul li a:hover {
            color: #146c0b; 
        }
        .navbar nav ul li a::after {
            content: ''; 
            position: absolute;
            bottom: 0px; 
            left: 0;
            width: 0;
            height: 2px;
            background-color: #19860f;
            transition: width 0.3s ease;
        }
        .navbar nav ul li a:hover::after,
        .navbar nav ul li a.active::after {
            width: 100%;
        }
        .navbar .sign-in-btn {
            background-color: #19860f;
            color: #fff;
            border: none;
            padding: 8px 18px;
            border-radius: 20px;
            font-size: 0.9rem;
            line-height: 1;
        }
        .navbar .sign-in-btn:hover {
            background-color: #146c0b;
        }

        /* ================================================= */
        /* ============ ABOUT US PAGE SPECIFIC STYLES ============= */
        /* ================================================= */
        .about-hero {
            padding-top: 120px;
            padding-bottom: 50px;
            background-color: #4C9945; /* Dark Green Header */
            color: #fff;
            text-align: center;
            position: relative;
            margin-bottom: 40px;
        }

        .about-hero h1 {
            font-size: 2.8rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .about-hero p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .about-content-section {
            padding: 30px 0 100px 0;
            background-color: #fff;
            box-shadow: 0 0 20px rgba(0,0,0,0.05);
            border-radius: 10px;
            margin-bottom: 60px;
            border-top: 5px solid #F7A31C; /* Orange accent line */
        }
        .about-content-section .container {
            max-width: 900px;
        }

        /* Document Styling */
        .section-heading {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
            margin-top: 40px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
            margin-bottom: 25px;
        }

        .subsection-heading {
            font-size: 1.4rem;
            font-weight: 600;
            color: #4C9945; /* Green for sub-headings */
            margin-top: 30px;
            margin-bottom: 15px;
        }

        .sub-sub-heading {
            font-size: 1.1rem;
            font-weight: 600;
            color: #F7A31C; /* Orange for sub-sub-headings */
            margin-top: 20px;
            margin-bottom: 10px;
        }

        .document-text {
            font-size: 1rem;
            line-height: 1.7;
            color: #555;
            margin-bottom: 20px;
            text-align: justify;
        }

        /* List Styling for Objectives and Significance */
        .document-list {
            list-style-type: none;
            padding-left: 0;
        }

        .document-list li {
            position: relative;
            padding-left: 25px;
            margin-bottom: 12px;
            line-height: 1.6;
        }

        .document-list li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: #19860f;
            font-weight: 700;
        }

        /* List for Objectives (Numbered) */
        .objectives-list {
            list-style-type: none;
            counter-reset: objective-counter;
            padding-left: 0;
        }
        .objectives-list li {
            counter-increment: objective-counter;
            position: relative;
            padding-left: 30px;
            margin-bottom: 15px;
            line-height: 1.6;
            font-weight: 500;
            color: #333;
        }
        .objectives-list li::before {
            content: counter(objective-counter) ".";
            position: absolute;
            left: 0;
            font-weight: 700;
            color: #F7A31C;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .navbar {
                height: auto;
            }
            .navbar .container {
                flex-direction: column;
                height: auto;
                padding-top: 10px;
                padding-bottom: 10px;
            }
            .navbar nav {
                width: 100%;
                justify-content: center;
            }
            .navbar nav ul {
                flex-wrap: wrap;
                gap: 10px;
            }
            .about-hero {
                padding-top: 90px;
                padding-bottom: 30px;
            }
            .about-hero h1 {
                font-size: 2rem;
            }
            .section-heading {
                font-size: 1.7rem;
            }
            .subsection-heading {
                font-size: 1.2rem;
            }
        }

    </style>
</head>
<body>
    <header class="navbar">
        <div class="container">
            <a href="index.php" class="logo">
                <img src="photos/logo.png" alt="Province of Antique Logo">
            </a>
            <nav>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="about.php" class="active">About</a></li>
                    <li><a href="index.php#announcements">Announcements</a></li>
                    <li><a href="contact.php">Contact</a></li>
                </ul>
                <a href="home.php" class="sign-in-btn">Sign In</a>
            </nav>
        </div>
    </header>

    <main>
        <div class="about-hero">
            <div class="container">
                <p>CAPSTONE PROJECT</p>
                <h1>Agriconnect: Subsidy System with Integrated Farmer Profiling</h1>
                <p>Modernizing Agricultural Aid and Analytics for the Province of Antique</p>
            </div>
        </div>

        <section class="about-content-section">
            <div class="container">
                <h2 class="section-heading">Our Story & Vision: Digitizing Agricultural Support</h2>

                <!-- The Challenge -->
                <h3 class="subsection-heading">The Challenge in Agricultural Support</h3>
                <p class="document-text">
                    Agriculture remains the lifeblood of the Philippine economy, yet many smallholder farmers face significant hurdles, including low wages and limited access to modern technology. A major challenge is the slow, manual process of distributing government subsidies (like RCEF and calamity aid) and managing farmer data. This often results in:
                </p>
                <ul class="document-list">
                    <li>Delayed assistance reaching those who need it most.</li>
                    <li>Data inconsistencies and difficulty in tracking records.</li>
                    <li>Vulnerability to fraudulent or duplicate claims.</li>
                </ul>
                <p class="document-text">
                    Existing farmer data systems are often decentralized and outdated, preventing Municipal Agriculture Offices (MAOs) and Provincial Agriculturist Offices (PAOs) from providing timely, data-driven support to the sector.
                </p>

                <!-- Introducing Agriconnect: The Solution -->
                <h3 class="subsection-heading">Introducing Agriconnect: The Digital Solution</h3>
                <p class="document-text">
                    **Agriconnect** is the answer to these challenges. It is a web-based, innovative system designed to modernize and centralize agricultural aid distribution, specifically for farmers registered with the **Registry System for Basic Sectors in Agriculture (RSBSA)** in the **Province of Antique**.
                </p>
                <p class="document-text">
                    Our system transforms the manual process into a secure, efficient, and transparent digital workflow by integrating several key technologies:
                </p>
                <ul class="document-list">
                    <li>A **QR Code System** for quick and secure one-time beneficiary verification and claims.</li>
                    <li>**Integrated Farmer Profiling** utilizing existing RSBSA data for automatic eligibility and account creation.</li>
                    <li>**Municipal Analytics** to provide real-time reporting on distribution, casualties, and program effectiveness.</li>
                </ul>
                
                <!-- Our Core Objectives & Impact -->
                <h3 class="subsection-heading">Our Core Objectives & Impact</h3>
                <p class="document-text">The overarching mission of Agriconnect is to create a digital platform that guarantees timely, accurate, and accountable agricultural support for Antique's farming community.</p>

                <h4 class="sub-sub-heading">What We Aim to Achieve:</h4>
                <ol class="objectives-list">
                    <li>**Streamlined Distribution:** To design a centralized application that manages the end-to-end process of subsidy distribution and casualties reporting.</li>
                    <li>**Data Integrity:** To ensure accurate verification of beneficiaries by leveraging and updating existing RSBSA profiles, minimizing duplicate or outdated entries.</li>
                    <li>**Secure Claims:** To develop a transparent subsidy management feature with QR-code validation to reduce fraud and long queues.</li>
                    <li>**Actionable Insights:** To provide Provincial and Municipal Agriculture Offices with powerful analytics for better decision-making and resource allocation.</li>
                </ol>

                <h4 class="sub-sub-heading">Who Benefits from Agriconnect?</h4>
                <ul class="document-list">
                    <li><strong>Smallholder Farmers:</strong> Receive immediate, hassle-free, and guaranteed support via a unique QR code.</li>
                    <li><strong>Municipal Agriculture Offices:</strong> Gain real-time data, accurate record-keeping, and efficient disaster relief reporting.</li>
                    <li><strong>The Provincial Agriculture Office:</strong> Can aggregate data across municipalities to track program efficiency and identify irregularities at a glance.</li>
                    <li><strong>Local Government Units (LGUs):</strong> Benefit from enhanced transparency and accountability in the utilization of public funds.</li>
                </ul>

                <!-- Our Focus -->
                <h3 class="subsection-heading">Our Focus and Scope</h3>
                <p class="document-text">
                    Agriconnect is a capstone project focused entirely on the digitalization and automation of agricultural subsidy programs in the **Antique Province**.
                </p>
                <h4 class="sub-sub-heading">Target Users:</h4>
                <p class="document-text">
                    The system is tailored for use by the Municipal and Provincial Agriculture Offices and is designed to serve only farmers **registered under the RSBSA** within the selected municipalities of Antique. This focus allows us to provide a deeply integrated and highly effective solution for local governance.
                </p>
                <p class="document-text">
                    *Note: The system requires a consistent internet connection to ensure real-time updates and secure claim validation at the point of disbursement.*
                </p>
            </div>
        </section>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    <!-- No custom JS needed for this static page -->
</body>
</html>