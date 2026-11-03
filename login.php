<?php require_once __DIR__.'/includes/auth.php';
if(!empty($_SESSION['uid'])) redirect(home_for($_SESSION['role']));
if($_SERVER['REQUEST_METHOD']==='POST'){
  $s=$pdo->prepare("SELECT * FROM users WHERE email=?"); $s->execute([post('email')]); $u=$s->fetch();
  if($u){ // brute-force guard: 5 failed attempts in 15 minutes lock sign-in for that account until the window passes
    $lk=$pdo->prepare("SELECT COUNT(*) FROM audit_logs WHERE user_id=? AND action='LOGIN_FAILED' AND created_at>=NOW()-INTERVAL 15 MINUTE AND created_at>COALESCE((SELECT MAX(a2.created_at) FROM audit_logs a2 WHERE a2.action='PASSWORD_RESET' AND a2.record_id=audit_logs.user_id),'1970-01-01')"); $lk->execute([$u['user_id']]); // an administrator password reset clears the lock
    if((int)$lk->fetchColumn()>=5){ flash('Too many failed attempts. Try again in 15 minutes.','err'); redirect('login.php'); }
  }
  if($u && password_verify($_POST['password']??'',$u['password'])){
    if($u['status']==='SUSPENDED'){ flash('Your account is suspended.','err'); redirect('login.php'); }
    session_regenerate_id(true);
    $_SESSION['uid']=$u['user_id']; $_SESSION['role']=$u['role']; $_SESSION['name']=$u['full_name'];
    audit($pdo,$u['user_id'],'LOGIN','users',$u['user_id'],'User logged in.');
    redirect(home_for($u['role']));
  }
  if($u) audit($pdo,$u['user_id'],'LOGIN_FAILED','users',$u['user_id'],'Wrong password.');
  else { try{ audit($pdo,null,'LOGIN_UNKNOWN_EMAIL','users',null,'Sign-in attempt with an unregistered e-mail: '.mb_substr(post('email'),0,100)); }catch(Throwable $e){} } // needs database/admin_upgrade.sql; never blocks the login page
  flash('Invalid email or password.','err'); redirect('login.php');
}
$title='Login'; require 'includes/header.php'; ?>
<style>
.wrap:has(.lg)>.page-title{display:none}
/* full-page dark backdrop behind header/footer-free area */
.lg-bg{position:fixed;inset:0;z-index:-1;overflow:hidden;background:radial-gradient(circle at 12% 15%,rgba(255,90,114,.45),transparent 40%),radial-gradient(circle at 90% 85%,rgba(91,75,219,.4),transparent 42%),linear-gradient(135deg,#2a030b 0%,#6d0a1d 45%,#b80f2b 100%)}
.lg-bg i{position:absolute;display:block;background:linear-gradient(135deg,rgba(255,255,255,.22),rgba(255,255,255,.03));border-radius:50% 0 50% 50%;transform:rotate(-45deg);animation:lgf 9s ease-in-out infinite}
.lg-bg i:nth-child(1){width:90px;height:90px;left:7%;top:20%}
.lg-bg i:nth-child(2){width:46px;height:46px;left:22%;top:72%;animation-delay:-3s}
.lg-bg i:nth-child(3){width:130px;height:130px;right:8%;top:14%;animation-delay:-5s}
.lg-bg i:nth-child(4){width:60px;height:60px;right:20%;top:68%;animation-delay:-1s}
.lg-bg i:nth-child(5){width:34px;height:34px;left:48%;top:10%;animation-delay:-7s}
.lg-bg svg{position:absolute;left:0;right:0;bottom:12%;width:100%;height:90px;opacity:.16}
.lg-bg svg path{fill:none;stroke:#fff;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:1;stroke-dashoffset:1;animation:lge 6s ease-in-out infinite}

.lg{display:grid;grid-template-columns:1fr 1.05fr;max-width:940px;margin:18px auto 30px;border-radius:28px;overflow:hidden;border:1px solid rgba(255,255,255,.18);box-shadow:0 40px 90px rgba(0,0,0,.45);animation:lgin .7s ease both}
/* left brand panel */
.lg-brand{position:relative;padding:44px 38px;color:#fff;background:linear-gradient(160deg,rgba(255,255,255,.12),rgba(255,255,255,.03));display:flex;flex-direction:column;justify-content:center;gap:18px;overflow:hidden}
.lg-brand:after{content:"";position:absolute;right:-70px;bottom:-80px;width:230px;height:230px;border-radius:50%;background:rgba(255,255,255,.08)}
.lg-logo{width:74px;height:74px;border-radius:50%;display:grid;place-items:center;background:linear-gradient(135deg,#ff5a72,#c8102e);box-shadow:0 0 0 8px rgba(255,255,255,.1),0 18px 34px rgba(0,0,0,.35);animation:lgb 2s ease-in-out infinite}
.lg-logo svg{width:34px;height:34px;fill:#fff}
.lg-brand h2{margin:0;font-size:1.9rem;line-height:1.15;letter-spacing:-.7px}
.lg-brand h2 em{font-style:normal;color:#ffd27a}
.lg-brand p{margin:0;color:#ffd9df;line-height:1.65;font-size:.95rem}
.lg-pts{list-style:none;margin:4px 0 0;padding:0;display:grid;gap:10px;position:relative;z-index:2}
.lg-pts li{display:flex;align-items:center;gap:11px;font-size:.88rem;font-weight:600;color:#fff}
.lg-pts b{width:28px;height:28px;border-radius:9px;display:grid;place-items:center;background:rgba(255,255,255,.18);font-size:.9rem}
.lg-tags{display:flex;gap:8px;flex-wrap:wrap;position:relative;z-index:2}
.lg-tags span{width:42px;height:42px;border-radius:50%;display:grid;place-items:center;font-weight:800;font-size:.78rem;color:#fff;border:2px solid rgba(255,255,255,.8);background:rgba(255,255,255,.12)}

/* right form panel (dark glass) */
.lg-form{padding:44px 40px;background:rgba(20,6,12,.62);backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px);color:#fff}
.lg-form h1{margin:0 0 4px;font-size:1.65rem;letter-spacing:-.5px;color:#fff}
.lg-form .sub{margin:0 0 22px;color:#f3c3cb;font-size:.92rem}
.lg-form label{display:block;margin:16px 0 7px;font-size:.78rem;font-weight:800;letter-spacing:1px;text-transform:uppercase;color:#ffd0d7}
.lg-f{position:relative}
.lg-f .ic{position:absolute;left:15px;top:50%;transform:translateY(-50%);font-size:1.05rem;opacity:.75;pointer-events:none}
.lg-form input{width:100%;padding:14px 46px 14px 44px;border-radius:14px;border:1.5px solid rgba(255,255,255,.2);background:rgba(255,255,255,.08);color:#fff;font:inherit;transition:.2s}
.lg-form input::placeholder{color:rgba(255,255,255,.45)}
.lg-form input:focus{outline:0;border-color:#ff7a8d;background:rgba(255,255,255,.14);box-shadow:0 0 0 4px rgba(255,90,114,.22)}
.lg-form input:-webkit-autofill{-webkit-text-fill-color:#fff;caret-color:#fff;-webkit-box-shadow:0 0 0 40px #4a1320 inset;transition:background-color 9999s}
.lg-eye{position:absolute;right:8px;top:50%;transform:translateY(-50%);border:0;background:none;color:#fff;cursor:pointer;font-size:1.05rem;padding:8px;border-radius:10px;opacity:.75}
.lg-eye:hover{opacity:1;background:rgba(255,255,255,.12)}
.lg-caps{display:none;margin-top:8px;font-size:.78rem;font-weight:700;color:#ffd27a}
.lg-btn{position:relative;width:100%;margin-top:22px;border:0;cursor:pointer;font:inherit;font-weight:800;font-size:1rem;color:#fff;padding:15px;border-radius:14px;background:linear-gradient(135deg,#ff4d68,#c8102e);box-shadow:0 14px 28px rgba(200,16,46,.5);transition:.25s;overflow:hidden}
.lg-btn:hover{transform:translateY(-3px);box-shadow:0 20px 36px rgba(200,16,46,.6)}
.lg-btn:after{content:"";position:absolute;top:0;left:-60%;width:40%;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.35),transparent);transform:skewX(-20deg);transition:.6s}
.lg-btn:hover:after{left:130%}
.lg-btn.busy{pointer-events:none;opacity:.8}
.lg-links{margin-top:20px;display:grid;gap:10px;font-size:.86rem;color:#f3c3cb;line-height:1.55}
.lg-help{display:flex;gap:10px;align-items:flex-start;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.14);border-radius:12px;padding:11px 13px}
.lg-links a{color:#ffd27a;font-weight:800;text-decoration:none}.lg-links a:hover{text-decoration:underline}

@keyframes lgf{0%,100%{transform:rotate(-45deg) translateY(0)}50%{transform:rotate(-45deg) translateY(-26px)}}
@keyframes lge{0%{stroke-dashoffset:1}60%,100%{stroke-dashoffset:0}}
@keyframes lgb{0%,100%{transform:scale(1)}50%{transform:scale(1.07)}}
@keyframes lgin{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:none}}
@media(prefers-reduced-motion:reduce){.lg,.lg *,.lg-bg *{animation:none!important;transition:none!important}.lg-bg svg path{stroke-dashoffset:0}}
@media(max-width:820px){.lg{grid-template-columns:1fr;max-width:480px}.lg-brand{padding:28px 26px}.lg-pts,.lg-tags,.lg-brand p{display:none}.lg-form{padding:30px 26px}}
</style>

<div class="lg-bg" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i>
  <svg viewBox="0 0 1200 90" preserveAspectRatio="none"><path pathLength="1" d="M0 50H430l14-30 16 60 18-76 14 46h110l14-20 16 44 18-56 14 32H1200"/></svg>
</div>

<div class="lg">
  <aside class="lg-brand">
    <div class="lg-logo"><svg viewBox="0 0 24 24"><path d="M12 2.7s6 6.2 6 10.6a6 6 0 0 1-12 0c0-4.4 6-10.6 6-10.6z"/></svg></div>
    <h2>Every second counts.<br><em>Every donor matters.</em></h2>
    <p>Sign in to coordinate emergency requests, respond to matches and keep blood stock under control.</p>
    <ul class="lg-pts">
      <li><b>✓</b>Verified donors &amp; organizations</li>
      <li><b>📍</b>Nearest-first donor matching</li>
      <li><b>⚡</b>Urgency-aware emergency requests</li>
    </ul>
    <div class="lg-tags"><span>A+</span><span>B&minus;</span><span>AB+</span><span>O&minus;</span></div>
  </aside>

  <div class="lg-form">
    <h1>Sign in to SEBD</h1>
    <p class="sub">Enter your registered email and password.</p>
    <form method="post" id="lg-form"><?= csrf_field() ?>
      <label for="lg-email">Email</label>
      <div class="lg-f"><span class="ic">✉️</span><input id="lg-email" type="email" name="email" placeholder="you@example.com" required autofocus autocomplete="username"></div>
      <label for="lg-pass">Password</label>
      <div class="lg-f"><span class="ic">🔒</span><input id="lg-pass" type="password" name="password" placeholder="••••••••" required autocomplete="current-password"><button type="button" class="lg-eye" id="lg-eye" aria-label="Show password">👁</button></div>
      <div class="lg-caps" id="lg-caps">⚠ Caps Lock is on</div>
      <button class="lg-btn" id="lg-btn">Login →</button>
      <div class="lg-links">
        <div class="lg-help"><span>🔑</span><span>Forgot your password? Contact the SEBD administrator for a temporary one.</span></div>
        <span>New to SEBD? <a href="register.php">Create an account</a></span>
      </div>
    </form>
  </div>
</div>

<script>
(function(){
  var p=document.getElementById('lg-pass'),e=document.getElementById('lg-eye'),c=document.getElementById('lg-caps'),f=document.getElementById('lg-form'),b=document.getElementById('lg-btn');
  e.addEventListener('click',function(){var s=p.type==='password';p.type=s?'text':'password';e.textContent=s?'🙈':'👁';e.setAttribute('aria-label',s?'Hide password':'Show password')});
  p.addEventListener('keyup',function(ev){c.style.display=(ev.getModifierState&&ev.getModifierState('CapsLock'))?'block':'none'});
  p.addEventListener('blur',function(){c.style.display='none'});
  f.addEventListener('submit',function(){b.classList.add('busy');b.textContent='Signing in…'});
})();
</script>
<?php require 'includes/footer.php';