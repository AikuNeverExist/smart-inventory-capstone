<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');

session_start();

require_once __DIR__ . "/config/database.php";
require_once __DIR__ . "/includes/role_helpers.php";


/* =========================================================
   IF ALREADY LOGGED IN
========================================================= */

if (isset($_SESSION['user_id'])) {
    redirectToRoleDashboard($_SESSION['role'] ?? 'user');
}


/* =========================================================
   CSRF TOKEN
========================================================= */

if (
    !isset($_SESSION['register_csrf']) ||
    !is_string($_SESSION['register_csrf'])
) {

    $_SESSION['register_csrf'] =
        bin2hex(
            random_bytes(32)
        );
}


/* =========================================================
   CAPTCHA GENERATOR
========================================================= */

function generateRegisterCaptcha(): void
{
    $numberA = random_int(2, 9);
    $numberB = random_int(1, 9);

    $_SESSION['register_captcha_a'] =
        $numberA;

    $_SESSION['register_captcha_b'] =
        $numberB;

    $_SESSION['register_captcha_answer'] =
        $numberA + $numberB;
}


/*
 * On a normal page load / browser refresh, create a new CAPTCHA.
 * On POST, keep the existing CAPTCHA long enough to validate it.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    generateRegisterCaptcha();

} elseif (
    !isset($_SESSION['register_captcha_a']) ||
    !isset($_SESSION['register_captcha_b']) ||
    !isset($_SESSION['register_captcha_answer'])
) {

    generateRegisterCaptcha();
}


$error = "";
$success = "";

$name = "";
$username = "";


/* =========================================================
   REGISTER
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {

    $name =
        trim(
            $_POST['name']
            ?? ''
        );

    $username =
        trim(
            $_POST['username']
            ?? ''
        );

    $password =
        $_POST['password']
        ?? '';

    $confirmPassword =
        $_POST['confirm_password']
        ?? '';

    $captchaAnswer =
        trim(
            $_POST['captcha_answer']
            ?? ''
        );

    $csrfToken =
        $_POST['csrf_token']
        ?? '';

    /*
     * Honeypot field.
     * Normal users never see or fill this.
     */
    $website =
        trim(
            $_POST['website']
            ?? ''
        );


    /* =====================================================
       BASIC SESSION RATE LIMIT
    ===================================================== */

    $now = time();

    $lastAttempt =
        intval(
            $_SESSION['register_last_attempt']
            ?? 0
        );

    if (
        $lastAttempt > 0 &&
        ($now - $lastAttempt) < 3
    ) {

        $error =
            "Please wait a few seconds before trying again.";

    } elseif (
        !hash_equals(
            $_SESSION['register_csrf'],
            $csrfToken
        )
    ) {

        $error =
            "Invalid registration request. Please refresh the page and try again.";

    } elseif ($website !== '') {

        /*
         * Bot detected.
         * Do not explain the honeypot.
         */
        $error =
            "Registration could not be completed.";

    } elseif (
        $name === '' ||
        $username === '' ||
        $password === '' ||
        $confirmPassword === ''
    ) {

        $error =
            "Please complete all required fields.";

    } elseif (
        mb_strlen($name) < 2 ||
        mb_strlen($name) > 100
    ) {

        $error =
            "Name must be between 2 and 100 characters.";

    } elseif (
        !preg_match(
            '/^[A-Za-z0-9._-]{4,50}$/',
            $username
        )
    ) {

        $error =
            "Username must be 4 to 50 characters and can only contain letters, numbers, dots, underscores, and hyphens.";

    } elseif (
        strlen($password) < 8
    ) {

        $error =
            "Password must be at least 8 characters.";

    } elseif (
        $password !==
        $confirmPassword
    ) {

        $error =
            "Passwords do not match.";

    } elseif (
        !ctype_digit($captchaAnswer)
    ) {

        $error =
            "Please answer the CAPTCHA.";

    } elseif (
        intval($captchaAnswer) !==
        intval(
            $_SESSION['register_captcha_answer']
        )
    ) {

        $error =
            "Incorrect CAPTCHA answer.";

    } else {

        try {

            /*
             * Check duplicate username.
             */
            $stmt =
                $conn->prepare(
                    "SELECT id
                     FROM users
                     WHERE username = ?
                     LIMIT 1"
                );

            $stmt->bind_param(
                "s",
                $username
            );

            $stmt->execute();

            $result =
                $stmt->get_result();


            if (
                $result->num_rows > 0
            ) {

                $error =
                    "That username is already taken.";

            } else {

                /*
                 * Public registration ALWAYS creates
                 * a normal USER account.
                 */
                $role =
                    "user";


                $passwordHash =
                    password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );


                $stmt =
                    $conn->prepare(
                        "INSERT INTO users
                        (
                            name,
                            username,
                            password,
                            role
                        )
                        VALUES (?, ?, ?, ?)"
                    );


                $stmt->bind_param(
                    "ssss",
                    $name,
                    $username,
                    $passwordHash,
                    $role
                );


                $stmt->execute();


                $success =
                    "Account created successfully. You can now log in.";


                /*
                 * Clear form values.
                 */
                $name = "";
                $username = "";


                /*
                 * Rotate CSRF token and CAPTCHA
                 * after successful registration.
                 */
                $_SESSION['register_csrf'] =
                    bin2hex(
                        random_bytes(32)
                    );


                generateRegisterCaptcha();
            }

        } catch (Throwable $e) {

            $error =
                "Registration failed. Please try again.";
        }
    }


    /*
     * Record attempt time.
     */
    $_SESSION['register_last_attempt'] =
        time();


    /*
     * Always generate a new CAPTCHA
     * after an error.
     */
    if ($error !== '') {

        generateRegisterCaptcha();
    }
}



