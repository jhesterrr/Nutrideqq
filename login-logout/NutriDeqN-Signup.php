<?php
require_once dirname(__DIR__) . '/session_bootstrap.php';

require_once '../database.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Handled natively by process_register.php
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#059669">
    <link rel="manifest" href="../manifest.json">
    <title>NutriDeq - Create Elite Account</title>
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
        .orb-special { width: 700px; height: 700px; background: rgba(16, 185, 129, 0.1); bottom: -20%; left: 30%; opacity: 0.15; }

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
            max-width: 500px;
            background: var(--glass-bg);
            backdrop-filter: blur(40px); -webkit-backdrop-filter: blur(40px);
            border: 2px solid var(--glass-border);
            border-radius: 40px;
            padding: 3.5rem 3rem;
            position: relative;
            transform-style: preserve-3d;
            box-shadow: 0 40px 100px rgba(0,0,0,0.06), 
                        inset 0 0 0 1px rgba(255,255,255,0.8);
            overflow: visible;
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
            position: relative;
        }
        .logo-box img { width: 35px; }

        .auth-header h1 { font-family: 'Outfit'; font-size: 2.2rem; color: var(--text-main); margin-bottom: 0.6rem; letter-spacing: -1px; }
        .auth-header p { color: var(--text-muted); font-size: 1rem; font-weight: 500; }

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
            position: relative;
            overflow: hidden;
            transition: all 0.4s var(--bounce);
            box-shadow: 0 10px 30px rgba(5, 150, 105, 0.2);
            margin-top: 1rem;
        }
        .btn-elite:hover { transform: translateY(-4px) scale(1.02); box-shadow: 0 20px 45px rgba(5, 150, 105, 0.3); background: var(--primary); }
        .btn-elite:active { transform: translateY(-2px) scale(0.98); }

        .google-auth {
            display: flex; align-items: center; justify-content: center; gap: 0.8rem;
            width: 100%; padding: 1rem; background: white; border: 1px solid #e2e8f0;
            border-radius: 14px; margin-top: 1.5rem; text-decoration: none;
            color: var(--text-main); font-weight: 600; font-size: 0.95rem;
            transition: all 0.3s;
        }
        .google-auth:hover { background: #f8fafc; border-color: #cbd5e1; transform: translateY(-2px); }
        .google-auth img { width: 22px; }

        .auth-footer { margin-top: 2.5rem; text-align: center; color: var(--text-muted); font-weight: 500; }
        .auth-footer a { color: var(--primary-dark); font-weight: 800; text-decoration: none; position: relative; }

        .message { padding: 1rem; border-radius: 12px; margin-bottom: 2rem; display: flex; align-items: center; gap: 0.8rem; font-weight: 600; }
        .error-message { background: #fff1f2; border-left: 4px solid #ef4444; color: #ef4444; }
        .success-message { background: #f0fdf4; border-left: 4px solid #10b981; color: #10b981; }

        /* Staggers */
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
        <div class="interactive-orb orb-special"></div>
    </div>

    <div class="tilt-wrapper">
        <div class="auth-card" id="auth-card">
            <div class="glass-reflection" id="glass-reflection"></div>
            
            <div class="card-content">
                <a href="../index.php" class="back-home-link stagger">
                    <i class="fas fa-chevron-left"></i> Back to Home
                </a>

                <div class="auth-header">
                    <a href="../index.php" style="text-decoration:none;">
                        <div class="logo-box stagger">
                            <svg viewBox="0 0 240 240" style="width: 45px; height: 45px; overflow: visible;">
                                <path d="M120 40 L165 90 L120 135 L75 90 Z" fill="#10b981"/>
                                <path d="M75 90 L120 135 L100 190 L55 135 Z" fill="#059669"/>
                                <path d="M165 90 L185 135 L140 190 L120 135 Z" fill="#34d399"/>
                            </svg>
                        </div>
                    </a>
                    <h1 class="stagger">Create Account</h1>
                    <p class="stagger">Join NutriDeq to start managing plans</p>
                </div>

                <?php if (isset($_GET['error'])): ?>
                    <div class="message error-message stagger">
                        <i class="fas fa-circle-exclamation"></i>
                        <?php echo htmlspecialchars($_GET['error']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['success'])): ?>
                    <div class="message success-message stagger">
                        <i class="fas fa-circle-check"></i>
                        <?php echo htmlspecialchars($_GET['success']); ?>
                    </div>
                <?php endif; ?>

                <form action="process_register.php" method="POST">
                    <?php echo csrf_field(); ?>
                    <div class="form-group stagger">
                        <input type="text" id="name" name="name" required value="<?php echo isset($_GET['name']) ? htmlspecialchars($_GET['name']) : ''; ?>">
                        <label for="name">Full Name</label>
                        <div class="input-accent"></div>
                    </div>

                    <div class="form-group stagger">
                        <input type="email" id="email" name="email" required value="<?php echo isset($_GET['email']) ? htmlspecialchars($_GET['email']) : ''; ?>">
                        <label for="email">Email Address</label>
                        <div class="input-accent"></div>
                    </div>

                    <div class="form-group stagger">
                        <input type="password" id="password" name="password" required>
                        <label for="password">Password (at least 8 characters)</label>
                        <div class="input-accent"></div>
                    </div>

                    <div class="form-group stagger">
                        <input type="password" id="confirm_password" name="confirm_password" required>
                        <label for="confirm_password">Confirm Password</label>
                        <div class="input-accent"></div>
                    </div>

                    <button type="submit" class="btn-elite stagger">Sign Up</button>
                </form>

                <div class="auth-footer stagger">
                    Already have an account? <a href="NutriDeqN-Login.php">Log In</a>
                </div>
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

            // Interactive Repel Backdrop
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
                const cardX = mouseX - rect.left - rect.width / 2, cardY = mouseY - rect.top - rect.height / 2;
                gsap.to(authCard, { rotateX: (cardY / (rect.height / 2)) * -5, rotateY: (cardX / (rect.width / 2)) * 5, duration: 0.6 });
                gsap.set(glassReflection, { opacity: 0.4 });
                gsap.to(glassReflection, { x: (cardX / rect.width) * 100 + "%", y: (cardY / rect.height) * 100 + "%", duration: 0.5 });
            });

            animate();
        });
    </script>
</body>

</html>
