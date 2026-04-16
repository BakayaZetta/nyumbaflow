<?php
require_once 'db_config.php';
require_once 'csrf_token.php'; 
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nyumbaflow | Portal</title>
    <link rel="shortcut icon" href="icons/home.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3a0ca3;
            --success: #4cc9f0;
            --warning: #f72585;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --light-gray: #e9ecef;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            display: flex;
            min-height: 90vh;
        }

        .auth-side {
            flex: 1;
            background: linear-gradient(to bottom right, var(--primary), var(--secondary));
            color: white;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        .auth-side::before {
            content: '';
            position: absolute;
            width: 200%;
            height: 200%;
            background: rgba(255, 255, 255, 0.1);
            transform: rotate(45deg);
            top: -50%;
            left: -50%;
        }

        .auth-content {
            position: relative;
            z-index: 1;
        }

        .auth-side h1 {
            font-size: 2.5rem;
            margin-bottom: 20px;
        }

        .auth-side p {
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .features {
            list-style: none;
        }

        .features li {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }

        .features i {
            margin-right: 10px;
            font-size: 1.2rem;
        }

        .form-side {
            flex: 1.5;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-container {
            width: 100%;
            max-width: 450px;
            margin: 0 auto;
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo h2 {
            color: var(--primary);
            font-size: 2rem;
            font-weight: 700;
        }

        .logo span {
            color: var(--secondary);
        }

        .tabs {
            display: flex;
            margin-bottom: 30px;
            border-bottom: 2px solid var(--light-gray);
        }

        .tab {
            padding: 12px 20px;
            cursor: pointer;
            font-weight: 600;
            color: var(--gray);
            transition: all 0.3s;
        }

        .tab.active {
            color: var(--primary);
            border-bottom: 3px solid var(--primary);
            margin-bottom: -2px;
        }

        .form {
            display: none;
        }

        .form.active {
            display: block;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }

        .form-control {
            width: 100%;
            padding: 14px;
            border: 2px solid var(--light-gray);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }

        .password-group {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
            cursor: pointer;
            z-index: 10;
        }

        .password-toggle:hover {
            color: var(--primary);
        }

        .btn {
            width: 100%;
            padding: 14px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn:hover {
            background: var(--secondary);
            transform: translateY(-2px);
        }

        .forgot-link {
            text-align: right;
            margin-top: 10px;
        }

        .forgot-link a {
            color: var(--gray);
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s;
        }

        .forgot-link a:hover {
            color: var(--primary);
        }

        .divider {
            text-align: center;
            margin: 25px 0;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            width: 100%;
            height: 1px;
            background: var(--light-gray);
        }

        .divider span {
            background: white;
            padding: 0 15px;
            color: var(--gray);
            position: relative;
        }

        .social-auth {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 25px;
        }

        .social-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            background: var(--light);
            color: var(--dark);
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.3s;
        }

        .social-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .facebook {
            color: #3b5998;
        }

        .google {
            color: #db4437;
        }

        .twitter {
            color: #1da1f2;
        }

        .questionnaire {
            display: none;
        }

        .questionnaire.active {
            display: block;
        }

        .progress-bar {
            height: 8px;
            background: var(--light-gray);
            border-radius: 4px;
            margin-bottom: 30px;
            overflow: hidden;
        }

        .progress {
            height: 100%;
            background: var(--primary);
            border-radius: 4px;
            width: 0%;
            transition: width 0.5s;
        }

        .question {
            margin-bottom: 25px;
        }

        .question h3 {
            margin-bottom: 15px;
            color: var(--dark);
        }

        .options {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }

        .option {
            padding: 15px;
            border: 2px solid var(--light-gray);
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .option:hover {
            border-color: var(--primary);
        }

        .option.selected {
            border-color: var(--primary);
            background: rgba(67, 97, 238, 0.1);
        }

        .nav-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }

        .btn-prev,
        .btn-next {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-prev {
            background: var(--light);
            color: var(--dark);
        }

        .btn-next {
            background: var(--primary);
            color: white;
        }

        .btn-prev:hover {
            background: var(--light-gray);
        }

        .btn-next:hover {
            background: var(--secondary);
        }

        .summary-item {
            margin-bottom: 15px;
            padding: 15px;
            background: var(--light);
            border-radius: 8px;
        }

        .summary-item h4 {
            margin-bottom: 5px;
            color: var(--dark);
        }

        .summary-item p {
            color: var(--gray);
        }

        .loader {
            display: none;
            text-align: center;
            padding: 40px 0;
        }

        .loader.active {
            display: block;
        }

        .spinner {
            width: 60px;
            height: 60px;
            border: 5px solid rgba(67, 97, 238, 0.2);
            border-radius: 50%;
            border-top-color: var(--primary);
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .apartment-config {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid var(--light-gray);
            border-radius: 8px;
        }

        .apartment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .floor-config {
            margin-bottom: 15px;
            padding: 10px;
            background: var(--light);
            border-radius: 5px;
        }

        .room-input-group {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }

        .room-input {
            flex: 1;
        }

        .add-room-btn {
            background: var(--success);
            color: white;
            border: none;
            border-radius: 5px;
            padding: 5px 10px;
            cursor: pointer;
        }

        @media (max-width: 900px) {
            .container {
                flex-direction: column;
                max-width: 500px;
            }

            .auth-side {
                padding: 30px;
            }

            .form-side {
                padding: 30px;
            }
        }

        @media (max-width: 500px) {
            .container {
                border-radius: 10px;
            }

            .auth-side,
            .form-side {
                padding: 20px;
            }

            .options {
                grid-template-columns: 1fr;
            }
        }

    </style>

</head>

<body>
    <div class="container">

        <!-- Left side with branding and features -->
        <div class="auth-side">
            <div class="auth-content">
                <h1>Nyumbaflow</h1>
                <p>Streamline your property management with our comprehensive landlord portal. Manage tenants, bills,
                    and visitors all in one place.</p>
                <ul class="features">
                    <li><i class="fas fa-check-circle"></i> Secure tenant management</li>
                    <li><i class="fas fa-check-circle"></i> Automated billing system</li>
                    <li><i class="fas fa-check-circle"></i> Visitor tracking</li>
                    <li><i class="fas fa-check-circle"></i> Payment reminders via SMS</li>
                    <li><i class="fas fa-check-circle"></i> Detailed financial reports</li>
                </ul>
            </div>
        </div>

        <!-- Right side with forms -->
        <div class="form-side">
            <div class="form-container">
                <!-- Logo -->
                <div class="logo">
                    <h2>Nyumba<span>flow</span></h2>
                </div>

                <!-- Authorization Forms -->
                <div class="auth-forms">
                    <div class="tabs">
                        <div class="tab active" data-tab="login">Login</div>
                        <div class="tab" data-tab="signup">Sign Up</div>
                    </div>

                    <!-- Login Form -->
                    <form class="form active" id="login-form" method="POST" action="auth_action.php">
                        <?php echo get_csrf_token_field(); ?>
                        <input type="hidden" name="action" value="login">
                        <div class="form-group">
                            <label for="login-email">Email</label>
                            <input type="email" id="login-email" name="email" class="form-control"
                                placeholder="Enter your email" required>
                        </div>
                        <div class="form-group">
                            <label for="login-password">Password</label>
                            <div class="password-group">
                                <input type="password" id="login-password" name="password" class="form-control"
                                    placeholder="Enter your password" required>
                                <i class="fas fa-eye password-toggle"
                                    onclick="togglePassword('login-password', this)"></i>
                            </div>
                        </div>
                        <button type="submit" class="btn">Login</button>
                        <div class="forgot-link">
                            <a href="#" id="show-forgot">Forgot Password?</a>
                        </div>

                        <div class="divider">
                            <span>Or continue with</span>
                        </div>

                        <div class="social-auth">
                            <div class="social-btn facebook">
                                <i class="fab fa-facebook-f"></i>
                            </div>
                            <div class="social-btn google">
                                <i class="fab fa-google"></i>
                            </div>
                            <div class="social-btn twitter">
                                <i class="fab fa-twitter"></i>
                            </div>
                        </div>
                    </form>

                    <!-- Signup Form -->
                    <form class="form" id="signup-form" method="POST" action="auth_action.php">
                        <?php echo get_csrf_token_field(); ?>
                        <input type="hidden" name="action" value="signup">
                        <div class="form-group">
                            <label for="signup-name">Full Name</label>
                            <input type="text" id="signup-name" name="name" class="form-control"
                                placeholder="Enter your full name" required>
                        </div>
                        <div class="form-group">
                            <label for="signup-email">Email</label>
                            <input type="email" id="signup-email" name="email" class="form-control"
                                placeholder="Enter your email" required>
                        </div>
                        <div class="form-group">
                            <label for="signup-password">Password</label>
                            <div class="password-group">
                                <input type="password" id="signup-password" name="password" class="form-control"
                                    placeholder="Create a password" required>
                                <i class="fas fa-eye password-toggle"
                                    onclick="togglePassword('signup-password', this)"></i>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="signup-confirm">Confirm Password</label>
                            <div class="password-group">
                                <input type="password" id="signup-confirm" name="confirm_password" class="form-control"
                                    placeholder="Confirm your password" required>
                                <i class="fas fa-eye password-toggle"
                                    onclick="togglePassword('signup-confirm', this)"></i>
                            </div>
                        </div>
                        <button type="submit" class="btn">Create Account</button>

                        <div class="divider">
                            <span>Or continue with</span>
                        </div>

                        <div class="social-auth">
                            <div class="social-btn facebook">
                                <i class="fab fa-facebook-f"></i>
                            </div>
                            <div class="social-btn google">
                                <i class="fab fa-google"></i>
                            </div>
                            <div class="social-btn twitter">
                                <i class="fab fa-twitter"></i>
                            </div>
                        </div>
                    </form>

                    <!-- Forgot Password Form -->
                    <form class="form" id="forgot-form" method="POST" action="auth_action.php">
                        <?php echo get_csrf_token_field(); ?>
                        <input type="hidden" name="action" value="forgot">
                        <div class="form-group">
                            <label for="forgot-email">Email</label>
                            <input type="email" id="forgot-email" name="email" class="form-control"
                                placeholder="Enter your email" required>
                        </div>
                        <button type="submit" class="btn">Reset Password</button>
                        <div class="forgot-link">
                            <a href="#" id="back-to-login">Back to Login</a>
                        </div>
                    </form>
                </div>

                <!-- Questionnaire -->
                <div class="questionnaire" id="questionnaire">
                    <h2>Property Setup</h2>
                    <p>Let's customize your experience by setting up your properties</p>

                    <div class="progress-bar">
                        <div class="progress" id="progress"></div>
                    </div>

                    <form id="setup-form" method="POST" action="setup.php">
                        <?php echo get_csrf_token_field(); ?>
                        <div class="question" id="question-1">
                            <h3>How many apartments do you have?</h3>
                            <div class="form-group">
                                <input type="number" id="apartment-count" name="apartment_count" class="form-control"
                                    min="1" required>
                            </div>
                        </div>

                        <div class="question" id="question-2" style="display: none;">
                            <h3>Do all apartments have the same number of floors?</h3>
                            <div class="options">
                                <div class="option" data-value="yes">Yes</div>
                                <div class="option" data-value="no">No</div>
                            </div>
                        </div>

                        <div class="question" id="question-3" style="display: none;">
                            <h3>How many floors does each apartment have?</h3>
                            <div id="floor-configuration">
                                <!-- Dynamic content will be inserted here -->
                            </div>
                        </div>

                        <div class="question" id="question-4" style="display: none;">
                            <h3>How many rooms are on each floor?</h3>
                            <div id="room-configuration">
                                <!-- Dynamic content will be inserted here -->
                            </div>
                        </div>

                        <div class="question" id="question-5" style="display: none;">
                            <h3>Are there any additional rooms? (e.g., storage, basement)</h3>
                            <div class="options">
                                <div class="option" data-value="yes">Yes</div>
                                <div class="option" data-value="no">No</div>
                            </div>
                        </div>

                        <div class="question" id="question-6" style="display: none;">
                            <h3>How much is the rent?</h3>
                            <div class="options">
                                <div class="option" data-value="same">Same for all</div>
                                <div class="option" data-value="custom">Custom pricing</div>
                            </div>
                        </div>

                        <div class="question" id="question-7" style="display: none;">
                            <h3>Do you charge for garbage collection?</h3>
                            <div class="options">
                                <div class="option" data-value="yes">Yes</div>
                                <div class="option" data-value="no">No</div>
                            </div>
                        </div>

                        <div class="question" id="question-8" style="display: none;">
                            <h3>How much do you charge per unit of water? (in KSH)</h3>
                            <div class="form-group">
                                <input type="number" id="water-rate" name="water_rate" class="form-control" min="0"
                                    step="0.01" required>
                            </div>
                        </div>

                        <div class="question" id="question-9" style="display: none;">
                            <h3>Do you charge for WiFi?</h3>
                            <div class="options">
                                <div class="option" data-value="yes">Yes</div>
                                <div class="option" data-value="no">No</div>
                            </div>
                        </div>

                        <div class="question" id="question-10" style="display: none;">
                            <h3>Summary</h3>
                            <div class="summary-item">
                                <h4>Total Apartments</h4>
                                <p id="summary-apartments">0</p>
                            </div>
                            <div class="summary-item">
                                <h4>Total Floors</h4>
                                <p id="summary-floors">0</p>
                            </div>
                            <div class="summary-item">
                                <h4>Total Rooms</h4>
                                <p id="summary-rooms">0</p>
                            </div>
                            <div class="summary-item">
                                <h4>Services</h4>
                                <p id="summary-services">None configured yet</p>
                            </div>
                        </div>

                        <div class="nav-buttons">
                            <button type="button" class="btn-prev" id="prev-btn">Previous</button>
                            <button type="button" class="btn-next" id="next-btn">Next</button>
                        </div>
                    </form>
                </div>

                <!-- Loader -->
                <div class="loader" id="loader">
                    <div class="spinner"></div>
                    <h3>Setting up your account</h3>
                    <p>This may take a few moments...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Tab switching
            const tabs = document.querySelectorAll('.tab');
            const forms = document.querySelectorAll('.form');

            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const tabId = tab.getAttribute('data-tab');

                    // Update active tab
                    tabs.forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');

                    // Show corresponding form
                    forms.forEach(form => form.classList.remove('active'));
                    document.getElementById(`${tabId}-form`).classList.add('active');
                });
            });

            // Forgot password link
            document.getElementById('show-forgot').addEventListener('click', function (e) {
                e.preventDefault();
                forms.forEach(form => form.classList.remove('active'));
                document.getElementById('forgot-form').classList.add('active');

                // Update tabs
                tabs.forEach(t => t.classList.remove('active'));
            });

            // Back to login link
            document.getElementById('back-to-login').addEventListener('click', function (e) {
                e.preventDefault();
                forms.forEach(form => form.classList.remove('active'));
                document.getElementById('login-form').classList.add('active');

                // Update tabs
                tabs.forEach(t => t.classList.remove('active'));
                document.querySelector('[data-tab="login"]').classList.add('active');
            });

            // Questionnaire navigation
            const progress = document.getElementById('progress');
            const nextBtn = document.getElementById('next-btn');
            const prevBtn = document.getElementById('prev-btn');
            const questions = document.querySelectorAll('.question');
            let currentQuestion = 1;
            let apartmentCount = 0;
            let sameFloors = true;
            let floorCounts = [];

            // Update progress bar
            function updateProgress() {
                const progressPercent = (currentQuestion / questions.length) * 100;
                progress.style.width = `${progressPercent}%`;
            }

            // Show current question
            function showQuestion(num) {
                questions.forEach(q => q.style.display = 'none');
                document.getElementById(`question-${num}`).style.display = 'block';

                // Update buttons
                if (currentQuestion === 1) {
                    prevBtn.style.visibility = 'hidden';
                } else {
                    prevBtn.style.visibility = 'visible';
                }

                if (currentQuestion === questions.length) {
                    nextBtn.textContent = 'Finish';
                } else {
                    nextBtn.textContent = 'Next';
                }
            }

            // Option selection
            const options = document.querySelectorAll('.option');
            options.forEach(option => {
                option.addEventListener('click', function () {
                    // Remove selected class from siblings
                    const siblings = this.parentElement.children;
                    for (let i = 0; i < siblings.length; i++) {
                        siblings[i].classList.remove('selected');
                    }

                    // Add selected class to clicked option
                    this.classList.add('selected');
                });
            });

            // Next button click
            nextBtn.addEventListener('click', function () {
                if (currentQuestion === 1) {
                    // Get apartment count
                    apartmentCount = parseInt(document.getElementById('apartment-count').value);
                    if (isNaN(apartmentCount) || apartmentCount < 1) {
                        alert('Please enter a valid number of apartments');
                        return;
                    }
                } else if (currentQuestion === 2) {
                    // Check if same floors option is selected
                    const selected = document.querySelector('#question-2 .option.selected');
                    if (!selected) {
                        alert('Please select an option');
                        return;
                    }
                    sameFloors = selected.dataset.value === 'yes';

                    // If same floors, ask for the number once
                    if (sameFloors) {
                        const floorConfig = document.getElementById('floor-configuration');
                        floorConfig.innerHTML = `
                            <div class="form-group">
                                <label>Number of floors for all apartments</label>
                                <input type="number" class="form-control" min="1" id="same-floor-count" required>
                            </div>
                        `;
                    } else {
                        // If different floors, ask for each apartment
                        let html = '';
                        for (let i = 1; i <= apartmentCount; i++) {
                            html += `
                                <div class="form-group">
                                    <label>Number of floors for Apartment ${i}</label>
                                    <input type="number" class="form-control" min="1" id="floor-count-${i}" required>
                                </div>
                            `;
                        }
                        document.getElementById('floor-configuration').innerHTML = html;
                    }
                } else if (currentQuestion === 3) {
                    // Get floor counts
                    if (sameFloors) {
                        const floorCount = parseInt(document.getElementById('same-floor-count').value);
                        if (isNaN(floorCount) || floorCount < 1) {
                            alert('Please enter a valid number of floors');
                            return;
                        }
                        floorCounts = Array(apartmentCount).fill(floorCount);
                    } else {
                        floorCounts = [];
                        for (let i = 1; i <= apartmentCount; i++) {
                            const count = parseInt(document.getElementById(`floor-count-${i}`).value);
                            if (isNaN(count) || count < 1) {
                                alert(`Please enter a valid number of floors for Apartment ${i}`);
                                return;
                            }
                            floorCounts.push(count);
                        }
                    }

                    // Generate room configuration UI
                    let html = '';
                    for (let apt = 1; apt <= apartmentCount; apt++) {
                        html += `<h4>Apartment ${apt}</h4>`;
                        for (let floor = 1; floor <= floorCounts[apt - 1]; floor++) {
                            html += `
                                <div class="form-group">
                                    <label>Number of rooms on Floor ${floor}, Apartment ${apt}</label>
                                    <input type="number" class="form-control" min="1" id="room-count-apt-${apt}-floor-${floor}" required>
                                </div>
                            `;
                        }
                    }
                    document.getElementById('room-configuration').innerHTML = html;
                } else if (currentQuestion === 10) {
                    // Calculate summary
                    let totalFloors = floorCounts.reduce((a, b) => a + b, 0);
                    let totalRooms = 0;

                    // This would normally be calculated from the room inputs
                    // For demo purposes, we'll use a placeholder
                    totalRooms = totalFloors * 4; // Assuming 4 rooms per floor

                    document.getElementById('summary-apartments').textContent = apartmentCount;
                    document.getElementById('summary-floors').textContent = totalFloors;
                    document.getElementById('summary-rooms').textContent = totalRooms;

                    // Get selected services
                    const services = [];
                    if (document.querySelector('#question-7 .option.selected')?.dataset.value === 'yes') {
                        services.push('Garbage Collection');
                    }
                    if (document.querySelector('#question-9 .option.selected')?.dataset.value === 'yes') {
                        services.push('WiFi');
                    }
                    services.push('Water');
                    services.push('Rent');

                    document.getElementById('summary-services').textContent = services.join(', ');
                }

                if (currentQuestion < questions.length) {
                    currentQuestion++;
                    showQuestion(currentQuestion);
                    updateProgress();
                } else {
                    // Finish questionnaire
                    document.getElementById('questionnaire').style.display = 'none';
                    document.getElementById('loader').classList.add('active');

                    // Submit the form
                    document.getElementById('setup-form').submit();
                }
            });

            // Previous button click
            prevBtn.addEventListener('click', function () {
                if (currentQuestion > 1) {
                    currentQuestion--;
                    showQuestion(currentQuestion);
                    updateProgress();
                }
            });

            // Handle form submissions via AJAX
            document.querySelectorAll('form').forEach(form => {
                if (form.id === 'setup-form') return; // Skip onboarding setup form

                form.addEventListener('submit', function (e) {
                    e.preventDefault();

                    const formData = new FormData(this);
                    const action = this.getAttribute('action');

                    fetch(action, {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                if (data.redirect) {
                                    window.location.href = data.redirect;
                                } else {
                                    alert(data.message || 'Action successful');
                                }
                            } else {
                                alert(data.error || 'An error occurred');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('A server error occurred');
                        });
                });
            });

            window.togglePassword = function (id, icon) {
                const input = document.getElementById(id);
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            };

            // Initialize
            updateProgress();
            showQuestion(1);
        });
    </script>
</body>

</html>