/*
 * Read the current CAPTCHA AFTER request processing.
 * This keeps the displayed numbers synchronized with the session answer.
 */
$captchaA = (int) ($_SESSION['register_captcha_a'] ?? 0);
$captchaB = (int) ($_SESSION['register_captcha_b'] ?? 0);

if ($captchaA <= 0 || $captchaB <= 0) {

    generateRegisterCaptcha();

    $captchaA = (int) $_SESSION['register_captcha_a'];
    $captchaB = (int) $_SESSION['register_captcha_b'];
}

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
    Register | Smart Inventory
</title>


<style>

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;
}


:root {
    --bg:
        #101223;

    --panel:
        #202036;

    --panel-2:
        #262740;

    --violet:
        #c06ceb;

    --mint:
        #5df09d;

    --white:
        #ffffff;

    --muted:
        #b9bed4;

    --border:
        #444667;
}


html,
body {
    min-height: 100%;
}


body {
    min-height: 100vh;

    padding:
        30px 16px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    color:
        var(--white);

    background:
        radial-gradient(
            circle at 15% 20%,
            rgba(192,108,235,.25),
            transparent 28%
        ),
        radial-gradient(
            circle at 85% 25%,
            rgba(93,240,157,.20),
            transparent 28%
        ),
        linear-gradient(
            135deg,
            #0e1020,
            #181a31,
            #101223
        );
}


.register-wrapper {
    width:
        min(
            100%,
            980px
        );

    display:
        grid;

    grid-template-columns:
        .85fr 1.15fr;

    overflow:
        hidden;

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            .12
        );

    border-radius:
        24px;

    background:
        rgba(
            32,
            32,
            54,
            .92
        );

    box-shadow:
        0 28px 80px
        rgba(
            0,
            0,
            0,
            .35
        );

    backdrop-filter:
        blur(18px);
}


.register-info {
    padding:
        50px 42px;

    display:
        flex;

    flex-direction:
        column;

    justify-content:
        center;

    background:
        linear-gradient(
            155deg,
            rgba(192,108,235,.18),
            rgba(38,39,64,.86),
            rgba(93,240,157,.10)
        );
}


.logo {
    width:
        54px;

    height:
        54px;

    margin-bottom:
        25px;

    display:
        grid;

    place-items:
        center;

    border-radius:
        15px;

    background:
        linear-gradient(
            135deg,
            var(--violet),
            var(--mint)
        );

    color:
        #17182c;

    font-size:
        18px;

    font-weight:
        900;
}


.register-info h1 {
    margin-bottom:
        14px;

    font-size:
        31px;

    line-height:
        1.12;
}


