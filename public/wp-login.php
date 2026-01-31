<?php
/**
 * WordPress Login Honeypot - Phase 3 Aggressive (Security Hardened)
 * 
 * SECURITY FEATURES:
 * - Input sanitization against log injection
 * - PHP error suppression (no information disclosure)
 * - Secure cookie settings (HttpOnly, Secure, SameSite)
 * - Optional password encryption at rest
 * - Rate limiting awareness
 * - Request validation
 * 
 * RESEARCH JUSTIFICATION:
 * This honeypot captures full credentials from attackers for TTP analysis.
 * 
 * ETHICAL POSITION:
 * 1. This endpoint serves NO legitimate purpose on a non-WordPress site
 * 2. Anyone submitting credentials is attempting unauthorized access
 * 3. Attackers have no reasonable expectation of privacy in criminal activity
 * 4. Full credential analysis is necessary to identify attack methodologies
 * 5. This data will NEVER be used for offensive purposes ("hacking back")
 * 
 * LEGAL BASIS:
 * - Provider exception to Wiretap Act (protecting own systems)
 * - GDPR Article 6(1)(f) - Legitimate interest in security
 * - No CFAA violation - defensive monitoring only
 * 
 * DATA HANDLING:
 * - Stored in secured, access-controlled system
 * - 90-day retention limit
 * - Used for academic research only
 * - Anonymized/aggregated for publication
 * 
 * Project: INST 570 - Information Security Ethics and Legal Aspects
 * Researcher: Brian Chaplow
 * Version: 3.2.0 (Security Hardened)
 */

// ============================================================================
// SECURITY: SUPPRESS ERROR DISPLAY
// ============================================================================
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/var/log/apache2/honeypot-php-errors.log');

// ============================================================================
// CONFIGURATION
// ============================================================================
$CONFIG = [
    // Logging
    'log_file' => '/var/log/honeypot/credentials.json',
    'max_password_log_length' => 256,
    'max_username_log_length' => 128,
    
    // Timing
    'artificial_delay_ms' => [800, 2500],
    
    // Wordlists (outside web root for security)
    'common_passwords_file' => '/opt/honeypot/wordlists/common-passwords.txt',
    'common_usernames_file' => '/opt/honeypot/wordlists/common-usernames.txt',
    
    // Security
    'encrypt_passwords' => true,
    'encryption_key' => getenv('HONEYPOT_ENCRYPTION_KEY') ?: null,
    
    // Rate limiting (works with Cloudflare)
    'max_attempts_per_session' => 50,
    'session_timeout' => 3600,
    
    // Logging verbosity
    'log_page_views' => true,  // Set false to only log credential submissions
];

// ============================================================================
// SECURITY FUNCTIONS
// ============================================================================

/**
 * Sanitize input to prevent log injection attacks
 * Removes null bytes, control characters, and potential injection vectors
 */
function sanitizeForLog($input) {
    if (!is_string($input)) {
        return '';
    }
    
    // Remove null bytes
    $input = str_replace("\0", '', $input);
    
    // Remove control characters except tab and newline (which JSON will escape)
    $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input);
    
    // Remove Unicode control characters
    $input = preg_replace('/[\x{0080}-\x{009F}]/u', '', $input);
    
    // Limit to printable ASCII + common Unicode (no weird stuff)
    // This is intentionally permissive to capture international credentials
    
    return $input;
}

/**
 * Encrypt sensitive data for storage
 */
function encryptData($data, $key) {
    if (empty($key) || strlen($key) < 16) {
        return null; // Key not configured or too short
    }
    
    try {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) {
            return null;
        }
        return base64_encode($iv . $encrypted);
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Validate and sanitize request data
 */
function validateRequest() {
    $issues = [];
    
    // Check for suspiciously large POST data
    if ($_SERVER['CONTENT_LENGTH'] ?? 0 > 10240) { // 10KB max
        $issues[] = 'oversized_request';
    }
    
    // Check for missing expected fields in POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['log']) || !isset($_POST['pwd'])) {
            $issues[] = 'missing_fields';
        }
    }
    
    return $issues;
}

/**
 * Set secure cookie with all protections
 */
