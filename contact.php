<?php
// Load latest announcements for landing page carousel (up to 5)
// require_once __DIR__ . '/home_data.php'; // Not needed for contact page

// Example contact data for municipalities in Antique
$municipal_contacts = [
    'San Jose de Buenavista' => [
        'Municipal Agriculturist' => 'Engr. Juan Dela Cruz',
        'Email' => 'sanjose.agri@agriconnect.ph',
        'Phone' => '(036) 540-1234',
        'Office' => 'Municipal Hall, San Jose de Buenavista, Antique',
        'Office_Hours' => 'M-F 8:00 AM - 5:00 PM',
    ],
    'Pandan' => [
        'Municipal Agriculturist' => 'Ms. Maria S. Reyes',
        'Email' => 'pandan.agri@agriconnect.ph',
        'Phone' => '(036) 540-5678',
        'Office' => 'Pandan Municipal Agriculture Office, Pandan, Antique',
        'Office_Hours' => 'M-F 8:00 AM - 5:00 PM',
    ],
    'Tibiao' => [
        'Municipal Agriculturist' => 'Mr. Jose P. Lopez',
        'Email' => 'tibiao.agri@agriconnect.ph',
        'Phone' => '(036) 540-9012',
        'Office' => 'Municipal Hall Annex B, Tibiao, Antique',
        'Office_Hours' => 'M-F 8:00 AM - 5:00 PM',
    ],
    'Patnongon' => [
        'Municipal Agriculturist' => 'Dr. Lita G. Santos',
        'Email' => 'patnongon.agri@agriconnect.ph',
        'Phone' => '(036) 540-3456',
        'Office' => 'Patnongon Agricultural Services Unit, Patnongon, Antique',
        'Office_Hours' => 'M-F 8:00 AM - 5:00 PM',
    ],
    'Anini-y' => [
        'Municipal Agriculturist' => 'Atty. Benjie M. Ramos',
        'Email' => 'aniniy.agri@agriconnect.ph',
        'Phone' => '(036) 540-7890',
        'Office' => 'Anini-y Municipal Building, Anini-y, Antique',
        'Office_Hours' => 'M-F 8:00 AM - 5:00 PM',
    ],
    'Hamtic' => [
        'Municipal Agriculturist' => 'Mr. Rodel A. Pama',
        'Email' => 'hamtic.agri@agriconnect.ph',
        'Phone' => '(036) 540-2001',
        'Office' => 'Hamtic MAO Office, Hamtic, Antique',
        'Office_Hours' => 'M-F 8:00 AM - 5:00 PM',
    ],
    'Sibalom' => [
        'Municipal Agriculturist' => 'Ms. Sheila M. Dela Torre',
        'Email' => 'sibalom.agri@agriconnect.ph',
        'Phone' => '(036) 540-3002',
        'Office' => 'Sibalom Town Hall, Sibalom, Antique',
        'Office_Hours' => 'M-F 8:00 AM - 5:00 PM',
    ],
    // Add more municipalities as needed for a complete list...
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Agriconnect</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Base Styles (copied from index.php for consistency) */
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
        .navbar nav ul li a.active::after { /* Added active state */
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

        /* --- CONTACT PAGE SPECIFIC STYLES --- */
        .contact-hero {
            background-color: #f7fff6; /* Light background */
            padding: 100px 0 50px 0;
            margin-top: 60px; /* Offset for fixed navbar */
            text-align: center;
        }
        .contact-hero h1 {
            font-size: 3em;
            font-weight: 700;
            color: #19860f; /* Primary Green */
            margin-bottom: 10px;
        }
        .contact-hero p {
            font-size: 1.1em;
            color: #555;
            max-width: 800px;
            margin: 0 auto 30px auto;
        }
        .contact-list-section {
            padding: 50px 0 100px 0;
            background-color: #ffffff;
        }
        .contact-card {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
        }
        .contact-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(25, 134, 15, 0.15);
        }
        .contact-card h3 {
            font-size: 1.6em;
            font-weight: 700;
            color: #4C9945; /* Darker Green */
            margin-bottom: 20px;
            /* Orange Accent for underline */
            border-bottom: 3px solid #F7A31C;
            display: inline-block;
            padding-bottom: 5px;
            line-height: 1.2;
        }
        .contact-card ul {
            list-style: none;
            padding: 0;
            margin-top: 20px;
        }
        .contact-card ul li {
            margin-bottom: 15px;
            font-size: 0.98em;
            color: #555;
            display: flex;
            align-items: flex-start;
            line-height: 1.5;
        }
        .contact-card ul li i {
            color: #F7A31C; /* Orange Accent Icon */
            margin-right: 15px;
            font-size: 1.2em;
            margin-top: 2px;
            flex-shrink: 0;
        }
        .contact-card strong {
            font-weight: 600;
            color: #1d2a1b;
            margin-right: 5px;
        }
        /* Active state for contact page link */
        .navbar nav ul li .contact-link {
            color: #146c0b;
        }
        .navbar nav ul li .contact-link::after {
            width: 100%;
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
                    <li><a href="index.php">Home</a></li>
                    <li><a href="about.php">About</a></li>
                    <li><a href="index.php#announcements">Announcements</a></li>
                    <!-- Set the link to this page as 'active' -->
                    <li><a href="contact.php" class="contact-link active">Contact</a></li>
                </ul>
                <a href="home.php" class="sign-in-btn">Sign In</a>
            </nav>
        </div>
    </header>

    <main>
        <!-- Contact Page Hero Section -->
        <section class="contact-hero">
            <div class="container">
                <h1>Get in Touch</h1>
                <p>
                    For inquiries, support, or direct communication regarding farmer data, subsidies, or municipal agricultural programs, please contact the appropriate Municipal Agriculture Office (MAO) below.
                </p>
                <i class="fas fa-hand-point-down fa-2x" style="color: #4C9945;"></i>
            </div>
        </section>

        <!-- Municipal Contact List Section -->
        <section class="contact-list-section">
            <div class="container">
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                    <?php foreach ($municipal_contacts as $municipality => $details) : ?>
                        <div class="col">
                            <div class="contact-card">
                                <h3><?php echo htmlspecialchars($municipality); ?> MAO</h3>
                                <ul>
                                    <li>
                                        <i class="fas fa-user-tie"></i>
                                        <div><strong>Head:</strong> <?php echo htmlspecialchars($details['Municipal Agriculturist']); ?></div>
                                    </li>
                                    <li>
                                        <i class="fas fa-envelope"></i>
                                        <div><strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($details['Email']); ?>"><?php echo htmlspecialchars($details['Email']); ?></a></div>
                                    </li>
                                    <li>
                                        <i class="fas fa-phone-alt"></i>
                                        <div><strong>Phone:</strong> <?php echo htmlspecialchars($details['Phone']); ?></div>
                                    </li>
                                    <li>
                                        <i class="fas fa-map-marker-alt"></i>
                                        <div><strong>Office:</strong> <?php echo htmlspecialchars($details['Office']); ?></div>
                                    </li>
                                    <li>
                                        <i class="fas fa-clock"></i>
                                        <div><strong>Hours:</strong> <?php echo htmlspecialchars($details['Office_Hours']); ?></div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>