.register-info p {
    color:
        var(--muted);

    font-size:
        14px;

    line-height:
        1.7;
}


.role-note {
    margin-top:
        28px;

    padding:
        15px;

    border:
        1px solid
        rgba(
            255,
            255,
            255,
            .12
        );

    border-radius:
        12px;

    background:
        rgba(
            255,
            255,
            255,
            .045
        );

    color:
        #dce0f4;

    font-size:
        12px;

    line-height:
        1.6;
}


.register-form-side {
    padding:
        45px;

    background:
        rgba(
            38,
            39,
            64,
            .96
        );
}


.register-header {
    margin-bottom:
        25px;
}


.register-header small {
    display:
        block;

    margin-bottom:
        7px;

    color:
        var(--mint);

    font-size:
        11px;

    font-weight:
        bold;

    letter-spacing:
        1.3px;

    text-transform:
        uppercase;
}


.register-header h2 {
    margin-bottom:
        7px;

    font-size:
        27px;
}


.register-header p {
    color:
        var(--muted);

    font-size:
        13px;

    line-height:
        1.55;
}


.alert {
    margin-bottom:
        18px;

    padding:
        12px 14px;

    border-radius:
        10px;

    font-size:
        13px;

    line-height:
        1.5;
}


.alert-error {
    background:
        rgba(
            192,
            108,
            235,
            .10
        );

    border:
        1px solid
        rgba(
            192,
            108,
            235,
            .45
        );

    color:
        #f4e5ff;
}


.alert-success {
    background:
        rgba(
            93,
            240,
            157,
            .10
        );

    border:
        1px solid
        rgba(
            93,
            240,
            157,
            .45
        );

    color:
        #dffff0;
}


.form-group {
    margin-bottom:
        17px;
}


.form-group label {
    display:
        block;

    margin-bottom:
        7px;

    color:
        #ffffff;

    font-size:
        12px;

    font-weight:
        bold;
}


.form-group input {
    width:
        100%;

    min-height:
        45px;

    padding:
        11px 13px;

    border:
        1px solid
        var(--border);

    border-radius:
        10px;

    outline:
        none;

    background:
        var(--panel);

    color:
        #ffffff;

    font-size:
        14px;

    transition:
        .2s;
}


.form-group input::placeholder {
    color:
        #858ba8;
}


.form-group input:focus {
    border-color:
        var(--mint);

    box-shadow:
        0 0 0 3px
        rgba(
            93,
            240,
            157,
            .10
        );
}


.password-grid {
    display:
        grid;

    grid-template-columns:
        1fr 1fr;

    gap:
        13px;
}


.captcha-box {
    margin-bottom:
        18px;

    padding:
        16px;

    display:
        grid;

    grid-template-columns:
        auto 1fr;

    gap:
        14px;

    align-items:
        center;

    border:
        1px solid
        var(--border);

    border-radius:
        12px;

    background:
        #202036;
}


.captcha-question {
    min-width:
        110px;

    padding:
        11px 13px;

    border-radius:
        9px;

    text-align:
        center;

    color:
        #ffffff;

    font-size:
        17px;

    font-weight:
        800;

    background:
        linear-gradient(
            135deg,
            rgba(192,108,235,.25),
            rgba(93,240,157,.16)
        );
}


.captcha-box input {
    width:
        100%;

    min-height:
        43px;

    padding:
        10px 12px;

    border:
        1px solid
        var(--border);

    border-radius:
        9px;

    outline:
        none;

    background:
        #18192d;

    color:
        #ffffff;
}


.captcha-box input:focus {
    border-color:
        var(--violet);
}


.register-button {
    width:
        100%;

    min-height:
        48px;

    border:
        none;

    border-radius:
        11px;

    cursor:
        pointer;

    color:
        #17182c;

    font-size:
        14px;

    font-weight:
        800;

    background:
        linear-gradient(
            135deg,
            var(--violet),
            var(--mint)
        );

    transition:
        .2s;
}


.register-button:hover {
    transform:
        translateY(-1px);

    filter:
        brightness(1.07);
}


.login-link {
    margin-top:
        20px;

    text-align:
        center;

    color:
        var(--muted);

    font-size:
        12px;
}


.login-link a {
    color:
        var(--mint);

    font-weight:
        bold;

    text-decoration:
        none;
}


