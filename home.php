<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agriconnect</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Basic Reset & Body Styling */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Poppins', sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #ffffff;
            /* Added for smooth scrolling when clicking on jump links */
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
        /* REFINEMENT: Added hover effect with bottom line */
        .navbar nav ul li a:hover {
            color: #146c0b; /* Darker green on hover */
        }
        .navbar nav ul li a::after {
            content: ''; 
            position: absolute;
            bottom: 0px; /* Adjusted to be closer to the text */
            left: 0;
            width: 0;
            height: 2px;
            background-color: #19860f;
            transition: width 0.3s ease;
        }
        .navbar nav ul li a:hover::after {
            width: 100%;
        }
        /* REMOVED: Old pseudo-element styles as the new one replaces them */
        /* .navbar nav ul li:nth-child(1) a::after, ... */
        
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
        /* Hero Section - UPDATED FOR LEFT ALIGNMENT */
        .hero-section {
            position: relative;
            height: 100vh;
            display: flex;
            /* REFINEMENT: Aligned items to the center for better vertical balance */
            align-items: center; 
            justify-content: flex-start;
            color: #fff;
            text-align: left;
            padding-top: 60px;
        }
        .hero-bg-img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 0;
        }
        .hero-section .container {
            padding-left: 150px;
            margin-left: 0;
            margin-right: auto;
        }
        .hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.55);
            z-index: 1;
        }
        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 600px;
            padding-left: 0;
            text-align: left;
        }
        .hero-content .welcome-text {
            font-size: 1.2em;
            letter-spacing: 2px;
            margin-bottom: 10px;
            color: #eee;
            text-align: left;
        }
        /* REFINEMENT: Removed Pacifico font to ensure it works without custom load, reduced size */
        .hero-content h1 {
            font-family: 'Poppins', sans-serif; /* Consistent font */
            font-size: 5.5em; /* Slightly smaller for a more balanced look */
            font-weight: 700;
            margin-bottom: 20px;
            line-height: 0.9;
            color: #fff;
            display: block;
            text-align: left; 
        }
        .hero-content .hero-logo {
            width: 450px; /* Slightly larger image for impact */
            height: auto;
            margin-bottom: 25px; /* Reduced margin for tighter composition */
            display: block;
            margin-left: 0; 
            margin-right: auto; 
        }
        /* REMOVED: Unused/replaced h1 span styling */
        /* .hero-content h1 span { ... } */
        .hero-content .description-text {
            font-size: 1.05em; 
            margin-bottom: 40px;
            color: #E0E0E0; 
            line-height: 1.8;
            max-width: 500px; 
            text-align: left;
        }
        .hero-content .sign-in-hero-btn {
            background-color: #F7A31C;
            color: #fff;
            border: none;
            padding: 14px 30px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: background-color 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .hero-content .sign-in-hero-btn:hover {
            background-color: #E8951A;
        }

        /* Main Content & Features */
        .main-content-section {
            padding-top: 150px;
            padding-bottom: 90px;
            background-color: #ffffff;
        }

        .feature-cards-row {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: -100px;
            margin-bottom: 120px;
            padding: 0 24px;
        }
        .feature-pill {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 24px 30px;
            border-radius: 15px;
            border: 1px solid rgba(25, 134, 15, 0.12);
            background: #ffffff;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .feature-pill:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.12);
        }

        /* Icon Styling */
        .feature-pill-icon {
            width: 60px;
            height: 60px;
            min-width: 60px;
            border-radius: 50%;
            background-color: #e6f5e4;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 4px 12px rgba(25, 134, 15, 0.1);
        }

        .feature-pill-icon i {
            font-size: 1.8rem;
            color: #19860f;
        }

        .feature-pill-info h4 {
            margin: 0 0 8px;
            font-size: 1.2rem;
            font-weight: 600;
            color: #1d2a1b;
        }
        .feature-pill-info p {
            margin: 0 0 10px;
            font-size: 0.95rem;
            color: #4b584c;
            line-height: 1.45;
        }
        .feature-pill-info a {
            margin-top: 8px;
            display: inline-flex;
            align-items: center;
            font-weight: 600;
            color: #19860f;
            text-decoration: none;
            font-size: 0.92rem;
            /* REFINEMENT: Link styling for better CTA */
            padding-bottom: 2px;
            border-bottom: 1px solid transparent;
            transition: border-bottom 0.3s ease, color 0.3s ease;
        }
        .feature-pill-info a:hover {
            border-bottom: 1px solid #19860f;
            color: #146c0b;
        }
        .feature-pill-info a i {
            margin-left: 6px;
            font-size: 0.85rem;
        }
        @media (max-width: 992px) {
            .feature-cards-row {
                flex-direction: column;
            }
        }
        /* Introduction Section */
        .introduction-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 60px;
        }

        .intro-left {
            flex: 1;
            position: relative;
            max-width: 50%;
        }
        
        .main-circle-img-wrapper {
            position: relative;
            width: 100%;
            padding-bottom: 100%; 
            max-width: 450px;
            margin-right: auto;
        }

        .main-circle-img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background-color: #ccc; 
            background-image: url('https://via.placeholder.com/550/dddddd?text=Harvester+Field');
            background-size: cover;
            background-position: center;
            /* REFINEMENT: Thicker white border */
            box-shadow: 0 0 0 10px #f0f0f0, 0 0 0 22px #fff; 
        }

        .small-circle-img {
            position: absolute;
            /* REFINEMENT: Adjusted position slightly */
            bottom: -30px; 
            left: 100px;
            width: 140px;
            height: 140px;
            border-radius: 50%;
            background-color: #999;
            background-image: url('https://via.placeholder.com/150/999999?text=Farmer+Fruit');
            background-size: cover;
            background-position: center;
            z-index: 5;
            border: 8px solid #fff; /* REFINEMENT: Thicker border */
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
        }

        .intro-right {
            flex: 1;
            max-width: 50%;
        }

        .intro-label {
            color: #F7A31C;
            font-size: 0.9em;
            font-weight: 700;
            margin-bottom: 10px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .intro-right h2 {
            font-size: 2.3em;
            font-weight: 700;
            line-height: 1.15;
            margin-bottom: 25px;
            color: #333;
        }

        .intro-text {
            font-size: 1.05em;
            color: #777;
            line-height: 1.7;
            margin-bottom: 30px;
        }

        .intro-features {
            display: flex;
            gap: 40px;
            margin-bottom: 30px;
        }

        .intro-feature-item {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            font-weight: 500;
            color: #333;
            /* REFINEMENT: Increased max-width */
            max-width: 170px;
        }
        .intro-feature-item p {
            margin-top: 10px;
            line-height: 1.4;
            /* REFINEMENT: Slightly bolder text for feature description */
            font-weight: 600; 
        }


        .intro-feature-item .icon-placeholder {
            width: 55px;
            height: 55px;
            border-radius: 50%;
            background-color: #4C9945;
            position: relative;
        }
        /* Using Unicode/Emoji as placeholder icons */
        .intro-feature-item:nth-child(1) .icon-placeholder::after {
            content: '🧺'; 
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 2em;
        }
        .intro-feature-item:nth-child(2) .icon-placeholder {
             background-color: #4C9945;
        }
        .intro-feature-item:nth-child(2) .icon-placeholder::after {
            content: '🌿';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 2em;
        }

        .intro-list {
            list-style: none;
            margin-bottom: 40px;
            padding-left: 0;
        }

        .intro-list li {
            display: flex;
            align-items: flex-start;
            /* REFINEMENT: Reduced margin */
            margin-bottom: 15px;
            font-size: 0.98em;
            color: #555;
        }

        .intro-list li .list-dot {
            display: block;
            width: 9px;
            height: 9px;
            min-width: 9px;
            border-radius: 50%;
            background-color: #F7A31C;
            margin-right: 12px;
            margin-top: 6px;
        }

        .discover-more-btn {
            background-color: #4C9945;
            color: #fff;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        .discover-more-btn:hover {
            background-color: #3D7A36;
            transform: translateY(-2px);
        }

        /* Why Choose Our Website Section */

        .why-choose-header {
            background-color: #5FA941;
            height: 90px;
            position: relative;
            z-index: 1;
        }

        .why-choose-section {
            background-color: #F7FFF6;
            padding-bottom: 100px;
        }

        /* RESTORED 2-COLUMN FLEX LAYOUT */
        .why-choose-content-wrapper {
            display: flex; /* Restored to flex */
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 30px;
            /* REFINEMENT: Increased overlap for a more integrated look */
            margin-top: -30px; /* Was -20px */
            gap: 60px;
            position: relative;
            z-index: 2;
        }

        .why-choose-image {
            flex: 1;
            min-width: 45%;
            max-width: 45%;
            position: relative;
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
            background-image: url('photos/photo4.png');
            background-size: cover;
            background-position: center;
            border-radius: 0 0 0 0;
            height: 480px;
            margin-left: 0;
        }

        .leader-box {
            position: absolute;
            top: 35%; 
            left: 35%;
            right: auto;
            background-color: #F7A31C;
            padding: 20px 30px;
            font-size: 1.0em;
            font-weight: 600;
            line-height: 1.3;
            color: #333;
            text-align: center;
            width: 180px;
            height: 120px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            clip-path: polygon(0% 0%, 100% 0%, 100% 75%, 60% 75%, 50% 100%, 40% 75%, 0% 75%);
            transform: none;
        }

        .why-choose-text-content {
            flex: 1;
            /* REFINEMENT: Reduced top padding */
            padding-top: 40px; /* Was 60px */
            padding-left: 20px;
        }

        /* REDUCED H2 FONT SIZE FOR CONSISTENCY */
        .why-choose-text-content h2 {
            font-size: 2.3em; /* Reduced from 2.7em to match intro section */
            font-weight: 700;
            margin-bottom: 30px;
            color: #333;
            letter-spacing: -0.5px;
        }

        /* --- ADJUSTED FOR CONSISTENCY (MATCHING .intro-text) --- */
        .why-choose-text-content .description-text {
            /* Now 1.05em and line-height 1.7 to match .intro-text */
            font-size: 1.05em; 
            color: #666;
            line-height: 1.7; 
            margin-bottom: 35px;
            max-width: 90%;
            font-weight: 400;
        }
        /* -------------------------------------------------------- */

        .checklist {
            list-style: none;
            /* REFINEMENT: Reduced margin */
            margin-bottom: 40px; /* Was 50px */
            padding-left: 0;
        }

        /* --- ADJUSTED FOR CONSISTENCY (MATCHING .intro-list li) --- */
        .checklist li {
            display: flex;
            align-items: flex-start;
            /* Now 0.98em to match .intro-list li */
            margin-bottom: 18px; 
            font-size: 0.98em; 
            font-weight: 500;
            color: #555;
            line-height: 1.6;
            transition: transform 0.2s ease;
        }
        /* -------------------------------------------------------- */

        .checklist li:hover {
            transform: translateX(5px);
        }
        .checklist li br {
            display: none;
        }
        .checklist li .check-icon {
            display: inline-block;
            /* REFINEMENT: Slightly larger icon for better visual weight */
            width: 30px; /* Was 28px */
            height: 30px; /* Was 28px */
            border-radius: 50%;
            /* REFINEMENT: Slightly increased margin for more space */
            margin-right: 20px; /* Was 18px */
            font-size: 1.3em; /* Was 1.2em */
            line-height: 30px; /* Adjusted line-height */
            text-align: center;
            color: #fff;
            font-weight: 700;
            flex-shrink: 0;
            margin-top: 0;
            background-color: #4C9945;
            /* REFINEMENT: Subtle button shadow */
            box-shadow: 0 2px 8px rgba(76, 153, 69, 0.3);
        }

        /* MODIFIED TO EXACTLY MATCH .discover-more-btn */
        .why-choose-btn {
            background-color: #4C9945; /* Changed from linear-gradient */
            color: #fff;
            border: none;
            padding: 15px 30px; /* Matched .discover-more-btn */
            border-radius: 8px; /* Matched .discover-more-btn */
            cursor: pointer;
            font-size: 16px;
            font-weight: 500; /* Matched .discover-more-btn */
            transition: background-color 0.3s ease, transform 0.2s ease; /* Matched .discover-more-btn */
            box-shadow: none; /* Removed box-shadow */
        }

        .why-choose-btn:hover {
            background-color: #3D7A36; /* Matched .discover-more-btn */
            transform: translateY(-2px); /* Matched .discover-more-btn */
            box-shadow: none; /* Removed hover box-shadow */
        }


        /* News & Articles Section */

        .news-section {
            background-color: #ffffff;
            padding: 50px 0 100px 0;
            /* Added padding-top to compensate for fixed header when jumping */
            padding-top: 100px; 
            margin-top: -60px; /* Offset the padding-top by header height */
        }
        
        /* New rule for the scroll-target section to adjust for the fixed navbar */
        #announcements {
            scroll-margin-top: 60px; /* Ensure content starts below fixed navbar */
        }

        .news-title-section {
            text-align: center;
            margin-bottom: 60px;
        }

        .news-title-section .update-label {
            color: #4C9945;
            font-size: 0.85em;
            font-weight: 500;
            margin-bottom: 5px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .news-title-section h2 {
            font-size: 2.8em;
            font-weight: 700;
            color: #333;
        }

        .blog-cards-row {
            display: flex;
            justify-content: space-between;
            gap: 30px;
        }

        .blog-card {
            flex: 1;
            background-color: #fff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border: none;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
        }

        .blog-img-container {
            position: relative;
            width: 100%;
            padding-bottom: 65%;
            background-size: cover;
            background-position: center;
            border-radius: 12px 12px 0 0;
        }

        /* Specific images for blog cards */
        .blog-card:nth-child(1) .blog-img-container {
            background-image: url('photos/photo5.jpg');
        }
        .blog-card:nth-child(2) .blog-img-container {
            background-image: url('photos/photo6.jfif');
        }
        .blog-card:nth-child(3) .blog-img-container {
            background-image: url('photos/photo7.jpg');
        }


        .blog-date-overlay {
            position: absolute;
            /* REFINEMENT: Moved down for better separation */
            bottom: -20px; 
            left: 50%;
            transform: translateX(-50%);
            background-color: #F7A31C;
            color: #fff;
            /* REFINEMENT: Increased padding */
            padding: 12px 20px; 
            border-radius: 6px; /* Slightly sharper corners */
            font-size: 0.9em;
            font-weight: 500;
            z-index: 10;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
        }

        .blog-content {
            /* REFINEMENT: Increased top padding to clear the date overlay */
            padding: 40px 20px 25px 20px; 
            text-align: center;
            flex-grow: 1; 
        }

        .blog-meta {
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 0.85em;
            color: #888;
            margin-bottom: 15px;
        }

        .blog-meta span {
            margin: 0 10px;
            display: flex;
            align-items: center;
        }
        /* Simulating meta icons: Author icon and Comment icon */
        .blog-meta span:nth-child(1)::before {
            content: '👤'; 
            font-size: 1.2em;
            margin-right: 5px;
            color: #4C9945;
            line-height: 1;
        }
        .blog-meta span:nth-child(2)::before {
            content: '💬';
            font-size: 1.2em;
            margin-right: 5px;
            color: #4C9945;
            line-height: 1;
        }

        .blog-content h3 {
            font-size: 1.3em;
            font-weight: 700;
            line-height: 1.4;
            color: #333;
            transition: color 0.3s ease; /* REFINEMENT: Added transition */
        }
        /* REFINEMENT: Title color change on hover */
        .blog-card:hover .blog-content h3 {
            color: #19860f; 
        }

        /* Responsive Adjustments for new sections (Updated for better mobile layout) */
        @media (max-width: 992px) {
            .why-choose-content-wrapper {
                flex-direction: column;
                margin-top: -50px;
                gap: 30px;
            }
            .why-choose-image {
                max-width: 100%;
                min-width: 100%;
                height: 400px;
                margin-left: 0;
                display: block; /* Ensure it is visible */
            }
            .leader-box {
                top: 20px;
                right: 20px;
                left: auto; /* Reset left position */
                transform: none;
            }
            .why-choose-text-content {
                padding-top: 0;
                padding-left: 0; /* Adjusted for better mobile padding */
            }
            .blog-cards-row {
                flex-direction: column;
            }
        }
        /* Existing Responsive Adjustments (Modified for left alignment) */
        @media (max-width: 768px) {
            .navbar .container {
                flex-direction: column;
                text-align: center;
            }
            .navbar nav {
                flex-direction: column;
                width: 100%;
                margin-top: 15px;
            }
            .navbar nav ul {
                margin-top: 15px;
                flex-wrap: wrap;
                justify-content: center;
                margin-right: 0;
            }
            .navbar nav ul li {
                margin: 0 10px 10px 10px;
            }
            .navbar .sign-in-btn {
                margin-top: 10px;
            }
            .hero-content {
                padding: 0 25px;
                text-align: left;
                max-width: 100%;
            }
            .hero-content h1 {
                font-size: 4em;
                justify-content: flex-start;
            }
            /* REMOVED: Unused h1 span styling */
            /* .hero-content h1 span {
                transform: translateY(-10px);
            } */
            .hero-content .hero-logo {
                margin-left: 0;
            }
            .feature-cards-row {
                flex-direction: column;
                margin-top: 0;
                margin-bottom: 50px;
            }
            .introduction-section {
                flex-direction: column;
                gap: 50px;
            }
            .intro-left, .intro-right {
                max-width: 100%;
            }
            .main-circle-img-wrapper {
                max-width: 100%;
            }
            .small-circle-img {
                left: 50%;
                transform: translateX(-50%);
                bottom: -50px;
            }
            .intro-features {
                flex-wrap: wrap;
                justify-content: center;
                gap: 20px;
            }
            .intro-feature-item {
                align-items: center;
                text-align: center;
            }
        }

        /* --- APPENDED STYLES FOR IMAGE MATCH (kept original for checklist colors) --- */

        /* 5. Checklist Icon Colors - Matching the three different colors in the image */
        .checklist li:nth-child(1) .check-icon {
            background-color: #61A847; /* Green for first item */
        }
        
        .checklist li:nth-child(2) .check-icon {
            background-color: #BFE140; /* Yellow-Green for second item */
        }
        
        .checklist li:nth-child(3) .check-icon {
            background-color: #F7A31C; /* Orange/Yellow for third item */
        }
    </style>
</head>
<body>
    <header class="navbar">
        <div class="container">
            <div class="logo">
                <img src="photos/logo.png" alt="Province of Antique Logo">
            </div>
            <nav>
                <ul>
                    <li><a href="#">Home</a></li>
                    <li><a href="#">About</a></li>
                    <!-- UPDATED: Added href="#announcements" for jump link -->
                    <li><a href="#announcements">Announcements</a></li> 
                    <li><a href="#">Contact</a></li>
                </ul>
                <a href="index.php" class="sign-in-btn">Sign In</a>
            </nav>
        </div>
    </header>

    <main class="hero-section">
        <div class="hero-overlay">
            <img src="photos/photo1.png" alt="Hero Background" class="hero-bg-img">
        </div>
        <div class="container hero-content">
            <img src="photos/elements.png" alt="Agriconnect" class="hero-logo">
            <p class="description-text">
                A unified platform for managing farmer profiles, subsidy distribution, and municipal agricultural analytics—all in one secure and efficient system.
                It provides a seamless experience for farmers, government agencies, and stakeholders, ensuring transparent and efficient agricultural management.
            </p>
            <a href="index.php" class="sign-in-hero-btn">Sign In</a>
        </div>
    </main>

    <!-- START: Existing Features and Introduction Section -->
    <section class="main-content-section">
        <div class="container">
            <!-- Feature Cards Row (UPDATED WITH ICONS AND STYLING) -->
            <div class="feature-cards-row">
                <div class="feature-pill">
                    <!-- NEW ICON -->
                    <div class="feature-pill-icon">
                        <i class="fas fa-qrcode"></i>
                    </div>
                    <div class="feature-pill-info">
                        <h4>QR Access</h4>
                        <p>Generate secure QR codes for farmer verification and quick check-ins.</p>
                        <a href="municipal-qrcode_management.php">Explore QR tools <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                <div class="feature-pill">
                    <!-- NEW ICON -->
                    <div class="feature-pill-icon">
                        <i class="fas fa-seedling"></i>
                    </div>
                    <div class="feature-pill-info">
                        <h4>Crop Monitoring</h4>
                        <p>Track planting status, growth stages, and field updates in real time.</p>
                        <a href="municipal-crop_monitoring.php">View crop status <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
                <div class="feature-pill">
                    <!-- NEW ICON -->
                    <div class="feature-pill-icon">
                        <i class="fas fa-coins"></i>
                    </div>
                    <div class="feature-pill-info">
                        <h4>Subsidy Support</h4>
                        <p>Manage requests, approvals, and claims with full transparency.</p>
                        <a href="municipal-subsidy_management.php">Manage subsidies <i class="fas fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>

            <!-- Introduction Section -->
            <div class="introduction-section">
                <div class="intro-left">
                    <div class="main-circle-img-wrapper">
                        <!-- Replace background-image URL with your actual Harvester image -->
                        <div class="main-circle-img" style="background-image: url('photos/photo2.png');"></div>
                        <!-- Replace background-image URL with your actual Farmer holding fruit image -->
                        <div class="small-circle-img" style="background-image: url('photos/photo3.png');"></div>
                    </div>
                </div>
                <div class="intro-right">
                    <p class="intro-label">Our Introductions</p>
                    <h2>Agriculture & Organic Product Farm</h2>
                    <p class="intro-text">
                        There are many variations of passages of lorem ipsum available but the majority have suffered alteration in some form by injected humor or random word which don't look even.
                    </p>
                    <div class="intro-features">
                        <div class="intro-feature-item">
                            <div class="icon-placeholder"></div>
                            <p>Growing fruits vegetables</p>
                        </div>
                        <div class="intro-feature-item">
                            <div class="icon-placeholder"></div>
                            <p>Tips for ripening your fruits</p>
                        </div>
                    </div>
                    <ul class="intro-list">
                        <li><i class="list-dot"></i> Lorem Ipsum is not simply random text.</li>
                        <li><i class="list-dot"></i> Making this the first true generator on the internet.</li>
                    </ul>
                    <button class="discover-more-btn">Discover More</button>
                </div>
            </div>
        </div>
    </section>
    <!-- END: Existing Features and Introduction Section -->

    <!-- START: Why Choose Our Website Section (NEWLY MODIFIED) -->
    <div class="why-choose-header"></div>
    <section class="why-choose-section">
        <div class="why-choose-content-wrapper">
            <div class="why-choose-image">
            </div>

            <div class="why-choose-text-content">
                <h2>Why Choose Our Website</h2>
                <p class="description-text">
                    Our platform provides a secure and efficient way to manage farmer profiles, subsidies, and agricultural analytics, ensuring transparency and ease for all stakeholders.
                </p>
                <ul class="checklist">
                    <li><i class="check-icon">✓</i> Secure QR Code Access for quick verification</li>
                    <li><i class="check-icon">✓</i> Real-time Crop Monitoring for better yields</li>
                    <li><i class="check-icon">✓</i> Transparent Subsidy Management for fair distribution</li>
                </ul>
                <button class="why-choose-btn">Discover More</button>
            </div>
        </div>
    </section>
    <!-- END: Why Choose Our Website Section -->

    <!-- START: News & Articles Section (NEWLY MODIFIED WITH ID) -->
    <section class="news-section" id="announcements">
        <div class="container">
            <div class="news-title-section">
                <p class="update-label">update</p>
                <h2>Announcements</h2>
            </div>
            <div class="blog-cards-row">
                <!-- Blog Card 1 - MODIFIED TO ANCHOR TAG -->
                <a href="index.php" class="blog-card">
                    <div class="blog-img-container">
                        <div class="blog-date-overlay">06 July 2022</div>
                    </div>
                    <div class="blog-content">
                        <div class="blog-meta">
                            <span>Kevin Martin</span>
                            <span>1 Comment</span>
                        </div>
                        <h3>Bringing Food Production Back To Cities</h3>
                    </div>
                </a>
                <!-- Blog Card 2 - MODIFIED TO ANCHOR TAG -->
                <a href="index.php" class="blog-card">
                    <div class="blog-img-container">
                        <div class="blog-date-overlay">06 July 2022</div>
                    </div>
                    <div class="blog-content">
                        <div class="blog-meta">
                            <span>Kevin Martin</span>
                            <span>0 Comments</span>
                        </div>
                        <h3>The Future of Farming, Smart Irrigation Solutions</h3>
                    </div>
                </a>
                <!-- Blog Card 3 - MODIFIED TO ANCHOR TAG -->
                <a href="index.php" class="blog-card">
                    <div class="blog-img-container">
                        <div class="blog-date-overlay">06 July 2022</div>
                    </div>
                    <div class="blog-content">
                        <div class="blog-meta">
                            <span>Kevin Martin</span>
                            <span>0 Comments</span>
                        </div>
                        <h3>Agronomy and relation to Other Sciences</h3>
                    </div>
                </a>
            </div>
        </div>
    </section>
    <!-- END: News & Articles Section -->
</body>
</html>