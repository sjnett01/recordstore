<?php
require __DIR__.'/app-bootstrap.php';
if (user()) redirect('account.php');
if(isset($_GET['favourite'])){
    $pending=(int)($_GET['favourite']??0);
    if($pending>0)$_SESSION['pending_favourite_track']=$pending;
}
if(isset($_GET['next'])){
    $next=(string)$_GET['next'];
    if($next!==''&&!str_contains($next,'://')&&!str_starts_with($next,'//')&&!str_contains($next,"\n"))$_SESSION['login_next']=ltrim($next,'/');
}

$err = '';
$notice = '';
$stage = 'email';
$email = strtolower(trim((string)($_POST['email'] ?? ($_SESSION['otp_login_email'] ?? ''))));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = (string)($_POST['action'] ?? 'next');
    if(in_array($action,['next','send_otp','verify','resend','admin_password'],true) && login_rate_limited()) { http_response_code(429); throw new RuntimeException('Too many sign-in attempts. Please wait 15 minutes and try again.'); }

    try {
        if ($action === 'next') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Enter a valid email address.');
            }

            $st = $pdo->prepare('SELECT id,is_admin,email_verified_at,disabled_at,banned_at FROM users WHERE email=? LIMIT 1');
            $st->execute([$email]);
            $account = $st->fetch();
            $_SESSION['otp_login_email'] = $email;

            if ($account && empty($account['email_verified_at'])) {
                throw new RuntimeException('This account has not been verified. Please complete email verification before signing in.');
            }
            if ($account && empty($account['disabled_at']) && empty($account['banned_at']) && !empty($account['is_admin'])) {
                $stage = 'admin_choice';
            } else {
                request_email_otp($email, 'login');
                $stage = 'verify';
                $notice = 'If an account exists for that address, a 6-digit sign-in code has been sent.';
            }
        } elseif ($action === 'send_otp') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Enter a valid email address.');
            }
            request_email_otp($email, 'login');
            $_SESSION['otp_login_email'] = $email;
            $stage = 'verify';
            $notice = 'A 6-digit sign-in code has been sent.';
        } elseif ($action === 'verify') {
            $stage = 'verify';
            $challenge = verify_email_otp($email, 'login', (string)($_POST['code'] ?? ''));
            if (!$challenge) {
                throw new RuntimeException('That code is invalid or has expired. Request a new code and try again.');
            }
            $st = $pdo->prepare('SELECT id,is_admin,email_verified_at,disabled_at,banned_at FROM users WHERE email=? LIMIT 1');
            $st->execute([$email]);
            $account = $st->fetch();
            if (!$account || empty($account['email_verified_at']) || !empty($account['disabled_at']) || !empty($account['banned_at'])) {
                throw new RuntimeException('Account unavailable.');
            }
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$account['id'];
            record_user_session((int)$account['id']);
            save_pending_favourite((int)$account['id']);
            $pdo->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?')->execute([(int)$account['id']]);audit_event('login_success',(int)$account['id'],['method'=>'otp']);
            unset($_SESSION['otp_login_email']);
            $destination=$_SESSION['login_next']??(!empty($account['is_admin'])?'admin.php':'account.php');unset($_SESSION['login_next']);redirect($destination);
        } elseif ($action === 'resend') {
            request_email_otp($email, 'login');
            $_SESSION['otp_login_email'] = $email;
            $stage = 'verify';
            $notice = 'A new code has been sent.';
        } elseif ($action === 'show_password') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Enter a valid email address.');
            }
            $st = $pdo->prepare('SELECT id,is_admin,email_verified_at,disabled_at,banned_at FROM users WHERE email=? LIMIT 1');
            $st->execute([$email]);
            $account = $st->fetch();
            if (!$account || empty($account['email_verified_at']) || !empty($account['disabled_at']) || !empty($account['banned_at']) || empty($account['is_admin'])) {
                throw new RuntimeException('Password sign-in is available to administrators only.');
            }
            $_SESSION['otp_login_email'] = $email;
            $stage = 'password';
        } elseif ($action === 'admin_password') {
            $stage = 'password';
            $password = (string)($_POST['password'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
                throw new RuntimeException('Enter your administrator password.');
            }
            $st = $pdo->prepare('SELECT id,password_hash,is_admin,email_verified_at,disabled_at,banned_at FROM users WHERE email=? LIMIT 1');
            $st->execute([$email]);
            $admin = $st->fetch();
            if (!$admin || empty($admin['email_verified_at']) || !empty($admin['disabled_at']) || !empty($admin['banned_at']) || empty($admin['is_admin']) || empty($admin['password_hash']) || !password_verify($password, (string)$admin['password_hash'])) {
                usleep(250000);
                throw new RuntimeException('Invalid administrator password.');
            }
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$admin['id'];
            record_user_session((int)$admin['id']);
            save_pending_favourite((int)$admin['id']);
            $pdo->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?')->execute([(int)$admin['id']]);audit_event('login_success',(int)$admin['id'],['method'=>'password']);
            unset($_SESSION['otp_login_email']);
            $destination=$_SESSION['login_next']??'admin.php';unset($_SESSION['login_next']);redirect($destination);
        }
    } catch (Throwable $e) {
        audit_event('login_failed',null,['method'=>$action,'email'=>$email]);
        $err = $e->getMessage();
        if ($action === 'next') {
            $stage = 'email';
        }
    }
}