.login-link a:hover {
    text-decoration:
        underline;
}


/*
 * Honeypot.
 * Real users should never see this.
 */
.website-field {
    position:
        absolute !important;

    left:
        -99999px !important;

    width:
        1px !important;

    height:
        1px !important;

    overflow:
        hidden !important;
}


@media (
    max-width: 800px
) {

    .register-wrapper {
        grid-template-columns:
            1fr;
    }


    .register-info {
        padding:
            34px 28px;
    }


    .register-form-side {
        padding:
            32px 25px;
    }
}


@media (
    max-width: 520px
) {

    .password-grid {
        grid-template-columns:
            1fr;
    }


    .captcha-box {
        grid-template-columns:
            1fr;
    }


    .captcha-question {
        width:
            100%;
    }
}

</style>

</head>


<body>


<div class="register-wrapper">


<!-- ================================
     LEFT SIDE
================================ -->

<section class="register-info">


<div class="logo">
    SI
</div>


<h1>
    Smart Inventory
</h1>


<p>
    Create a normal user account.
    Staff and Supplier permissions
    can only be assigned by an administrator.
</p>


<div class="role-note">

    <strong>
        Registration security
    </strong>

    <br><br>

    All public registrations are saved as
    <strong>USER</strong> accounts.

    A registered user cannot choose
    Admin, Staff, or Supplier access.

</div>


</section>


<!-- ================================
     REGISTER FORM
================================ -->

<section class="register-form-side">


<div class="register-header">

<small>
    New Account
</small>

<h2>
    Register
</h2>

<p>
    Enter your information and complete
    the CAPTCHA verification.
</p>

</div>


<?php if (
    $error !== ''
): ?>

<div class="alert alert-error">

    <?php
    echo htmlspecialchars(
        $error
    );
    ?>

</div>

<?php endif; ?>


<?php if (
    $success !== ''
): ?>

<div class="alert alert-success">

    <?php
    echo htmlspecialchars(
        $success
    );
    ?>

</div>

<?php endif; ?>


<form
    method="POST"
    autocomplete="off"
>


<input
    type="hidden"
    name="csrf_token"
    value="<?php
        echo htmlspecialchars(
            $_SESSION['register_csrf']
        );
    ?>"
>


<!-- HONEYPOT -->

<div class="website-field">

<label>
    Website
</label>

<input
    type="text"
    name="website"
    value=""
    tabindex="-1"
    autocomplete="off"
>

</div>


<!-- NAME -->

<div class="form-group">

<label for="name">
    Full Name
</label>

<input
    type="text"
    id="name"
    name="name"
    maxlength="100"
    placeholder="Enter your full name"
    value="<?php
        echo htmlspecialchars(
            $name
        );
    ?>"
    required
>

</div>


<!-- USERNAME -->

<div class="form-group">

<label for="username">
    Username
</label>

<input
    type="text"
    id="username"
    name="username"
    minlength="4"
    maxlength="50"
    placeholder="Choose a username"
    value="<?php
        echo htmlspecialchars(
            $username
        );
    ?>"
    required
>

</div>


<!-- PASSWORDS -->

<div class="password-grid">


<div class="form-group">

<label for="password">
    Password
</label>

<input
    type="password"
    id="password"
    name="password"
    minlength="8"
    placeholder="At least 8 characters"
    required
>

</div>


<div class="form-group">

<label for="confirm_password">
    Confirm Password
</label>

<input
    type="password"
    id="confirm_password"
    name="confirm_password"
    minlength="8"
    placeholder="Repeat your password"
    required
>

</div>


</div>


<!-- CAPTCHA -->

<div class="captcha-box">


<div class="captcha-question">

    <?php
    echo $captchaA;
    ?>

    +

    <?php
    echo $captchaB;
    ?>

    = ?

</div>


<input
    type="number"
    name="captcha_answer"
    min="0"
    inputmode="numeric"
    placeholder="CAPTCHA answer"
    required
>


</div>


<button
    type="submit"
    class="register-button"
>
    Create User Account
</button>


</form>


<div class="login-link">

Already have an account?

<a
    href="/smart_inventory/login.php"
>
    Login
</a>

</div>


</section>


</div>


</body>

</html>
