<?php
/**
 * ATZ Fitness Gym Management System
 * Login Page
 */


require_once 'includes/db.php';

/*
|--------------------------------------------------------------------------
| Google reCAPTCHA v2 Settings
|--------------------------------------------------------------------------
*/
define('RECAPTCHA_SITE_KEY', '6LcOd6EtAAAAAMTW5GFqZ3VqXy_7hO5O83xT9hSV');
define('RECAPTCHA_SECRET_KEY', '6LcOd6EtAAAAAPRJpRTxOBVyF9E2ax7juRokdfar');

function verify_recaptcha(string $response): bool
{
    if ($response === '' || RECAPTCHA_SECRET_KEY === 'YOUR_RECAPTCHA_SECRET_KEY') {
        return false;
    }

    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');

    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'secret'   => RECAPTCHA_SECRET_KEY,
            'response' => $response,
            'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $result = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($result === false || $curlError !== '') {
        return false;
    }

    $data = json_decode($result, true);

    return !empty($data['success']);
}



/*
|--------------------------------------------------------------------------
| Redirect Already Logged-In Users
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['user_id'])) {

    if ($_SESSION['role'] === 'Administrator') {
        header("Location: admin/index.php");
    } else {
        header("Location: staff/index.php");
    }

    exit();
}


/*
|--------------------------------------------------------------------------
| Messages
|--------------------------------------------------------------------------
*/

$error = $_GET['error'] ?? '';
$msg   = $_GET['msg'] ?? '';


/*
|--------------------------------------------------------------------------
| LOGIN SECURITY SETTINGS
|--------------------------------------------------------------------------
|
| 3 failed attempts = 30-second lock
|
*/

define('LOGIN_ATTEMPT_LIMIT', 3);
define('LOGIN_LOCKOUT_SECONDS', 30);


/*
|--------------------------------------------------------------------------
| Initialize Login Security Session
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['login_fail_count'])) {
    $_SESSION['login_fail_count'] = 0;
}

if (!isset($_SESSION['login_lockout_until'])) {
    $_SESSION['login_lockout_until'] = 0;
}


/*
|--------------------------------------------------------------------------
| Check If Lock Has Expired
|--------------------------------------------------------------------------
*/

if (
    $_SESSION['login_lockout_until'] > 0 &&
    $_SESSION['login_lockout_until'] <= time()
) {

    /*
    |--------------------------------------------------------------------------
    | Reset Failed Attempts
    |--------------------------------------------------------------------------
    */

    $_SESSION['login_fail_count'] = 0;
    $_SESSION['login_lockout_until'] = 0;
}


/*
|--------------------------------------------------------------------------
| Check Current Lock Status
|--------------------------------------------------------------------------
*/

$login_locked = (
    $_SESSION['login_lockout_until'] > time()
);

$lock_remaining = 0;


if ($login_locked) {

    $lock_remaining =
        $_SESSION['login_lockout_until'] - time();

    $error =
        "Too many failed login attempts. "
        . "Please wait "
        . $lock_remaining
        . " second(s) before trying again.";
}