layout_header('Login'); ?>
<form class="auth panel otp-auth" method="post" data-no-async>
  <span class="kicker">SIGN IN</span>
  <h1>Welcome back</h1>
  <?php if ($err): ?><p class="error"><?=e($err)?></p><?php endif; ?>
  <?php if ($notice): ?><p class="success"><?=e($notice)?></p><?php endif; ?>

  <?php if ($stage === 'email'): ?>
    <p class="muted">Enter your email address to continue.</p>
    <label>Email address<input name="email" type="email" value="<?=e($email)?>" autocomplete="email" required autofocus></label>
    <input type="hidden" name="action" value="next">
    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
    <button class="button">Next</button>

  <?php elseif ($stage === 'admin_choice'): ?>
    <input type="hidden" name="email" value="<?=e($email)?>">
    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
    <p class="muted">Administrator account recognised for <strong><?=e($email)?></strong>.</p>
    <p>Choose how you want to sign in.</p>
    <div class="auth-choice-grid">
      <button class="button" type="submit" name="action" value="send_otp">Send OTP</button>
      <button class="button secondary" type="submit" name="action" value="show_password">Use password</button>
    </div>
    <p class="otp-actions"><a href="login">Use another email</a></p>

  <?php elseif ($stage === 'password'): ?>
    <input type="hidden" name="email" value="<?=e($email)?>">
    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
    <p class="muted">Signing in as <strong><?=e($email)?></strong></p>
    <label>Password<input name="password" type="password" autocomplete="current-password" required autofocus></label>
    <button class="button" type="submit" name="action" value="admin_password">Sign in with password</button>
    <div class="otp-actions">
      <button class="link-button" type="submit" name="action" value="send_otp">Use OTP instead</button>
      <a href="login">Use another email</a>
    </div>

  <?php else: ?>
    <input type="hidden" name="email" value="<?=e($email)?>">
    <input type="hidden" name="csrf" value="<?=e(csrf_token())?>">
    <p class="muted">Enter the 6-digit code sent to <strong><?=e($email)?></strong>.</p>
    <label>6-digit code<input class="otp-code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="000000" required autofocus></label>
    <button class="button" type="submit" name="action" value="verify">Verify &amp; sign in</button>
    <div class="otp-actions">
      <button class="link-button" type="submit" name="action" value="resend">Send a new code</button>
      <a href="login">Use another email</a>
    </div>
  <?php endif; ?>

  <p>New to <?=e(site_name())?>? <a href="register">Create account</a></p>
</form>
<?php layout_footer();
