<?php
require __DIR__.'/app-bootstrap.php';
if(user()) redirect('account.php');
$err='';$notice='';$stage='request';$email=strtolower(trim((string)($_POST['email']??($_SESSION['otp_register_email']??''))));$name=trim((string)($_POST['name']??($_SESSION['otp_register_name']??'')));
if($_SERVER['REQUEST_METHOD']==='POST'){
    require_csrf();$action=$_POST['action']??'request';
    try{
        if($action==='request'){
            if($name===''||mb_strlen($name)>120)throw new RuntimeException('Enter your name.');
            request_email_otp($email,'register',$name);$_SESSION['otp_register_email']=$email;$_SESSION['otp_register_name']=$name;$stage='verify';$notice='We sent a 6-digit verification code to '.$email.'.';
        } elseif($action==='verify'){
            $stage='verify';$challenge=verify_email_otp($email,'register',(string)($_POST['code']??''));if(!$challenge)throw new RuntimeException('That code is invalid or has expired.');
            $display=trim((string)($challenge['display_name']??$name));if($display==='')throw new RuntimeException('Registration details have expired. Start again.');
            try{$pdo->prepare('INSERT INTO users(email,password_hash,display_name,email_verified_at) VALUES(?,?,?,NOW())')->execute([$email,password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT),$display]);}catch(PDOException $e){throw new RuntimeException('That email address is already registered.');}
            session_regenerate_id(true);$_SESSION['user_id']=(int)$pdo->lastInsertId();audit_event('registration_success',(int)$_SESSION['user_id']);unset($_SESSION['otp_register_email'],$_SESSION['otp_register_name']);redirect('account.php');
        } elseif($action==='resend'){
            request_email_otp($email,'register',$name);$stage='verify';$notice='A new verification code has been sent.';
        }
    }catch(Throwable $e){$err=$e->getMessage();if($action!=='request')$stage='verify';}
}
layout_header('Create account'); ?>
<form class="auth panel otp-auth" method="post" data-no-async>
  <span class="kicker">EMAIL VERIFIED ACCOUNT</span><h1>Create account</h1>
  <p class="muted">No password to remember. We verify your email with a one-time code and use the same method whenever you sign in. Unverified accounts are removed after 7 days if they are not activated.</p>
  <?php if($err):?><p class="error"><?=e($err)?></p><?php endif;?><?php if($notice):?><p class="success"><?=e($notice)?></p><?php endif;?>
  <?php if($stage==='request'):?>
    <label>Your name<input name="name" value="<?=e($name)?>" autocomplete="name" maxlength="120" required></label><label>Email address<input name="email" type="email" value="<?=e($email)?>" autocomplete="email" required></label>
    <input type="hidden" name="action" value="request"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><button class="button">Verify my email</button>
  <?php else:?>
    <input type="hidden" name="email" value="<?=e($email)?>"><input type="hidden" name="name" value="<?=e($name)?>"><label>6-digit verification code<input class="otp-code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" placeholder="000000" required autofocus></label>
    <input type="hidden" name="action" value="verify"><input type="hidden" name="csrf" value="<?=e(csrf_token())?>"><button class="button">Verify &amp; create account</button>
    <div class="otp-actions"><button class="link-button" type="submit" name="action" value="resend">Send a new code</button><a href="register">Start again</a></div>
  <?php endif;?>
  <p>Already registered? <a href="login">Sign in</a></p>
</form><?php layout_footer();
