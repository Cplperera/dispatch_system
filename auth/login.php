<?php
/**
 * ==========================================================================
 * LOGIN CONTROLLER & PROTOCOL TERMINAL
 * ==========================================================================
 */
session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';

$error = '';

// Handle form submission
if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // Secure parameterized query to fetch user details
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND is_deleted = 0 LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user) {
        // Validate password against salted hash
        if (password_verify($password, $user['password'])) {
            // Establish session contexts
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['section'] = $user['section'];
            $_SESSION['desk_id'] = $user['desk_id'];
            $_SESSION['status'] = $user['status'];

            // Map user-specific system permissions
            $_SESSION['permissions'] = [];
            $perm_stmt = $conn->prepare("SELECT permission_code, is_allowed FROM user_permissions WHERE user_id = ?");
            $perm_stmt->bind_param("i", $user['id']);
            $perm_stmt->execute();
            $result = $perm_stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $_SESSION['permissions'][$row['permission_code']] = $row['is_allowed'];
            }

            // Redirect to terminal dashboard
            header("Location: " . BASE_URL . "letters/dashboard/dashboard.php");
            exit;
        } else {
            $error = "Invalid password!";
        }
    } else {
        $error = "User not found or inactive!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    
    <link rel="stylesheet" href="../Assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../Assets/css/all.min.css">

    <style>
        /* ==========================================================================
            COLOR VARIANTS & DESIGN TOKENS
           ========================================================================== */
        :root {
            --bg-body: #0B1437;
            --card-bg: #111C44;
            --accent: #7551FF;
            --text-main: #FFFFFF;
            --text-muted: #A3AED0;
            --text-sub: #696970;
            --error: #EE5D50;
            --input-bg: rgba(255, 255, 255, 0.05);
            --border: rgba(255, 255, 255, 0.1);
        }

        /* ==========================================================================
            GLOBAL RESETS & TYPOGRAPHY
           ========================================================================== */
        * {
            margin: 0; 
            padding: 0; 
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background-color: var(--bg-body);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-image: radial-gradient(circle at 20% 30%, rgba(117, 81, 255, 0.15) 0%, transparent 40%),
                              radial-gradient(circle at 80% 70%, rgba(67, 24, 255, 0.1) 0%, transparent 40%);
        }

        /* ==========================================================================
            LOGIN CARD CONTAINER
           ========================================================================== */
        .login-container {
            width: 100%;
            max-width: 420px;
            background: var(--card-bg);
            padding: 50px 40px;
            border-radius: 30px;
            border: 1px solid var(--border);
            box-shadow: 0px 40px 80px rgba(0, 0, 0, 0.5);
            text-align: center;
        }

        .brand-icon {
            font-size: 3rem;
            color: var(--accent);
            margin-bottom: 20px;
        }

        h2 {
            color: var(--text-main);
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 10px;
            letter-spacing: -1px;
        }

        h6 {
            margin-top: 10px;
            font-size: 1rem;
            color: var(--text-sub);
            font-weight: 600;
            letter-spacing: 1px;
        }
        p.subtitle {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 35px;
            font-weight: 500;
        }

        /* ==========================================================================
            FORM ELEMENTS & BUTTONS
           ========================================================================== */
        .error {
            background: rgba(238, 93, 80, 0.1);
            color: var(--error);
            padding: 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 25px;
            border: 1px solid rgba(238, 93, 80, 0.2);
        }

        .input-group {
            position: relative;
            margin-bottom: 20px;
            text-align: left;
        }

        .input-group i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 1.2rem;
            z-index: 10;
        }

        input {
            width: 100%;
            background: var(--input-bg);
            border: 1px solid var(--border);
            padding: 16px 16px 16px 50px;
            border-radius: 15px;
            color: white;
            font-size: 0.95rem;
            font-weight: 500;
            transition: 0.3s;
            outline: none;
        }

        input:focus {
            border-color: var(--accent);
            background: rgba(117, 81, 255, 0.05);
            box-shadow: 0 0 0 4px rgba(117, 81, 255, 0.1);
        }

        button[type="submit"] {
            width: 100%;
            background: var(--accent);
            color: white;
            border: none;
            padding: 16px;
            border-radius: 15px;
            font-size: 1rem;
            font-weight: 800;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 10px;
            box-shadow: 0px 10px 20px rgba(117, 81, 255, 0.3);
        }

        button[type="submit"]:hover {
            transform: translateY(-2px);
            box-shadow: 0px 15px 25px rgba(117, 81, 255, 0.4);
            filter: brightness(1.1);
        }

        .footer-text {
            margin-top: 30px;
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        /* ==========================================================================
            HELP PROTOCOL MODAL & INHERITANCE OVERRIDES
           ========================================================================== */
        .info-trigger-btn {
            background: transparent !important;
            border: none !important;
            color: var(--text-muted) !important;
            font-size: 0.85rem !important;
            font-weight: 600 !important;
            cursor: pointer;
            box-shadow: none !important;
            margin: 0 !important;
            padding: 0 !important;
            width: auto !important;
            transition: color 0.2s ease;
        }

        .info-trigger-btn:hover {
            color: var(--accent) !important;
        }

        .registry-modal-container {
            background-color: var(--card-bg) !important;
            background: var(--card-bg) !important;
            border: 1px solid var(--border) !important;
            border-radius: 24px !important;
            padding: 10px !important;
        }

        .custom-popup-header {
            background-color: transparent !important;
            border-bottom: none !important;
            display: flex !important;
            justify-content: space-between !important;
            align-items: center !important;
            padding-bottom: 0 !important;
        }

        .custom-popup-body {
            background-color: transparent !important;
        }

        .custom-popup-footer {
            background-color: transparent !important;
            border-top: none !important;
            padding-top: 0 !important;
        }

        .custom-popup-body h6 {
            color: #FFFFFF !important;
        }

        .popup-muted-text {
            color: #A3AED0 !important;
        }

        .popup-muted-text strong {
            color: #FFFFFF !important;
            font-weight: 700 !important;
        }

        .text-accent {
            color: var(--accent) !important;
        }

        .close-popup-x {
            background: transparent !important;
            border: none !important;
            color: var(--text-muted) !important;
            font-size: 1.2rem !important;
            cursor: pointer;
            box-shadow: none !important;
            margin: 0 !important;
            padding: 5px !important;
            width: auto !important;
        }

        .close-popup-x:hover {
            color: #FFFFFF !important;
        }

        .ack-popup-btn {
            background: var(--accent) !important;
            color: #FFFFFF !important;
            border: none !important;
            border-radius: 14px !important;
            font-size: 0.9rem !important;
            box-shadow: 0px 8px 16px rgba(117, 81, 255, 0.2) !important;
            margin: 0 !important;
        }

        .ack-popup-btn:hover {
            filter: brightness(1.1) !important;
            color: #FFFFFF !important;
        }
    </style>
</head>
<body>

<div class="login-container">
    <div class="brand-icon">
        <i class="fa-solid fa-circle-user"></i>
    </div>
    
    <h2>Welcome Back</h2>
    <h6>Integrated Document & Correspondence Management System <br><strong>(IDCMS) </h6>
    
    <p class="subtitle">Enter your terminal credentials to continue</p>
    

    <?php if ($error): ?>
        <div class='error'>
            <i class='fa-solid fa-circle-exclamation'></i> <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <form method="post">
        <div class="input-group">
            <i class="fa-solid fa-user"></i>
            <input type="text" name="username" placeholder="Username" required autocomplete="off">
        </div>

        <div class="input-group">
            <i class="fa-solid fa-lock"></i>
            <input type="password" name="password" placeholder="Password" required>
        </div>

        <button type="submit" name="login">Sign In to Terminal</button>
    </form>

    <div class="mt-4">
        <button type="button" class="info-trigger-btn" data-bs-toggle="modal" data-bs-target="#registryInfoModal">
            <i class="fa-solid fa-circle-info me-1"></i> New user account assistance
        </button>
    </div>

    <div class="footer-text">
        SECURE GATEWAY | DISPATCH PRO v1.0
    </div>
</div>

<div class="modal fade" id="registryInfoModal" tabindex="-1" aria-labelledby="registryInfoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content registry-modal-container shadow-lg">
            
            <div class="modal-header custom-popup-header">
                <h5 class="modal-title fw-bold text-white d-flex align-items-center" id="registryInfoModalLabel">
                    <i class="fa-solid fa-shield-halved text-accent me-2"></i> Registry Access Protocol
                </h5>
                <button type="button" class="close-popup-x" data-bs-dismiss="modal" aria-label="Close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="modal-body custom-popup-body py-4">
                <div class="d-flex align-items-start mb-3">
                    <i class="fa-solid fa-folder-closed text-accent mt-1 me-3 fs-5"></i>
                    <div>
                        <h6 class="text-white fw-bold mb-1">Secured System Domain</h6>
                        <p class="popup-muted-text small m-0">To protect official record pathways and system data integrity, open self-registration protocols are completely restricted.</p>
                    </div>
                </div>
                <div class="d-flex align-items-start">
                    <i class="fa-solid fa-id-card text-accent mt-1 me-3 fs-5"></i>
                    <div>
                        <h6 class="text-white fw-bold mb-1">Obtaining Credentials</h6>
                        <p class="popup-muted-text small m-0">Please route an official account creation request through your assigned <strong>Section Head</strong> or desk officer to authorize terminal access permissions.</p>
                    </div>
                </div>
            </div>

            <div class="modal-footer custom-popup-footer">
                <button type="button" class="btn ack-popup-btn w-100 py-2.5 fw-bold" data-bs-dismiss="modal">Acknowledge</button>
            </div>

        </div>
    </div>
</div>

<script src="../Assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>