<?php

session_start();

require_once __DIR__ . '/includes/role_helpers.php';

/* =========================
   REDIRECT IF LOGGED IN
========================= */

if (isset($_SESSION['user_id'])) {
    redirectToRoleDashboard($_SESSION['role'] ?? 'user');
}


/* =========================
   GET ERROR MESSAGE
========================= */

$error = $_GET['error'] ?? '';

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
Login | Smart Inventory
</title>


<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family: Arial, sans-serif;
}


/* =========================
   BODY
========================= */

body {

    min-height: 100vh;

    display: flex;
    justify-content: center;
    align-items: center;

    padding: 25px;

    background:
        linear-gradient(
            135deg,
            #111111 0%,
            #1a1a1a 45%,
            #111111 100%
        );

}


/* =========================
   MAIN WRAPPER
========================= */

.login-wrapper {

    width: 100%;
    max-width: 1000px;

    min-height: 590px;

    display: grid;

    grid-template-columns:
        1.1fr 1fr;

    background: white;

    border-radius: 24px;

    overflow: hidden;

    box-shadow:
        0 30px 80px
        rgba(0, 0, 0, 0.30);

}


/* =========================
   LEFT SIDE
========================= */

.login-info {

    position: relative;

    overflow: hidden;

    padding: 60px;

    color: white;

    display: flex;
    flex-direction: column;
    justify-content: center;

    background:
        linear-gradient(
            145deg,
            #111111,
            #111111
        );

}


/* DECORATION */

.login-info::before {

    content: "";

    position: absolute;

    width: 280px;
    height: 280px;

    border-radius: 50%;

    background:
        rgba(255,255,255,0.08);

    right: -90px;
    top: -90px;

}


.login-info::after {

    content: "";

    position: absolute;

    width: 200px;
    height: 200px;

    border-radius: 50%;

    background:
        rgba(255,255,255,0.05);

    left: -70px;
    bottom: -70px;

}


.info-content {

    position: relative;

    z-index: 2;

}


/* LOGO */

.logo {

    width: 72px;
    height: 72px;

    display: flex;
    justify-content: center;
    align-items: center;

    border-radius: 20px;

    background:
        rgba(255,255,255,0.15);

    font-size: 34px;

    margin-bottom: 25px;

    backdrop-filter: blur(6px);

}


.login-info h1 {

    font-size: 38px;

    line-height: 1.15;

    margin-bottom: 16px;

}


.login-info p {

    color: #eeeeee;

    line-height: 1.7;

    font-size: 15px;

    max-width: 430px;

}


/* FEATURES */

.features {

    margin-top: 35px;

    display: grid;

    gap: 14px;

}


.feature {

    display: flex;

    align-items: center;

    gap: 12px;

    color: #eeeeee;

    font-size: 14px;

}


.feature-icon {

    width: 32px;
    height: 32px;

    border-radius: 9px;

    display: flex;
    align-items: center;
    justify-content: center;

    background:
        rgba(255,255,255,0.13);

}


/* =========================
   RIGHT SIDE
========================= */

.login-form-side {

    padding: 60px 55px;

    display: flex;

    flex-direction: column;

    justify-content: center;

    background: #ffffff;

}


.login-header {

    margin-bottom: 30px;

}


.login-header .small-title {

    color: #111111;

    font-size: 13px;

    font-weight: bold;

    letter-spacing: 1px;

    text-transform: uppercase;

    margin-bottom: 8px;

}


.login-header h2 {

    color: #111111;

    font-size: 32px;

    margin-bottom: 10px;

}


.login-header p {

    color: #707070;

    line-height: 1.6;

}


/* =========================
   ERROR
========================= */

.error {

    display: flex;

    align-items: center;

    gap: 10px;

    background: #f5f5f5;

    color: #000000;

    border: 1px solid #dddddd;

    padding: 13px 15px;

    border-radius: 10px;

    margin-bottom: 20px;

    font-size: 14px;

}


/* =========================
   FORM
========================= */

.form-group {

    margin-bottom: 20px;

}


.form-group label {

    display: block;

    color: #333333;

    font-size: 14px;

    font-weight: bold;

    margin-bottom: 8px;

}


.input-wrapper {

    position: relative;

}


.input-icon {

    position: absolute;

    top: 50%;

    left: 14px;

    transform:
        translateY(-50%);

    font-size: 17px;

    color: #707070;

}