/*
|--------------------------------------------------------------------------
| LOGIN PROCESS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | CSRF Protection
    |--------------------------------------------------------------------------
    */

    verify_csrf();

    /*
     |--------------------------------------------------------------------------
     | Google reCAPTCHA Verification
     |--------------------------------------------------------------------------
     */

    $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';

    if (!verify_recaptcha($recaptcha_response)) {

        $error =
            "Please complete the reCAPTCHA verification "
            . "before signing in.";

    } else {




    /*
    |--------------------------------------------------------------------------
    | Check 30-Second Lock
    |--------------------------------------------------------------------------
    */

    if (
        $_SESSION['login_lockout_until'] > time()
    ) {

        $remaining =
            $_SESSION['login_lockout_until'] - time();

        $error =
            "Too many failed login attempts. "
            . "Please wait "
            . $remaining
            . " second(s) before trying again.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | Get Username and Password
        |--------------------------------------------------------------------------
        */

        $username =
            sanitize(
                $_POST['username'] ?? ''
            );

        $password =
            $_POST['password'] ?? '';


        /*
        |--------------------------------------------------------------------------
        | Check Username and Password
        |--------------------------------------------------------------------------
        */

        if (
            !empty($username) &&
            !empty($password)
        ) {

            /*
            |--------------------------------------------------------------------------
            | Find User
            |--------------------------------------------------------------------------
            */

            $stmt = mysqli_prepare(
                $conn,
                "SELECT
                    id,
                    username,
                    password,
                    full_name,
                    role,
                    status,
                    force_password_change,
                    profile_picture
                 FROM users
                 WHERE username = ?
                 LIMIT 1"
            );


            /*
            |--------------------------------------------------------------------------
            | Prepare Statement Failed
            |--------------------------------------------------------------------------
            */

            if (!$stmt) {

                $error =
                    "Unable to process login. "
                    . "Please try again.";

            } else {

                mysqli_stmt_bind_param(
                    $stmt,
                    "s",
                    $username
                );


                mysqli_stmt_execute($stmt);


                $result =
                    mysqli_stmt_get_result($stmt);


                /*
                |--------------------------------------------------------------------------
                | User Found
                |--------------------------------------------------------------------------
                */

                if (
                    $user =
                    mysqli_fetch_assoc($result)
                ) {

                    /*
                    |--------------------------------------------------------------------------
                    | Check Account Status
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $user['status'] !== 'Active'
                    ) {

                        $error =
                            "Your account is inactive. "
                            . "Please contact the administrator.";

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Verify Password
                    |--------------------------------------------------------------------------
                    */

                    else if (
                        password_verify(
                            $password,
                            $user['password']
                        )
                    ) {

                        /*
                        |--------------------------------------------------------------------------
                        | SUCCESSFUL LOGIN
                        |--------------------------------------------------------------------------
                        */

                        session_regenerate_id(true);


                        /*
                        |--------------------------------------------------------------------------
                        | Store User Information
                        |--------------------------------------------------------------------------
                        */

                        $_SESSION['user_id'] =
                            $user['id'];

                        $_SESSION['username'] =
                            $user['username'];

                        $_SESSION['full_name'] =
                            $user['full_name'];

                        $_SESSION['role'] =
                            $user['role'];

                        $_SESSION['force_password_change'] =
                            $user['force_password_change'];

                        $_SESSION['profile_picture'] =
                            $user['profile_picture'];

                        $_SESSION['last_activity'] =
                            time();


                        /*
                        |--------------------------------------------------------------------------
                        | RESET 3-ATTEMPT LOCK
                        |--------------------------------------------------------------------------
                        */

                        $_SESSION['login_fail_count'] = 0;

                        $_SESSION['login_lockout_until'] = 0;


                        /*
                        |--------------------------------------------------------------------------
                        | Reset Existing Database Failed Login Records
                        |--------------------------------------------------------------------------
                        |
                        | Only run this if your db.php contains this function.
                        |
                        */

                        if (function_exists('reset_failed_login')) {

                            reset_failed_login(
                                $conn,
                                $user['username']
                            );
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Activity Log
                        |--------------------------------------------------------------------------
                        */

                        if (function_exists('log_activity')) {

                            log_activity(
                                $conn,
                                $user['id'],
                                $user['username'],
                                $user['role'],
                                'User Login',
                                'User successfully logged in'
                            );
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Force Password Change
                        |--------------------------------------------------------------------------
                        */

                        if (
                            $user['force_password_change'] == 1
                        ) {

                            header(
                                "Location: change_password.php?reason=forced"
                            );

                            exit();
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | Redirect Based On Role
                        |--------------------------------------------------------------------------
                        */

                        if (
                            $user['role'] === 'Administrator'
                        ) {

                            header(
                                "Location: admin/index.php"
                            );

                        } else {

                            header(
                                "Location: staff/index.php"
                            );
                        }

                        exit();
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | WRONG PASSWORD
                    |--------------------------------------------------------------------------
                    */

                    else {

                        /*
                        |--------------------------------------------------------------------------
                        | Increase Failed Attempts
                        |--------------------------------------------------------------------------
                        */

                        $_SESSION['login_fail_count']++;


                        /*
                        |--------------------------------------------------------------------------
                        | Optional Existing Database Failed Login
                        |--------------------------------------------------------------------------
                        */

                        if (function_exists('register_failed_login')) {

                            register_failed_login(
                                $conn,
                                $user['username']
                            );
                        }


                        /*
                        |--------------------------------------------------------------------------
                        | 3 ATTEMPTS REACHED
                        |--------------------------------------------------------------------------
                        */

                        if (
                            $_SESSION['login_fail_count']
                            >=
                            LOGIN_ATTEMPT_LIMIT
                        ) {

                            /*
                            |--------------------------------------------------------------------------
                            | Lock For 30 Seconds
                            |--------------------------------------------------------------------------
                            */

                            $_SESSION['login_lockout_until'] =
                                time() +
                                LOGIN_LOCKOUT_SECONDS;


                            $error =
                                "Too many failed login attempts. "
                                . "Login has been locked for 30 seconds.";

                        } else {

                            $attempts_left =
                                LOGIN_ATTEMPT_LIMIT -
                                $_SESSION['login_fail_count'];


                            $error =
                                "Invalid username or password. "
                                . $attempts_left .
                                " attempt(s) remaining.";
                        }
                    }

                }


                /*
                |--------------------------------------------------------------------------
                | USERNAME NOT FOUND
                |--------------------------------------------------------------------------
                */

                else {

                    /*
                    |--------------------------------------------------------------------------
                    | Increase Failed Attempts
                    |--------------------------------------------------------------------------
                    */

                    $_SESSION['login_fail_count']++;


                    /*
                    |--------------------------------------------------------------------------
                    | 3 ATTEMPTS REACHED
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $_SESSION['login_fail_count']
                        >=
                        LOGIN_ATTEMPT_LIMIT
                    ) {

                        /*
                        |--------------------------------------------------------------------------
                        | Lock For 30 Seconds
                        |--------------------------------------------------------------------------
                        */

                        $_SESSION['login_lockout_until'] =
                            time() +
                            LOGIN_LOCKOUT_SECONDS;


                        $error =
                            "Too many failed login attempts. "
                            . "Login has been locked for 30 seconds.";

                    } else {

                        $attempts_left =
                            LOGIN_ATTEMPT_LIMIT -
                            $_SESSION['login_fail_count'];


                        $error =
                            "Invalid username or password. "
                            . $attempts_left .
                            " attempt(s) remaining.";
                    }
                }


                mysqli_stmt_close($stmt);
            }

        } else {

            $error =
                "Please fill in all fields.";
        }


    }
    }
}


/*
|--------------------------------------------------------------------------
| Final Lock Status
|--------------------------------------------------------------------------
*/

$login_locked =
    $_SESSION['login_lockout_until'] > time();

$lock_remaining = 0;


if ($login_locked) {

    $lock_remaining =
        $_SESSION['login_lockout_until'] - time();
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
        ATZ Fitness - Staff & Admin Login
    </title>


    <!-- Bootstrap 5 -->
    <link
        href="assets/vendor/bootstrap/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <!-- Bootstrap Icons -->
    <link
        href="assets/vendor/bootstrap-icons/bootstrap-icons.css"
        rel="stylesheet"
    >


    <!-- ATZ Fitness CSS -->
    <link
        href="assets/css/style.css?v=4"
        rel="stylesheet"
    >

    <!-- Google reCAPTCHA v2 -->
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>

</head>


<body>

<div class="auth-shell">

    <div class="login-card">


        <!-- LOGIN HEADER -->

        <div class="login-header">

            <img
                src="assets/img/logo.jpg"
                alt="ATZ Fitness Logo"
                class="login-logo mb-2"
                width="96"
                height="96"
            >


            <h3 class="fw-bold mt-2 text-warning">
                ATZ FITNESS
            </h3>


            <p class="text-white-50 mb-0 small">
                Gym Management System Portal
            </p>

        </div>


        <!-- LOGIN BODY -->

        <div class="p-4 p-md-5">


            <!-- SUCCESS MESSAGE -->

            <?php if (!empty($msg)): ?>

                <div
                    class="alert alert-success d-flex align-items-center mb-4"
                    role="alert"
                >

                    <i
                        class="bi bi-check-circle-fill me-2 fs-5"
                    ></i>

                    <div>

                        <?php
                        echo sanitize($msg);
                        ?>

                    </div>

                </div>

            <?php endif; ?>


            <!-- ERROR MESSAGE -->

            <?php if (!empty($error)): ?>

                <div
                    class="alert alert-danger d-flex align-items-center mb-4"
                    role="alert"
                    id="loginError"
                >

                    <i
                        class="bi bi-exclamation-triangle-fill me-2 fs-5"
                    ></i>

                    <div>

                        <?php
                        echo sanitize($error);
                        ?>

                    </div>

                </div>

            <?php endif; ?>


            <!-- 30 SECOND LOCK MESSAGE -->

            <?php if ($login_locked): ?>

                <div
                    class="alert alert-warning text-center mb-4"
                    id="lockMessage"
                >

                    <i
                        class="bi bi-shield-lock-fill me-1"
                    ></i>

                    <strong>
                        Login temporarily locked
                    </strong>

                    <br>

                    Please wait

                    <strong>

                        <span id="lockCountdown">

                            <?php
                            echo (int)$lock_remaining;
                            ?>

                        </span>

                    </strong>

                    seconds.

                </div>

            <?php endif; ?>


            <!-- LOGIN FORM -->

            <form
                method="POST"
                action="login.php"
                id="loginForm"
            >

                <?php echo csrf_field(); ?>


                <!-- USERNAME -->

                <div class="mb-3">

                    <label
                        class="form-label fw-semibold"
                    >
                        Username
                    </label>


                    <div class="input-group">

                        <span
                            class="input-group-text bg-light"
                        >

                            <i class="bi bi-person"></i>

                        </span>


                        <input
                            type="text"
                            name="username"
                            class="form-control"
                            placeholder="Enter username"
                            autocomplete="username"
                            required
                            autofocus

                            <?php
                            echo $login_locked
                                ? 'disabled'
                                : '';
                            ?>
                        >

                    </div>

                </div>


                <!-- PASSWORD -->

                <div class="mb-3">

                    <label
                        class="form-label fw-semibold"
                    >
                        Password
                    </label>


                    <div class="input-group">

                        <span
                            class="input-group-text bg-light"
                        >

                            <i class="bi bi-lock"></i>

                        </span>


                        <input
                            type="password"
                            name="password"
                            id="loginPassword"
                            class="form-control"
                            placeholder="Enter password"
                            autocomplete="current-password"
                            required

                            <?php
                            echo $login_locked
                                ? 'disabled'
                                : '';
                            ?>
                        >


                        <span
                            class="input-group-text bg-light"
                            role="button"
                            id="toggleLoginPassword"
                            style="cursor:pointer;"
                            title="Show/Hide Password"
                        >

                            <i
                                class="bi bi-eye-slash"
                                id="toggleLoginPasswordIcon"
                            ></i>

                        </span>

                    </div>


                    <!-- FORGOT PASSWORD -->

                    <div class="text-end mt-1">

                        <a
                            href="forgot_password.php"
                            class="text-decoration-none small"
                        >

                            Forgot password?

                        </a>

                    </div>

                </div>


                <!-- GOOGLE reCAPTCHA v2 -->

                <?php if (!$login_locked): ?>
                    <div class="mb-3 d-flex justify-content-center">
                        <div
                            class="g-recaptcha"
                            data-sitekey="<?php echo htmlspecialchars(RECAPTCHA_SITE_KEY, ENT_QUOTES, 'UTF-8'); ?>"
                        ></div>
                    </div>
                <?php endif; ?>


                <!-- SIGN IN BUTTON -->

                <button
                    type="submit"
                    class="btn btn-warning w-100 fw-bold py-2 shadow-sm text-dark fs-5"
                    id="loginButton"

                    <?php
                    echo $login_locked
                        ? 'disabled'
                        : '';
                    ?>
                >

                    <i
                        class="bi bi-box-arrow-in-right me-1"
                    ></i>


                    <span id="loginButtonText">

                        Sign In

                    </span>

                </button>


            </form>


            <!-- REGISTER -->

            <div class="text-center mt-4">

                <a
                    href="register.php"
                    class="text-decoration-none small"
                >

                    <i
                        class="bi bi-person-plus me-1"
                    ></i>

                    Need an account? Register here

                </a>

            </div>


        </div>

    </div>

</div>


<!-- Bootstrap JS -->

<script
    src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"
></script>


<!-- PASSWORD SHOW / HIDE -->

<script>

(function () {

    const toggle =
        document.getElementById(
            'toggleLoginPassword'
        );


    const input =
        document.getElementById(
            'loginPassword'
        );


    const icon =
        document.getElementById(
            'toggleLoginPasswordIcon'
        );


    if (
        toggle &&
        input &&
        icon
    ) {

        toggle.addEventListener(
            'click',
            function () {

                const isPassword =
                    input.type === 'password';


                input.type =
                    isPassword
                        ? 'text'
                        : 'password';


                icon.classList.toggle(
                    'bi-eye-slash',
                    !isPassword
                );


                icon.classList.toggle(
                    'bi-eye',
                    isPassword
                );

            }
        );

    }

})();


/*
|--------------------------------------------------------------------------
| 30-Second Login Countdown
|--------------------------------------------------------------------------
*/

(function () {

    const countdown =
        document.getElementById(
            'lockCountdown'
        );


    const lockMessage =
        document.getElementById(
            'lockMessage'
        );


    const loginButton =
        document.getElementById(
            'loginButton'
        );


    const loginButtonText =
        document.getElementById(
            'loginButtonText'
        );


    /*
    |--------------------------------------------------------------------------
    | Stop If No Countdown
    |--------------------------------------------------------------------------
    */

    if (!countdown) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Get Starting Seconds
    |--------------------------------------------------------------------------
    */

    let seconds =
        parseInt(
            countdown.textContent,
            10
        );


    /*
    |--------------------------------------------------------------------------
    | Countdown Timer
    |--------------------------------------------------------------------------
    */

    const timer =
        setInterval(
            function () {

                seconds--;


                /*
                |--------------------------------------------------------------------------
                | Lock Finished
                |--------------------------------------------------------------------------
                */

                if (seconds <= 0) {

                    clearInterval(timer);


                    countdown.textContent =
                        '0';


                    /*
                    |--------------------------------------------------------------------------
                    | Show Ready Message
                    |--------------------------------------------------------------------------
                    */

                    if (lockMessage) {

                        lockMessage.innerHTML =
                            '<i class="bi bi-check-circle-fill me-1"></i>' +
                            '<strong>You can try logging in again.</strong>';

                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Reload Page
                    |--------------------------------------------------------------------------
                    */

                    setTimeout(
                        function () {

                            window.location.href =
                                'login.php';

                        },
                        1000
                    );


                    return;
                }


                /*
                |--------------------------------------------------------------------------
                | Update Countdown
                |--------------------------------------------------------------------------
                */

                countdown.textContent =
                    seconds;

            },
            1000
        );

})();

</script>


</body>

</html>