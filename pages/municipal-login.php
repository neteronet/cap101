<?php
    include "../includes/connection.php";  

    if(isset($_POST['login']) ) {
        $username = $_POST['username'];
        $password = $_POST['password'];

        $sql = "SELECT * FROM municipallogin WHERE username = '$username' and password = '$password'";
        $result = mysqli_query($conn, $sql);
        $row = mysqli_fetch_array($result, MYSQLI_ASSOC);
        $count = mysqli_num_rows($result);

        if($count == 1) {
            header("Location: municipal-dashboard.php");
        }
        else {
            echo '<script>
                window.location.href = "municipal-login.php";
                alert("Login failed. Invalid Email or Password!!!")
            </script>';
        }   
    }
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Municipal Agriculturist Log-In Page</title>

    <link rel="stylesheet" href="../css/style.css">
    <!-- BOOTSTRAP -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-LN+7fdVzj6u52u30Kp6M/trliBMCMKTyK833zpbD+pXdCLuTusPj697FH4R/5mcr" crossorigin="anonymous">
    
    <!-- FONT -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&family=Roboto:wght@400;500;700&display=swap"
        rel="stylesheet">

    <!-- icon -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer">
</head>

<body class="bg-body-tertiary d-flex flex-column min-vh-100">
    <!-- MAIN CONTENT -->

    <main class="flex-fill d-flex justify-content-center align-items-center py-5">
        <form class="form-login w-100 m-auto" method="POST">
            <div class="text-center mb-4">
                <img class="mb-3" src="../photos/OfficialSeal.png" alt="" width="150px" height="149px">
                <h1 class="h4 mb-1 fw-semibold">MUNICIPAL AGRICULTURIST LOGIN</h1>
            </div>

            <div class="form-floating mb-3">
                <input type="email" class="form-control" id="floatingInput" name="username" placeholder="name@example.com" required>
                <label for="floatingInput">Email</label>
            </div>

            <div class="form-floating mb-3">
                <input type="password" class="form-control" id="floatingPassword" name="password" placeholder="Password" required>
                <label for="floatingPassword">Password</label>
            </div>

            <button class="btn btn-primary w-100 py-2" type="submit" name="login">Login</button>
            
            <!-- Link to index.php -->
            <div class="text-center mt-3">
                <a href="../index.php" class="text-decoration-none text-primary fw-semibold">
                    Back to Home
                </a>
            </div>
        </form>
    </main>

    <!-- FOOTER -->
    <footer class="text-bg-primary text-white py-2 mt-auto">
        <div class="container">
            <div class="row justify-content-between">
                <div class="col-12 col-md-6"></div>
                <div class="col-12 col-md-6 text-md-end text-center">
                    <p class="mb-0">&copy; BSIT-4. All Rights Reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- BOOTSTRAP JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-ndDqU0Gzau9qJ1lfW4pNLlhNTkCfHzAVBReH9diLvGRem5+R9g2FzA8ZGN954O5Q"
        crossorigin="anonymous"></script>
</body>
</html>