.form-group input {

    width: 100%;

    padding:
        14px 45px 14px 44px;

    border:
        1px solid #d0d0d0;

    border-radius: 10px;

    outline: none;

    font-size: 15px;

    color: #111111;

    background: #fafafa;

    transition: 0.2s;

}


.form-group input:focus {

    border-color: #111111;

    background: white;

    box-shadow:
        0 0 0 4px
        rgba(37,99,235,0.10);

}


/* SHOW PASSWORD BUTTON */

.toggle-password {

    position: absolute;

    top: 50%;

    right: 13px;

    transform:
        translateY(-50%);

    border: none;

    background: none;

    cursor: pointer;

    font-size: 17px;

    color: #707070;

    padding: 5px;

}


/* =========================
   LOGIN BUTTON
========================= */

.login-btn {

    width: 100%;

    padding: 14px;

    border: none;

    border-radius: 10px;

    background:
        linear-gradient(
            135deg,
            #111111,
            #000000
        );

    color: white;

    font-size: 16px;

    font-weight: bold;

    cursor: pointer;

    transition: 0.2s;

    box-shadow:
        0 8px 20px
        rgba(37,99,235,0.25);

}


.login-btn:hover {

    transform:
        translateY(-1px);

    box-shadow:
        0 12px 25px
        rgba(37,99,235,0.35);

}


.login-btn:active {

    transform:
        translateY(0);

}

.register-area {
    margin-top: 14px;
    text-align: center;
    color: #8f90aa;
    font-size: 13px;
}

