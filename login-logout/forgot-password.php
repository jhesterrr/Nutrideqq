<?php
require_once dirname(__DIR__) . '/session_bootstrap.php';

require_once __DIR__ . '/../database.php';

$message = '';
$error = '';
$email = '';
$step = 1; // 1: Email entry, 2: Recovery Key Entry, 3: Reset Password

try {
    $db = new Database();
    $conn = $db->getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = trim($_POST['email'] ?? '');
        $action = $_POST['action'] ?? '';

        // Step 1: Verify Email
        if ($action === 'verify_email') {
            $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ? AND status = 'active'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $_SESSION['reset_user_id'] = $user['id'];
                $_SESSION['reset_user_email'] = $email;
                $_SESSION['reset_user_name'] = $user['name'];
                $step = 2;
            } else {
                $error = "No active account found with this email address.";
            }
        }

        // Step 2: Verify Recovery Key
        elseif ($action === 'verify_key') {
            $key = trim($_POST['recovery_key'] ?? '');
            $user_id = $_SESSION['reset_user_id'];

            $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND recovery_key = ?");
            $stmt->execute([$user_id, $key]);
            
            if ($stmt->fetch()) {
                $step = 3;
            } else {
                $error = "Invalid recovery key. Please try again.";
                $step = 2;
            }
        }

        // Step 3: Final Password Reset
        elseif ($action === 'reset_password') {
            $pass = $_POST['new_password'] ?? '';
            $conf = $_POST['confirm_password'] ?? '';
            $user_id = $_SESSION['reset_user_id'];

            if (strlen($pass) < 8) {
                $error = "Password must be at least 8 characters long.";
                $step = 3;
            } elseif ($pass !== $conf) {
                $error = "Passwords do not match.";
                $step = 3;
            } else {
                $hashed = password_hash($pass, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$hashed, $user_id]);
                
                $_SESSION['success_message'] = "Password reset successfully! You can now log in.";
                header("Location: NutriDeqN-Login.php");
                exit();
            }
        }
    }
} catch (Exception $e) {
    $error = "Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#059669">
    <title>Account Recovery - NutriDeq Elite</title>
    <link rel="icon" type="image/png" href="../assets/img/logo.png">
    <link rel="stylesheet" href="../css/base.css?v=205">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <style>
        :root {
            --primary: #10b981;
            --primary-dark: #059669;
            --emerald-soft: rgba(16, 185, 129, 0.2);
            --text-main: #1e293b;
            --text-muted: #64748b;
            --bg-body: #f8fafc;
            --glass-bg: rgba(255, 255, 255, 0.75);
            --glass-border: rgba(255, 255, 255, 1);
            --bounce: cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        body {
            margin: 0; padding: 0; min-height: var(--app-height-dvh);
            background-color: var(--bg-body);
            font-family: 'Inter', sans-serif;
            overflow: hidden;
            display: flex; align-items: center; justify-content: center;
        }

        /* --- INTERACTIVE DYNAMIC BACKDROP --- */
        .backdrop {
            position: fixed;
            top:0; left:0; width:100%; height:100%;
            z-index: -1;
            pointer-events: none;
        }

        #particle-canvas {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            opacity: 0.4;
        }

        .bg-grid {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background-image: radial-gradient(rgba(16, 185, 129, 0.08) 1.5px, transparent 1.5px);
            background-size: 40px 40px;
        }

        .interactive-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(100px);
            opacity: 0.7;
            will-change: transform;
        }

        .orb-1 { width: 600px; height: 600px; background: rgba(5, 150, 105, 0.18); top: -15%; left: -10%; }
        .orb-2 { width: 500px; height: 500px; background: rgba(16, 185, 129, 0.15); bottom: 5%; right: -10%; }
        .orb-3 { width: 450px; height: 450px; background: rgba(5, 150, 105, 0.1); top: 35%; left: 25%; }

        /* --- THE ELITE GLASS CARD --- */
        .tilt-wrapper {
            perspective: 2000px;
            z-index: 10;
            padding: 20px;
            width: 100%;
            display: flex; justify-content: center;
        }

        .auth-card {
            width: 100%;
            max-width: 480px;
            background: var(--glass-bg);
            backdrop-filter: blur(40px); -webkit-backdrop-filter: blur(40px);
            border: 2px solid var(--glass-border);
            border-radius: 40px;
            padding: 3.5rem 3rem;
            position: relative;
            transform-style: preserve-3d;
            box-shadow: 0 40px 100px rgba(0,0,0,0.06), 
                        inset 0 0 0 1px rgba(255,255,255,0.8);
            opacity: 0;
            transform: scale(0.96) translateY(20px);
            filter: blur(4px);
        }

        .glass-reflection {
            position: absolute;
            top: -100%; left: -100%; width: 300%; height: 300%;
            background: radial-gradient(circle at center, rgba(255,255,255,0.3) 0%, transparent 60%);
            pointer-events: none;
            z-index: 1;
            opacity: 0;
            transition: opacity 0.5s;
        }

        .card-content { position: relative; z-index: 2; transform: translateZ(50px); }

        .back-home-link {
            display: inline-flex; align-items: center; gap: 8px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 25px;
            transition: all 0.3s;
            opacity: 1 !important;
        }
        .back-home-link:hover { color: var(--primary-dark); transform: translateX(-4px); }

        .auth-header { text-align: center; margin-bottom: 2.5rem; }
        .logo-box {
            width: 60px; height: 60px;
            background: white;
            border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.2rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.04);
        }
        .logo-box img { width: 35px; }

        .auth-header h1 { font-family: 'Outfit'; font-size: 1.8rem; color: var(--text-main); margin-bottom: 0.6rem; letter-spacing: -1px; }
        .auth-header p { color: var(--text-muted); font-size: 0.95rem; font-weight: 500; }

        /* Form Engine */
        .form-group { position: relative; margin-bottom: 1.8rem; }
        
        .form-group input {
            width: 100%;
            padding: 1.4rem 1rem 0.6rem;
            font-size: 1rem;
            font-family: inherit;
            background: transparent;
            border: none;
            border-bottom: 2px solid #e2e8f0;
            color: var(--text-main);
            transition: border-color 0.4s var(--bounce);
            outline: none;
        }

        .form-group label {
            position: absolute;
            top: 1.4rem; left: 1rem;
            font-size: 1rem;
            color: var(--text-muted);
            pointer-events: none;
            transition: all 0.3s var(--bounce);
            font-weight: 500;
        }

        .form-group input:focus ~ label,
        .form-group input:valid ~ label {
            top: 0;
            left: 0;
            font-size: 0.85rem;
            font-weight: 800;
            color: var(--primary-dark);
        }

        .input-accent {
            position: absolute;
            bottom: 0; left: 50%; width: 0; height: 2px;
            background: var(--primary);
            transition: all 0.4s var(--bounce);
            box-shadow: 0 0 10px rgba(16, 185, 129, 0.4);
        }
        .form-group input:focus ~ .input-accent { width: 100%; left: 0; }

        .btn-elite {
            width: 100%;
            padding: 1.2rem;
            background: var(--primary-dark);
            color: white;
            border: none;
            border-radius: 16px;
            font-size: 1.1rem;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.4s var(--bounce);
            box-shadow: 0 10px 30px rgba(5, 150, 105, 0.2);
            margin-top: 1rem;
        }
        .btn-elite:hover { transform: translateY(-4px) scale(1.02); box-shadow: 0 20px 45px rgba(5, 150, 105, 0.3); background: var(--primary); }

        .back-link {
            display: block;
            margin-top: 2rem;
            text-align: center;
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: color 0.3s;
        }
        .back-link:hover { color: var(--primary-dark); }

        .alert { padding: 1rem; border-radius: 12px; margin-bottom: 2rem; font-size: 0.9rem; font-weight: 600; text-align: left; display: flex; align-items: center; gap: 0.8rem; }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-error { background: #fff1f2; color: #ef4444; border-left: 4px solid #ef4444; }

        .stagger { opacity: 0; transform: translateY(15px); }

        @media (max-width: 480px) {
            .auth-card { padding: 3rem 1.5rem; border-radius: 30px; }
        }
    </style>
</head>

<body>
    <div class="backdrop">
        <div class="bg-grid"></div>
        <canvas id="particle-canvas"></canvas>
        <div class="interactive-orb orb-1"></div>
        <div class="interactive-orb orb-2"></div>
        <div class="interactive-orb orb-3"></div>
    </div>

    <div class="tilt-wrapper">
        <div class="auth-card" id="auth-card">
            <div class="glass-reflection" id="glass-reflection"></div>
            
            <div class="card-content">
                <a href="../index.php" class="back-home-link stagger">
                    <i class="fas fa-chevron-left"></i> Back to Home
                </a>

                <?php if ($step == 1): ?>
                    <div class="auth-header">
                        <div class="logo-box stagger">
                            <svg viewBox="0 0 240 240" style="width: 45px; height: 45px; overflow: visible;">
                                <path d="M120 40 L165 90 L120 135 L75 90 Z" fill="#10b981"/>
                                <path d="M75 90 L120 135 L100 190 L55 135 Z" fill="#059669"/>
                                <path d="M165 90 L185 135 L140 190 L120 135 Z" fill="#34d399"/>
                            </svg>
                        </div>
                        <h1 class="stagger">Forgot Password?</h1>
                        <p class="stagger">Enter your email address and we'll help you reset your password.</p>
                    </div>

                    <?php if ($error): ?><div class="alert alert-error stagger"><i class="fas fa-circle-exclamation"></i> <?php echo $error; ?></div><?php endif; ?>

                    <form method="POST" class="stagger">
                        <input type="hidden" name="action" value="verify_email">
                        <div class="form-group">
                            <input type="email" name="email" id="email" required value="<?php echo htmlspecialchars($email); ?>">
                            <label for="email">Email Address</label>
                            <div class="input-accent"></div>
                        </div>
                        <button type="submit" class="btn-elite">Reset Password</button>
                    </form>

                <?php elseif ($step == 2): ?>
                    <div class="auth-header">
                        <div class="logo-box stagger">
                            <svg viewBox="0 0 240 240" style="width: 45px; height: 45px; overflow: visible;">
                                <path d="M120 40 L165 90 L120 135 L75 90 Z" fill="#10b981"/>
                                <path d="M75 90 L120 135 L100 190 L55 135 Z" fill="#059669"/>
                                <path d="M165 90 L185 135 L140 190 L120 135 Z" fill="#34d399"/>
                            </svg>
                        </div>
                        <h1 class="stagger">Enter Recovery Key</h1>
                        <p class="stagger">Hi <strong><?php echo htmlspecialchars($_SESSION['reset_user_name']); ?></strong>, please enter the 12-character recovery key provided during registration.</p>
                    </div>

                    <?php if ($error): ?><div class="alert alert-error stagger"><i class="fas fa-circle-exclamation"></i> <?php echo $error; ?></div><?php endif; ?>

                    <form method="POST" class="stagger">
                        <input type="hidden" name="action" value="verify_key">
                        <div class="form-group">
                            <input type="text" name="recovery_key" id="recovery_key" required pattern="ND-[A-Z0-9]{4}-[A-Z0-9]{4}">
                            <label for="recovery_key">Recovery Key (ND-XXXX-XXXX)</label>
                            <div class="input-accent"></div>
                        </div>
                        <button type="submit" class="btn-elite">Verify Key</button>
                    </form>

                <?php elseif ($step == 3): ?>
                    <div class="auth-header">
                        <div class="logo-box stagger">
                            <svg viewBox="0 0 240 240" style="width: 45px; height: 45px; overflow: visible;">
                                <path d="M120 40 L165 90 L120 135 L75 90 Z" fill="#10b981"/>
                                <path d="M75 90 L120 135 L100 190 L55 135 Z" fill="#059669"/>
                                <path d="M165 90 L185 135 L140 190 L120 135 Z" fill="#34d399"/>
                            </svg>
                        </div>
                        <h1 class="stagger">Create New Password</h1>
                        <p class="stagger">Choose a new secure password for your account.</p>
                    </div>

                    <?php if ($error): ?><div class="alert alert-error stagger"><i class="fas fa-circle-exclamation"></i> <?php echo $error; ?></div><?php endif; ?>

                    <form method="POST" class="stagger">
                        <input type="hidden" name="action" value="reset_password">
                        <div class="form-group">
                            <input type="password" name="new_password" id="new_password" required minlength="8">
                            <label for="new_password">New Password (at least 8 characters)</label>
                            <div class="input-accent"></div>
                        </div>
                        <div class="form-group">
                            <input type="password" name="confirm_password" id="confirm_password" required minlength="8">
                            <label for="confirm_password">Confirm Password</label>
                            <div class="input-accent"></div>
                        </div>
                        <button type="submit" class="btn-elite">Save New Password</button>
                    </form>
                <?php endif; ?>

                <a href="NutriDeqN-Login.php" class="back-link stagger"><i class="fas fa-arrow-left"></i> Back to Log In</a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const authCard = document.getElementById('auth-card');
            const glassReflection = document.getElementById('glass-reflection');
            const orbs = document.querySelectorAll('.interactive-orb');

            // --- 1. PARTICLE SYSTEM ---
            const canvas = document.getElementById('particle-canvas');
            const ctx = canvas.getContext('2d');
            let particles = [];
            let width, height;

            const resize = () => {
                width = window.innerWidth;
                height = window.innerHeight;
                canvas.width = width;
                canvas.height = height;
            };

            window.addEventListener('resize', resize);
            resize();

            class Particle {
                constructor() {
                    this.reset();
                }
                reset() {
                    this.x = Math.random() * width;
                    this.y = Math.random() * height;
                    this.vx = (Math.random() - 0.5) * 0.5;
                    this.vy = (Math.random() - 0.5) * 0.5;
                    this.radius = Math.random() * 2 + 1;
                    this.alpha = Math.random() * 0.5 + 0.2;
                }
                update() {
                    this.x += this.vx;
                    this.y += this.vy;
                    if (this.x < 0 || this.x > width) this.vx *= -1;
                    if (this.y < 0 || this.y > height) this.vy *= -1;
                }
                draw() {
                    ctx.beginPath();
                    ctx.arc(this.x, this.y, this.radius, 0, Math.PI * 2);
                    ctx.fillStyle = `rgba(16, 185, 129, ${this.alpha})`;
                    ctx.fill();
                }
            }

            for (let i = 0; i < 40; i++) particles.push(new Particle());

            function animate() {
                ctx.clearRect(0, 0, width, height);
                particles.forEach(p => {
                    p.update();
                    p.draw();
                });
                
                for (let i = 0; i < particles.length; i++) {
                    for (let j = i + 1; j < particles.length; j++) {
                        const dist = Math.hypot(particles[i].x - particles[j].x, particles[i].y - particles[j].y);
                        if (dist < 100) {
                            ctx.beginPath();
                            ctx.moveTo(particles[i].x, particles[i].y);
                            ctx.lineTo(particles[j].x, particles[j].y);
                            ctx.strokeStyle = `rgba(16, 185, 129, ${0.1 * (1 - dist / 100)})`;
                            ctx.stroke();
                        }
                    }
                    const mouseDist = Math.hypot(particles[i].x - mouseX, particles[i].y - mouseY);
                    if (mouseDist < 150) {
                        ctx.beginPath();
                        ctx.moveTo(particles[i].x, particles[i].y);
                        ctx.lineTo(mouseX, mouseY);
                        ctx.strokeStyle = `rgba(16, 185, 129, ${0.3 * (1 - mouseDist / 150)})`;
                        ctx.stroke();
                    }
                }
                requestAnimationFrame(animate);
            }

            // Focus-Resolve Entrance
            const tl = gsap.timeline();
            tl.to(authCard, {
                opacity: 1, scale: 1, y: 0, filter: "blur(0px)",
                duration: 0.6, ease: "power3.out"
            })
            .to('.stagger', {
                opacity: 1, y: 0,
                stagger: 0.03, duration: 0.4, ease: "power2.out"
            }, "-=0.4");

            // Interactive Repel & Tilt
            let mouseX = window.innerWidth / 2;
            let mouseY = window.innerHeight / 2;

            document.addEventListener('mousemove', (e) => {
                mouseX = e.clientX;
                mouseY = e.clientY;

                orbs.forEach((orb, i) => {
                    const factor = (i + 1) * 0.04;
                    gsap.to(orb, { x: -(mouseX - window.innerWidth / 2) * factor, y: -(mouseY - window.innerHeight / 2) * factor, duration: 1.8, ease: "power2.out" });
                });

                const rect = authCard.getBoundingClientRect();
                const cardX = mouseX - rect.left - rect.width / 2;
                const cardY = mouseY - rect.top - rect.height / 2;
                
                gsap.to(authCard, {
                    rotateX: (cardY / (rect.height / 2)) * -5,
                    rotateY: (cardX / (rect.width / 2)) * 5,
                    duration: 0.6, ease: "power2.out"
                });

                gsap.set(glassReflection, { opacity: 0.4 });
                gsap.to(glassReflection, {
                    x: (cardX / rect.width) * 100 + "%",
                    y: (cardY / rect.height) * 100 + "%",
                    duration: 0.5, ease: "power2.out"
                });
            });

            animate();
        });
    </script>
</body>

</html>

