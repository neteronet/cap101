<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Homepage</title>

    <link rel="stylesheet" href="css/style.css">

    <!-- BOOTSTRAP -->
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
</head>


<body>

    <header class="headerHome p-3 text-bg-primary sticky-top">
        <div class="container">
            <div class="d-flex flex-wrap align-items-center justify-content-center justify-content-between">
                <a href="index.html" class="d-flex align-items-center mb-2 mb-lg-0 text-white text-decoration-none">
                    <img src="photos/AntiqueProv Logo.png" alt="province of antique" height="50">
                </a>
            </div>
        </div>
    </header>

    <!-- Hero Section -->

    <main class="hero-section position-relative">
        <div class="container position-relative z-1">
            <div class="container mt-5 mb-5">
                <div class="row justify-content-center gx-4"> <!-- gx-4 adds horizontal gap -->

                    <div class="col-md-4 d-flex justify-content-center">
                        <div class="card fixed-size">
                            <div class="card-body justify-content-center text-center">
                                <h5 class="card-title">FARMERS LOGIN</h5>
                            </div>
                            <img src="photos/PAgri.png" class="card-img-top" alt="...">
                            <a href="pages/farmers-login.php" class="btn btn-outline-primary btn-lg btn-block my-4 mx-4">Log-in Here</a>
                        </div>
                    </div>

                    <div class="col-md-4 d-flex justify-content-center">
                        <div class="card fixed-size">
                            <div class="card-body justify-content-center text-center">
                                <h5 class="card-title">MUNICIPAL AGRICULTURIST'S LOGIN</h5>
                            </div>
                            <img src="photos/MAgri.png" class="card-img-top" alt="...">
                            <a href="pages/municipal-login.php" class="btn btn-outline-primary btn-lg btn-block my-4 mx-4">Log-in Here</a>
                        </div>
                    </div>
                    
                    <div class="col-md-4 d-flex justify-content-center">
                        <div class="card fixed-size">
                            <div class="card-body justify-content-center text-center">
                                <h5 class="card-title">SYSTEM ADMIN LOGIN</h5>
                            </div>
                            <img src="photos/SAdmin.png" class="card-img-top" alt="...">
                            <a href="pages/admin-login.php" class="btn btn-outline-primary btn-lg btn-block my-4 mx-4">Log-in Here</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>


    <footer class="text-bg-primary text-white py-2">
        <div class="container">
            <div class="row justify-content-between">
                <div class="col-10 d-flex align-items-center">
                    <!-- BLANK SPACE -->
                </div>
                <div class="col-2 align-items-center d-flex justify-content-end">
                    <p class="mb-0">&copy; BSIT-4. All Rights Reserved.</p>
                </div>
            </div>
        </div>
    </footer>




    <!--SCRIPTS BOOTSTRAP -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-ndDqU0Gzau9qJ1lfW4pNLlhNTkCfHzAVBReH9diLvGRem5+R9g2FzA8ZGN954O5Q"
        crossorigin="anonymous"></script>

</body>

</html>

