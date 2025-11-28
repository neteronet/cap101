<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agriconnect - Your Gateway to Agricultural Services</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 25%, #a5d6a7 50%, #81c784 75%, #66bb6a 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 40px 20px 0 20px;
            position: relative;
            overflow-x: hidden;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Animated background particles */
        .particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
        }

        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 20s infinite ease-in-out;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) translateX(0); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            100% { transform: translateY(-100vh) translateX(100px); opacity: 0; }
        }

        .header-section {
            text-align: center;
            color: white;
            margin-bottom: 80px;
            position: relative;
            z-index: 1;
            animation: fadeInDown 1s ease-out;
        }

        .back-to-home-btn {
            position: absolute;
            top: 20px;
            left: 20px;
            background: rgba(255, 255, 255, 0.95);
            color: #19860f;
            border: 2px solid #19860f;
            padding: 10px 20px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 10;
        }

        .back-to-home-btn:hover {
            background: #19860f;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(25, 134, 15, 0.3);
        }

        .back-to-home-btn i {
            font-size: 1rem;
        }

        @media (max-width: 768px) {
            .back-to-home-btn {
                top: 10px;
                left: 10px;
                padding: 8px 16px;
                font-size: 0.85rem;
            }
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header-section h1 {
            font-size: 4rem;
            font-weight: 800;
            margin-bottom: 20px;
            text-shadow: 3px 3px 6px rgba(0, 0, 0, 0.3);
            letter-spacing: -1px;
            color: #ffffff;
        }

        .header-section p {
            font-size: 1.4rem;
            font-weight: 400;
            opacity: 0.95;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
            max-width: 700px;
            margin: 0 auto;
            line-height: 1.6;
        }

        .login-cards-container {
            max-width: 1300px;
            margin: 0 auto;
            display: flex;
            justify-content: center;
            gap: 40px;
            flex-wrap: wrap;
            padding: 20px;
            position: relative;
            z-index: 1;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 25px;
            padding: 50px 35px;
            text-align: center;
            flex: 1;
            min-width: 320px;
            max-width: 380px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2),
                        0 5px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            overflow: hidden;
            border: 2px solid transparent;
            animation: fadeInUp 0.8s ease-out backwards;
        }

        .login-card:nth-child(1) { animation-delay: 0.1s; }
        .login-card:nth-child(2) { animation-delay: 0.2s; }
        .login-card:nth-child(3) { animation-delay: 0.3s; }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.5s;
        }

        .login-card:hover::before {
            left: 100%;
        }

        .login-card:hover {
            transform: translateY(-15px) scale(1.02);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3),
                        0 10px 25px rgba(0, 0, 0, 0.15);
            border-color: rgba(76, 153, 69, 0.3);
        }

        .login-card-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #4C9945 0%, #19860f 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 30px;
            font-size: 2.8rem;
            color: white;
            box-shadow: 0 10px 25px rgba(76, 153, 69, 0.3);
            transition: all 0.4s ease;
            position: relative;
        }

        .login-card:hover .login-card-icon {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 15px 35px rgba(76, 153, 69, 0.4);
        }

        .login-card-icon::after {
            content: '';
            position: absolute;
            top: -5px;
            left: -5px;
            right: -5px;
            bottom: -5px;
            border-radius: 50%;
            border: 3px solid rgba(76, 153, 69, 0.2);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.1);
                opacity: 0.7;
            }
        }

        .login-card h2 {
            color: #4C9945;
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 18px;
            transition: color 0.3s ease;
            letter-spacing: 0.5px;
        }

        .login-card:hover h2 {
            color: #19860f;
        }

        .login-card p {
            color: #555;
            font-size: 1rem;
            line-height: 1.7;
            margin-bottom: 30px;
            flex-grow: 1;
            transition: color 0.3s ease;
        }

        .login-card:hover p {
            color: #333;
        }

        .login-card-illustration {
            width: 100%;
            height: 160px;
            margin-top: auto;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, rgba(76, 153, 69, 0.05) 0%, rgba(25, 134, 15, 0.05) 100%);
            border-radius: 15px;
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }

        .login-card:hover .login-card-illustration {
            background: linear-gradient(135deg, rgba(76, 153, 69, 0.1) 0%, rgba(25, 134, 15, 0.1) 100%);
            transform: scale(1.05);
        }

        .login-card-illustration i {
            font-size: 4.5rem;
            color: #4C9945;
            transition: all 0.4s ease;
            z-index: 1;
        }

        .login-card:hover .login-card-illustration i {
            transform: scale(1.2) rotate(-5deg);
            color: #19860f;
        }

        .footer {
            background-color: #ffffff;
            color: #000000;
            text-align: center;
            padding: 20px;
            margin-top: auto;
            width: calc(100% + 40px);
            margin-left: -20px;
            margin-right: -20px;
            position: relative;
            z-index: 1;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
        }

        .footer p {
            margin: 0;
            font-size: 0.9rem;
            font-weight: 500;
            color: #000000;
        }

        /* Responsive Design */
        @media (max-width: 992px) {
            .header-section h1 {
                font-size: 3rem;
            }

            .header-section p {
                font-size: 1.2rem;
            }

            .login-cards-container {
                gap: 30px;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 30px 15px 0;
            }
            
            .footer {
                width: calc(100% + 30px);
                margin-left: -15px;
                margin-right: -15px;
            }

            .header-section {
                margin-bottom: 50px;
            }

            .header-section h1 {
                font-size: 2.5rem;
            }

            .header-section p {
                font-size: 1.1rem;
            }

            .login-cards-container {
                flex-direction: column;
                align-items: center;
                gap: 25px;
            }

            .login-card {
                max-width: 100%;
                padding: 40px 30px;
            }
        }

        @media (max-width: 480px) {
            .header-section h1 {
                font-size: 2rem;
            }

            .header-section p {
                font-size: 1rem;
            }

            .login-card {
                padding: 35px 25px;
                min-width: 100%;
            }

            .login-card-icon {
                width: 85px;
                height: 85px;
                font-size: 2.3rem;
            }

            .login-card h2 {
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body>
    <!-- Animated Background Particles -->
    <div class="particles" id="particles"></div>

    <!-- Header Section -->
    <div class="header-section">
        <a href="index.php" class="back-to-home-btn">
            <i class="fas fa-arrow-left"></i>
            <span>Back to Landing Page</span>
        </a>
        <h1>Welcome to Agriconnect</h1>
        <p>Your Gateway to Agricultural Services and Management</p>
    </div>

    <!-- Login Cards Section -->
    <div class="login-cards-container">
        <!-- Farmers Login Card -->
        <a href="pages/farmers-login.php" class="login-card">
            <div class="login-card-icon">
                <i class="fas fa-user-friends"></i>
            </div>
            <h2>FARMERS LOGIN</h2>
            <p>Access your farming dashboard, apply for subsidies, and manage your agricultural profile.</p>
            <div class="login-card-illustration">
                <i class="fas fa-seedling"></i>
            </div>
        </a>

        <!-- Municipal Agriculturist's Login Card -->
        <a href="pages/municipal-login.php" class="login-card">
            <div class="login-card-icon">
                <i class="fas fa-building"></i>
            </div>
            <h2>MUNICIPAL AGRICULTURIST'S LOGIN</h2>
            <p>Review applications, manage subsidies, and oversee agricultural programs in your municipality.</p>
            <div class="login-card-illustration">
                <i class="fas fa-clipboard-check"></i>
            </div>
        </a>

        <!-- System Admin Login Card -->
        <a href="pages/admin-login.php" class="login-card">
            <div class="login-card-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h2>SYSTEM ADMIN LOGIN</h2>
            <p>Manage system settings, users, and oversee all administrative functions of the platform.</p>
            <div class="login-card-illustration">
                <i class="fas fa-laptop-code"></i>
            </div>
        </a>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>© BSIT-4. All Rights Reserved.</p>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Create animated background particles
        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            const particleCount = 20;

            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                
                const size = Math.random() * 10 + 5;
                particle.style.width = size + 'px';
                particle.style.height = size + 'px';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 20 + 's';
                particle.style.animationDuration = (Math.random() * 10 + 15) + 's';
                
                particlesContainer.appendChild(particle);
            }
        }

        // Initialize particles on page load
        document.addEventListener('DOMContentLoaded', createParticles);

        // Add smooth scroll behavior
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>