function setSecureCookie($name, $value, $expires) {
    if (PHP_VERSION_ID >= 70300) {
        // PHP 7.3+ supports options array
        setcookie($name, $value, [
            'expires' => $expires,
            'path' => '/',
            'domain' => '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    } else {
        // Fallback for older PHP
        setcookie($name, $value, $expires, '/; SameSite=Strict', '', true, true);
    }
}

/**
 * Check rate limiting (session-based)
 */
function checkRateLimit($sessionId, $maxAttempts) {
    $rateLimitFile = sys_get_temp_dir() . '/hp_ratelimit_' . md5($sessionId);
    
    $attempts = 0;
    $firstAttempt = time();
    
    if (file_exists($rateLimitFile)) {
        $data = json_decode(file_get_contents($rateLimitFile), true);
        if ($data && isset($data['attempts']) && isset($data['first'])) {
            // Reset if window expired (1 hour)
            if (time() - $data['first'] > 3600) {
                $attempts = 0;
                $firstAttempt = time();
            } else {
                $attempts = $data['attempts'];
                $firstAttempt = $data['first'];
            }
        }
    }
    
    $attempts++;
    
    // Save updated count
    file_put_contents($rateLimitFile, json_encode([
        'attempts' => $attempts,
        'first' => $firstAttempt
    ]), LOCK_EX);
    
    return [
        'allowed' => $attempts <= $maxAttempts,
        'attempts' => $attempts,
        'remaining' => max(0, $maxAttempts - $attempts)
    ];
}

/**
 * Securely write to log file with locking
 */
function writeLog($logFile, $entry) {
    $json = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        // JSON encoding failed - log minimal entry
        $json = json_encode([
            'timestamp' => gmdate('Y-m-d\TH:i:s.v\Z'),
            'error' => 'json_encode_failed',
            'request_id' => $entry['request_id'] ?? 'unknown'
        ]);
    }
    
    $result = file_put_contents($logFile, $json . "\n", FILE_APPEND | LOCK_EX);
    return $result !== false;
}

// ============================================================================
// ANALYSIS FUNCTIONS
// ============================================================================

/**
 * Analyze password characteristics for TTP identification
 */
function analyzePassword($password) {
    $length = strlen($password);
    
    return [
        'length' => $length,
        'charset' => [
            'lowercase' => (bool) preg_match('/[a-z]/', $password),
            'uppercase' => (bool) preg_match('/[A-Z]/', $password),
            'digits' => (bool) preg_match('/[0-9]/', $password),
            'special' => (bool) preg_match('/[^A-Za-z0-9]/', $password),
            'unicode' => (bool) preg_match('/[^\x00-\x7F]/', $password),
        ],
        'patterns' => [
            'all_lowercase' => (bool) preg_match('/^[a-z]+$/', $password),
            'all_uppercase' => (bool) preg_match('/^[A-Z]+$/', $password),
            'all_digits' => (bool) preg_match('/^[0-9]+$/', $password),
            'starts_uppercase' => (bool) preg_match('/^[A-Z]/', $password),
            'ends_digits' => (bool) preg_match('/[0-9]+$/', $password),
            'ends_special' => (bool) preg_match('/[^A-Za-z0-9]$/', $password),
            'keyboard_walk' => (bool) preg_match('/qwerty|asdf|zxcv|1234|!@#\$/i', $password),
            'repeating' => (bool) preg_match('/(.)\1{2,}/', $password),
            'sequential_digits' => (bool) preg_match('/012|123|234|345|456|567|678|789/', $password),
            'leet_speak' => (bool) preg_match('/[4@][a-z]*[3€][a-z]*|p[4@]ss|[4@]dm[1!]n/i', $password),
        ],
        'entropy_estimate' => estimateEntropy($password),
        'complexity_score' => calculateComplexityScore($password),
    ];
}

/**
 * Rough entropy estimation
 */
function estimateEntropy($password) {
    $charsetSize = 0;
    if (preg_match('/[a-z]/', $password)) $charsetSize += 26;
    if (preg_match('/[A-Z]/', $password)) $charsetSize += 26;
    if (preg_match('/[0-9]/', $password)) $charsetSize += 10;
    if (preg_match('/[^A-Za-z0-9]/', $password)) $charsetSize += 32;
    
    if ($charsetSize === 0) return 0;
    
    return round(strlen($password) * log($charsetSize, 2), 2);
}

/**
 * Calculate complexity score (0-100)
 */
function calculateComplexityScore($password) {
    $score = 0;
    $length = strlen($password);
    
    $score += min($length * 4, 40);
    
    if (preg_match('/[a-z]/', $password)) $score += 10;
    if (preg_match('/[A-Z]/', $password)) $score += 10;
    if (preg_match('/[0-9]/', $password)) $score += 10;
    if (preg_match('/[^A-Za-z0-9]/', $password)) $score += 15;
    
    if (preg_match('/^[a-z]+$/', $password)) $score -= 10;
    if (preg_match('/^[0-9]+$/', $password)) $score -= 15;
    if (preg_match('/(.)\1{2,}/', $password)) $score -= 10;
    
    return max(0, min(100, $score));
}

/**
 * Analyze username for targeting patterns
 */
function analyzeUsername($username) {
    $lower = strtolower($username);
    
    return [
        'length' => strlen($username),
        'type' => filter_var($username, FILTER_VALIDATE_EMAIL) ? 'email' : 'username',
        'patterns' => [
            'is_generic' => in_array($lower, ['admin', 'administrator', 'root', 'user', 'test', 'guest', 'wordpress', 'wp', 'webmaster']),
            'is_default_wp' => in_array($lower, ['admin', 'administrator', 'wordpress', 'wp-admin']),
            'contains_admin' => (bool) preg_match('/admin/i', $username),
            'contains_test' => (bool) preg_match('/test/i', $username),
            'contains_site_name' => (bool) preg_match('/brian|chaplow|bytes|bourbon|bbq/i', $username),
            'is_numeric' => (bool) preg_match('/^[0-9]+$/', $username),
            'has_year' => (bool) preg_match('/19[0-9]{2}|20[0-2][0-9]/', $username),
        ],
        'email_analysis' => analyzeEmailIfApplicable($username),
    ];
}

/**
 * If username is email, extract additional info
 */
function analyzeEmailIfApplicable($username) {
    if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    
    $parts = explode('@', $username);
    $domain = strtolower($parts[1] ?? '');
    
    return [
        'domain' => $domain,
        'is_freemail' => in_array($domain, [
            'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 
            'aol.com', 'mail.com', 'protonmail.com', 'icloud.com'
        ]),
        'is_disposable' => (bool) preg_match('/tempmail|guerrilla|mailinator|10minute/i', $domain),
        'tld' => pathinfo($domain, PATHINFO_EXTENSION),
    ];
}

/**
 * Check if credentials appear in common wordlists
 */
function checkWordlists($username, $password, $config) {
    $results = [
        'username_in_common_list' => false,
        'password_in_common_list' => false,
        'matched_wordlist' => null,
    ];
    
    // Safely check username wordlist
    if (!empty($config['common_usernames_file']) && file_exists($config['common_usernames_file'])) {
        $commonUsernames = @file($config['common_usernames_file'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($commonUsernames) {
            $results['username_in_common_list'] = in_array(strtolower($username), array_map('strtolower', $commonUsernames));
        }
    }
    
    // Safely check password wordlist
    if (!empty($config['common_passwords_file']) && file_exists($config['common_passwords_file'])) {
        $commonPasswords = @file($config['common_passwords_file'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($commonPasswords && in_array($password, $commonPasswords)) {
            $results['password_in_common_list'] = true;
            $results['matched_wordlist'] = 'common-passwords';
        }
    }
    
    return $results;
}

/**
 * Classify likely attack type based on patterns
 */
function classifyAttackType($usernameAnalysis, $passwordAnalysis, $wordlistResults) {
    $indicators = [];
    
    if ($wordlistResults['password_in_common_list']) {
        $indicators[] = 'dictionary';
    }
    
    if ($usernameAnalysis['type'] === 'email' && 
        $passwordAnalysis['complexity_score'] > 40 &&
        !$wordlistResults['password_in_common_list']) {
        $indicators[] = 'credential_stuffing';
    }
    
    if ($passwordAnalysis['patterns']['all_digits'] || 
        $passwordAnalysis['patterns']['sequential_digits'] ||
        $passwordAnalysis['length'] <= 4) {
        $indicators[] = 'brute_force';
    }
    
    if ($usernameAnalysis['patterns']['contains_site_name']) {
        $indicators[] = 'targeted';
    }
    
    if ($usernameAnalysis['patterns']['is_default_wp']) {
        $indicators[] = 'default_wordpress';
    }
    
    return [
        'indicators' => $indicators,
        'primary_classification' => $indicators[0] ?? 'unknown',
        'confidence' => count($indicators) > 0 ? 'medium' : 'low',
    ];
}

/**
 * Generate SHA-256 hash for correlation
 */
function generateCredentialHash($username, $password) {
    return [
        'username_hash' => hash('sha256', strtolower($username)),
        'password_hash' => hash('sha256', $password),
        'pair_hash' => hash('sha256', strtolower($username) . ':' . $password),
    ];
}

// ============================================================================
// MAIN LOGIC
// ============================================================================

$site = $_SERVER['HTTP_HOST'] ?? 'unknown';
$request_id = bin2hex(random_bytes(16));
$session_id = $_COOKIE['hp_session'] ?? bin2hex(random_bytes(8));
$is_post = $_SERVER['REQUEST_METHOD'] === 'POST';
$show_error = false;

// Set secure session cookie if not exists
if (!isset($_COOKIE['hp_session'])) {
    setSecureCookie('hp_session', $session_id, time() + $CONFIG['session_timeout']);
}

// Validate request
$validation_issues = validateRequest();

// Check rate limiting
$rate_limit = checkRateLimit($session_id, $CONFIG['max_attempts_per_session']);

// Build base log entry
$log_entry = [
    'timestamp' => gmdate('Y-m-d\TH:i:s.v\Z'),
    'request_id' => $request_id,
    'session_id' => $session_id,
    'honeypot' => [
        'type' => 'wp-login',
        'phase' => 3,
        'version' => '3.2.0-hardened',
    ],
    'target' => [
        'site' => sanitizeForLog($site),
        'uri' => sanitizeForLog($_SERVER['REQUEST_URI'] ?? ''),
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
    ],
    'source' => [
        'ip' => $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'port' => (int)($_SERVER['REMOTE_PORT'] ?? 0),
        'user_agent' => sanitizeForLog(substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512)),
        'referer' => sanitizeForLog(substr($_SERVER['HTTP_REFERER'] ?? '', 0, 512)),
        'accept_language' => sanitizeForLog(substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '', 0, 128)),
        'accept_encoding' => sanitizeForLog(substr($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 0, 128)),
    ],
    'cloudflare' => [
        'country' => $_SERVER['HTTP_CF_IPCOUNTRY'] ?? null,
        'city' => sanitizeForLog($_SERVER['HTTP_CF_IPCITY'] ?? ''),
        'region' => sanitizeForLog($_SERVER['HTTP_CF_REGION'] ?? ''),
        'postal_code' => $_SERVER['HTTP_CF_POSTAL_CODE'] ?? null,
        'timezone' => $_SERVER['HTTP_CF_TIMEZONE'] ?? null,
        'threat_score' => isset($_SERVER['HTTP_CF_THREAT_SCORE']) ? (int)$_SERVER['HTTP_CF_THREAT_SCORE'] : null,
        'bot_score' => isset($_SERVER['HTTP_CF_BOT_MANAGEMENT_SCORE']) ? (int)$_SERVER['HTTP_CF_BOT_MANAGEMENT_SCORE'] : null,
        'bot_verified' => $_SERVER['HTTP_CF_VERIFIED_BOT'] ?? null,
        'ja3_hash' => $_SERVER['HTTP_CF_JA3_HASH'] ?? null,
        'asn' => $_SERVER['HTTP_CF_ASN'] ?? null,
        'colo' => $_SERVER['HTTP_CF_COLO'] ?? null,
    ],
    'security' => [
        'validation_issues' => $validation_issues,
        'rate_limit' => [
            'attempts' => $rate_limit['attempts'],
            'remaining' => $rate_limit['remaining'],
            'blocked' => !$rate_limit['allowed'],
        ],
    ],
    'interaction' => 'page_view',
    'credentials' => null,
    'classification' => null,
];

// Process credential submission
if ($is_post && isset($_POST['log']) && isset($_POST['pwd']) && $rate_limit['allowed']) {
    $show_error = true;
    
    // Sanitize and extract credentials
    $username_raw = sanitizeForLog(substr($_POST['log'], 0, $CONFIG['max_username_log_length']));
    $password_raw = sanitizeForLog(substr($_POST['pwd'], 0, $CONFIG['max_password_log_length']));
    
    // Perform analysis
    $username_analysis = analyzeUsername($username_raw);
    $password_analysis = analyzePassword($password_raw);
    $wordlist_results = checkWordlists($username_raw, $password_raw, $CONFIG);
    $credential_hashes = generateCredentialHash($username_raw, $password_raw);
    $attack_classification = classifyAttackType($username_analysis, $password_analysis, $wordlist_results);
    
    // Handle password storage based on config
    $stored_password = $password_raw;
    $password_encrypted = null;
    
    if ($CONFIG['encrypt_passwords'] && !empty($CONFIG['encryption_key'])) {
        $password_encrypted = encryptData($password_raw, $CONFIG['encryption_key']);
        if ($password_encrypted) {
            $stored_password = '[ENCRYPTED]';
        }
    }
    
    // Update log entry
    $log_entry['interaction'] = 'credential_submission';
    
    $log_entry['credentials'] = [
        'username' => $username_raw,
        'password' => $stored_password,
        'password_encrypted' => $password_encrypted,
        'hashes' => $credential_hashes,
        'username_analysis' => $username_analysis,
        'password_analysis' => $password_analysis,
        'wordlist_match' => $wordlist_results,
        'remember_me' => isset($_POST['rememberme']),
        'redirect_to' => sanitizeForLog(substr($_POST['redirect_to'] ?? '', 0, 256)),
    ];
    
    $log_entry['classification'] = $attack_classification;
    
    // Clear sensitive variables from memory
    unset($username_raw, $password_raw, $password_encrypted);
}

// Write log (skip page views if configured)
if ($log_entry['interaction'] === 'credential_submission' || $CONFIG['log_page_views']) {
    writeLog($CONFIG['log_file'], $log_entry);
}

// Artificial delay to simulate real WordPress
$delay_ms = rand($CONFIG['artificial_delay_ms'][0], $CONFIG['artificial_delay_ms'][1]);
usleep($delay_ms * 1000);

// If rate limited, return 429
if (!$rate_limit['allowed']) {
    http_response_code(429);
    header('Retry-After: 3600');
}
?>
<!DOCTYPE html>
<html lang="en-US">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Log In &lsaquo; <?php echo htmlspecialchars($site); ?> &#8212; WordPress</title>
    <link rel="icon" href="https://s.w.org/favicon.ico" sizes="32x32">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: #f1f1f1;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            font-size: 13px;
            line-height: 1.4;
            min-height: 100vh;
        }
        #login {
            width: 320px;
            margin: 8% auto 0;
            padding: 20px 0;
        }
        #login h1 {
            text-align: center;
            margin-bottom: 24px;
        }
        #login h1 a {
            display: block;
            width: 84px;
            height: 84px;
            margin: 0 auto;
            background: url('https://s.w.org/images/wmark.png') no-repeat center;
            background-size: 84px;
            text-indent: -9999px;
            outline: none;
        }
        .login form {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 26px 24px 34px;
            box-shadow: 0 1px 3px rgba(0,0,0,.04);
        }
        .login form .input, 
        .login input[type="text"], 
        .login input[type="password"] {
            width: 100%;
            padding: 3px 5px;
            margin: 2px 0 16px;
            border: 1px solid #8c8f94;
            border-radius: 4px;
            background: #fff;
            color: #2c3338;
            font-size: 24px;
            line-height: 1.33;
            font-family: inherit;
        }
        .login form .input:focus,
        .login input[type="text"]:focus,
        .login input[type="password"]:focus {
            border-color: #2271b1;
            box-shadow: 0 0 0 1px #2271b1;
            outline: 2px solid transparent;
        }
        .login label {
            display: block;
            margin-bottom: 3px;
            font-size: 14px;
            font-weight: 600;
            color: #1e1e1e;
        }
        .user-pass-wrap { margin-bottom: 0; }
        .wp-pwd { position: relative; }
        .login .button-primary {
            background: #2271b1;
            border: 1px solid #2271b1;
            border-radius: 3px;
            color: #fff;
            text-decoration: none;
            font-size: 13px;
            line-height: 2.15384615;
            min-height: 32px;
            padding: 0 12px;
            cursor: pointer;
            width: 100%;
            margin-top: 16px;
            display: inline-block;
            text-align: center;
        }
        .login .button-primary:hover { background: #135e96; border-color: #135e96; }
        .login .button-primary:focus {
            background: #135e96;
            border-color: #135e96;
            box-shadow: 0 0 0 1px #fff, 0 0 0 3px #2271b1;
            outline: 2px solid transparent;
        }
        .forgetmenot { margin: 16px 0 0; }
        .forgetmenot label { font-size: 13px; font-weight: 400; display: inline; }
        .forgetmenot input[type="checkbox"] { margin: -3px 4px 0 0; vertical-align: middle; }
        #login_error, .login .message {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 12px;
            margin-bottom: 20px;
            word-wrap: break-word;
        }
        #login_error { border-left: 4px solid #d63638; }
        #login_error strong { color: #d63638; }
        #login_error a, .login .message a { color: #50575e; }
        .login .message { border-left: 4px solid #72aee6; }
        #nav, #backtoblog { text-align: center; margin: 16px 0; }
        #nav a, #backtoblog a { color: #50575e; text-decoration: none; font-size: 13px; }
        #nav a:hover, #backtoblog a:hover { color: #2271b1; }
        .privacy-policy-link { text-align: center; margin-top: 20px; }
        .privacy-policy-link a { color: #50575e; text-decoration: none; font-size: 12px; }
    </style>
</head>
<body class="login login-action-login wp-core-ui">
    <div id="login">
        <h1><a href="https://wordpress.org/">Powered by WordPress</a></h1>
        
        <?php if (!$rate_limit['allowed']): ?>
        <div class="message">
            <strong>Notice:</strong> Too many login attempts. Please try again later.
        </div>
        <?php elseif ($show_error): ?>
        <div id="login_error">
            <strong>Error:</strong> The username or password you entered is incorrect. 
            <a href="wp-login.php?action=lostpassword">Lost your password?</a>
        </div>
        <?php endif; ?>
        
        <form name="loginform" id="loginform" action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>" method="post">
            <p>
                <label for="user_login">Username or Email Address</label>
                <input type="text" name="log" id="user_login" class="input" value="" size="20" autocapitalize="off" autocomplete="username" required <?php echo $rate_limit['allowed'] ? '' : 'disabled'; ?>>
            </p>
            <div class="user-pass-wrap">
                <label for="user_pass">Password</label>
                <div class="wp-pwd">
                    <input type="password" name="pwd" id="user_pass" class="input password-input" value="" size="20" autocomplete="current-password" required <?php echo $rate_limit['allowed'] ? '' : 'disabled'; ?>>
                </div>
            </div>
            <p class="forgetmenot">
                <input name="rememberme" type="checkbox" id="rememberme" value="forever" <?php echo $rate_limit['allowed'] ? '' : 'disabled'; ?>>
                <label for="rememberme">Remember Me</label>
            </p>
            <p class="submit">
                <input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="Log In" <?php echo $rate_limit['allowed'] ? '' : 'disabled'; ?>>
                <input type="hidden" name="redirect_to" value="wp-admin/">
                <input type="hidden" name="testcookie" value="1">
            </p>
        </form>
        
        <p id="nav">
            <a href="wp-login.php?action=lostpassword">Lost your password?</a>
        </p>
        
        <p id="backtoblog">
            <a href="/">&larr; Go to <?php echo htmlspecialchars($site); ?></a>
        </p>
        
        <div class="privacy-policy-link">
            <a href="/privacy-policy/">Privacy Policy</a>
        </div>
    </div>

    <script>document.getElementById('user_login').focus();</script>
    
    <!--
    ============================================================================
    SECURITY RESEARCH HONEYPOT - ACADEMIC DISCLOSURE
    ============================================================================
    
    This endpoint is a honeypot deployed for academic security research.
    
    RESEARCH PROJECT:
      Course:      INST 570 - Information Security Ethics and Legal Aspects
      Researcher:  Brian Chaplow
      Contact:     security@brianchaplow.com
    
    DISCLOSURE:
      - This site does NOT run WordPress
      - This login page is NOT connected to any authentication system
      - All access attempts are logged for security research
      - Data is handled in accordance with research ethics guidelines
    
    ETHICAL FRAMEWORK:
      - No legitimate user has reason to access this endpoint
      - Credential capture serves legitimate security research purposes
      - Data retention limited to 90 days
      - Research findings will be anonymized and aggregated
    
    Request ID: <?php echo htmlspecialchars($request_id); ?>
    Session ID: <?php echo htmlspecialchars($session_id); ?>
    Timestamp:  <?php echo gmdate('Y-m-d\TH:i:s\Z'); ?>
    ============================================================================
    -->
</body>
</html>