.register-btn {
    margin-top: 10px;
    width: 100%;
    min-height: 46px;
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    border-radius: 10px;
    border: none;
    background: linear-gradient(135deg, #111111, #000000);
    color: #ffffff;
    font-size: 16px;
    font-weight: bold;
    transition: .2s;
    box-shadow: 0 8px 20px rgba(37,99,235,0.25);
}

.register-btn:hover {
    transform: translateY(-1px);
    color: #ffffff;
    background: linear-gradient(135deg, #111111, #000000);
    box-shadow: 0 12px 25px rgba(37,99,235,0.35);
}


/* =========================
   FOOTER TEXT
========================= */

.login-footer {

    text-align: center;

    margin-top: 25px;

    color: #9a9a9a;

    font-size: 12px;

}


.login-footer strong {

    color: #707070;

}


/* =========================
   RESPONSIVE
========================= */

@media (max-width: 850px) {

    .login-wrapper {

        grid-template-columns: 1fr;

        max-width: 520px;

    }


    .login-info {

        padding: 40px;

    }


    .login-info h1 {

        font-size: 30px;

    }


    .features {

        display: none;

    }


    .login-form-side {

        padding: 45px 35px;

    }

}


@media (max-width: 500px) {

    body {

        padding: 15px;

    }


    .login-wrapper {

        border-radius: 18px;

    }


    .login-info {

        padding: 32px 25px;

    }


    .logo {

        width: 58px;
        height: 58px;

        font-size: 28px;

        margin-bottom: 18px;

    }


    .login-info h1 {

        font-size: 25px;

    }


    .login-form-side {

        padding: 35px 25px;

    }


    .login-header h2 {

        font-size: 27px;

    }

}

</style>

<link rel="stylesheet" href="/smart_inventory/assets/ui.css?v=2">
<style>.brand-logo{width:48px;height:48px;display:block;object-fit:contain}</style>
<link rel="stylesheet" href="/smart_inventory/assets/ux_enhancements.css?v=2">
<link rel="stylesheet" href="/smart_inventory/assets/dashboard_background.css?v=4">
<style id="login-register-final-fix">
.login-btn,
.register-btn {
    width:100% !important;
    min-height:46px !important;
    display:flex !important;
    align-items:center !important;
    justify-content:center !important;
    border:0 !important;
    border-radius:10px !important;
    background:linear-gradient(135deg,#c06ceb 0%,#5df09d 100%) !important;
    color:#111827 !important;
    font-size:16px !important;
    font-weight:800 !important;
    text-decoration:none !important;
    box-shadow:0 10px 24px rgba(0,0,0,.24) !important;
    opacity:1 !important;
}
.login-btn:hover,
.register-btn:hover {
    background:linear-gradient(135deg,#c06ceb 0%,#5df09d 100%) !important;
    color:#111827 !important;
    filter:brightness(1.08) !important;
    transform:translateY(-1px) !important;
}
</style>
<style id="login-icon-visibility-fix">
.login-form-side .input-icon .ui-icon,
.login-form-side .toggle-password .ui-icon {
    filter: invert(1) brightness(2) !important;
    opacity: 1 !important;
}
.login-form-side .input-icon,
.login-form-side .toggle-password {
    color: #ffffff !important;
}
</style>
</head>


<body>


<div class="login-wrapper">


    <!-- =========================
         LEFT SIDE
    ========================= -->

    <div class="login-info">

        <div class="info-content">


            <div class="logo">
                <img class="brand-logo" src="/smart_inventory/assets/brand.svg" alt="Smart Inventory">
            </div>


            <h1>

                Smart Inventory
                <br>
                & Sales Management

            </h1>


            <p>

                Manage products, monitor inventory,
                process sales, generate reports, and
                analyze business performance from
                one centralized system.

            </p>


            <div class="features">


                <div class="feature">

                    <div class="feature-icon">
                        <img class="ui-icon" src="/smart_inventory/assets/icons/box.svg" alt="">
                    </div>

                    <span>
                        Real-time Inventory Monitoring
                    </span>

                </div>


                <div class="feature">

                    <div class="feature-icon">
                        <img class="ui-icon" src="/smart_inventory/assets/icons/money.svg" alt="">
                    </div>

                    <span>
                        Fast Sales and POS Processing
                    </span>

                </div>


                <div class="feature">

                    <div class="feature-icon">
                        <img class="ui-icon" src="/smart_inventory/assets/icons/chart.svg" alt="">
                    </div>

                    <span>
                        Business Analytics and Reports
                    </span>

                </div>


                <div class="feature">

                    <div class="feature-icon">
                        <img class="ui-icon" src="/smart_inventory/assets/icons/lock.svg" alt="">
                    </div>

                    <span>
                        Secure Role-Based Access
                    </span>

                </div>


            </div>


        </div>

    </div>


    <!-- =========================
         LOGIN FORM
    ========================= -->

    <div class="login-form-side">


        <div class="login-header">

            <div class="small-title">
                Welcome Back
            </div>


            <h2>
                Sign in to your account
            </h2>


            <p>

                Enter your login credentials
                to continue to the system.

            </p>

        </div>


        <!-- ERROR MESSAGE -->

        <?php if ($error): ?>

            <div class="error">

                <span>
                    ⚠️
                </span>

                <span>

                    <?php
                    echo htmlspecialchars(
                        $error
                    );
                    ?>

                </span>

            </div>

        <?php endif; ?>


        <!-- LOGIN FORM -->

        <form
            action="authenticate.php"
            method="POST"
        >


            <!-- USERNAME -->

            <div class="form-group">

                <label>
                    Username
                </label>


                <div class="input-wrapper">


                    <span class="input-icon">
                        <img class="ui-icon" src="/smart_inventory/assets/icons/user.svg" alt="">
                    </span>


                    <input
                        type="text"
                        name="username"
                        placeholder="Enter your username"
                        autocomplete="username"
                        required
                        autofocus
                    >


                </div>

            </div>


            <!-- PASSWORD -->

            <div class="form-group">

                <label>
                    Password
                </label>


                <div class="input-wrapper">


                    <span class="input-icon">
                        <img class="ui-icon" src="/smart_inventory/assets/icons/lock.svg" alt="">
                    </span>


                    <input
                        type="password"
                        name="password"
                        id="password"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        required
                    >


                    <button
                        type="button"
                        class="toggle-password"
                        onclick="togglePassword()"
                        aria-label="Show or hide password"
                    >

                        <img class="ui-icon" src="/smart_inventory/assets/icons/eye.svg" alt="">

                    </button>


                </div>

            </div>


            <!-- LOGIN BUTTON -->

            <button
                type="submit"
                class="login-btn"
            >

                Sign In

            </button>


        </form>

        <div class="register-area">
            Don't have an account yet?
            <a class="register-btn" href="/smart_inventory/register.php">
                Register
            </a>
        </div>


        <div class="login-footer">

            <strong>
                Smart Inventory and Sales Management System
            </strong>

            <br>

            with Business Analytics

        </div>


    </div>


</div>


<script>

/* =========================
   SHOW / HIDE PASSWORD
========================= */

function togglePassword() {

    const password =
        document.getElementById(
            "password"
        );


    if (
        password.type === "password"
    ) {

        password.type = "text";

    } else {

        password.type = "password";

    }

}

</script>


</body>

</html>