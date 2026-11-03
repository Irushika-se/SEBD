<?php require_once __DIR__.'/includes/auth.php';
if(!empty($_SESSION['uid'])) redirect(home_for($_SESSION['role']));
if($_SERVER['REQUEST_METHOD']==='POST'){
  $role=post('role'); $err=[];
  $name=post('full_name'); $email=post('email'); $pw=$_POST['password']??''; $pwConfirm=$_POST['password_confirm']??''; $phone=post('phone');
  if(!in_array($role,['DONOR','HEALTHCARE'])) $err[]='Choose a role.';
  if(mb_strlen($name)<2||mb_strlen($name)>100||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>100) $err[]='Enter a valid name (2–100 characters) and email address.';
  if(strlen($pw)<8 || !preg_match('/[A-Za-z]/',$pw) || !preg_match('/[0-9]/',$pw)) $err[]='Password must be at least 8 characters and contain at least one letter and one number.';
  if(!array_key_exists(post('city'),SL_CITIES)) $err[]='Select a city from the provided list.';
  if($pw!==$pwConfirm) $err[]='Passwords do not match.';
  if(empty($_POST['privacy_consent'])) $err[]='Please confirm that you have read the privacy notice and agree to the use of your information for donor coordination.';
  if(!preg_match('/^[0-9]{10}$/',$phone)) $err[]='Phone number must be exactly 10 digits.';
  if(mb_strlen(post('address'))>255) $err[]='Address must be 255 characters or fewer.';
  $c=$pdo->prepare("SELECT 1 FROM users WHERE email=?"); $c->execute([$email]); if($c->fetch()) $err[]='Email already registered.';
  $c=$pdo->prepare("SELECT 1 FROM users WHERE phone=?"); $c->execute([$phone]); if($c->fetch()) $err[]='Phone number already registered.';
  [$lat,$lng]=city_coords(post('city')); $prec=0;
  if($role==='DONOR'){
    [$lat,$lng,$prec,$gerr]=location_from_post(post('city')); if($gerr) $err[]=$gerr; // optional precise position, else city centre
    if(!in_array(post('blood_group'),BLOOD)) $err[]='Select blood group.';
    $dob=post('date_of_birth');
    $dobObj=DateTime::createFromFormat('!Y-m-d',$dob);
    if(!$dobObj || $dobObj->format('Y-m-d')!==$dob || $dob>date('Y-m-d') || !donor_age_ok($dob,post('last_donation_date')!=='')) $err[]='Donors must be '.DONOR_MIN_AGE.'-'.DONOR_MAX_AGE_REGULAR.' years old (first-time donors '.DONOR_MIN_AGE.'-'.DONOR_MAX_AGE_FIRST.'), with a valid date of birth. If you have donated before, enter your last donation date.';
    if(!is_numeric(post('weight_kg')) || !donor_weight_ok(post('weight_kg')) || (float)post('weight_kg')>200) $err[]='Donors must weigh over '.DONOR_MIN_WEIGHT_KG.' kg (up to 200 kg).';
    if(!in_array(post('gender'),['Male','Female','Other'],true)) $err[]='Select an allowed gender option.';
    $lastDate=post('last_donation_date'); $lastDateObj=$lastDate!==''?DateTime::createFromFormat('!Y-m-d',$lastDate):null;
    if($lastDate!=='' && (!$lastDateObj || $lastDateObj->format('Y-m-d')!==$lastDate || $lastDate>date('Y-m-d'))) $err[]='Last donation date must be valid and cannot be in the future.';
    if($lastDate!=='' && $lastDateObj && isset($dobObj) && $lastDateObj < (clone $dobObj)->modify('+18 years')) $err[]='Last donation date cannot be before your 18th birthday.';
    if(post('preferred_organization_id')!=='' && !ctype_digit(post('preferred_organization_id'))) $err[]='Choose a valid preferred organization.';
    if(post('preferred_organization_id')!==''){ $pref=$pdo->prepare("SELECT COUNT(*) FROM healthcare_organizations WHERE organization_id=? AND verification_status='VERIFIED'"); $pref->execute([(int)post('preferred_organization_id')]); if(!(int)$pref->fetchColumn()) $err[]='Preferred organization must be verified.'; }
  } elseif($role==='HEALTHCARE'){
    if(post('organization_name')===''||post('registration_number')==='') $err[]='Organization name and registration number are required.';
    if(mb_strlen(post('organization_name'))>150) $err[]='Organization name must be 150 characters or fewer.';
    if(mb_strlen(post('registration_number'))>100) $err[]='Registration number must be 100 characters or fewer.';
    if(!in_array(post('organization_type'),['Blood Bank','Hospital'],true)) $err[]='Select an allowed organization type.';
    $c=$pdo->prepare("SELECT 1 FROM healthcare_organizations WHERE LOWER(organization_name)=LOWER(?) AND city=?"); $c->execute([post('organization_name'),post('city')]); if($c->fetch()) $err[]='An organization with this name is already registered in this city.';
    $cert=org_cert_check($_FILES['certificate']??null,$err);
    $c=$pdo->prepare("SELECT 1 FROM healthcare_organizations WHERE registration_number=?"); $c->execute([post('registration_number')]); if($c->fetch()) $err[]='Registration number already exists.';
  }
  if(!$err){
    $certFile=null;
    try{
      if($role==='HEALTHCARE'){ $certFile=org_cert_save($cert); if(!$certFile) throw new PDOException('Certificate could not be saved.'); }
      $pdo->beginTransaction();
      $pdo->prepare("INSERT INTO users(full_name,email,password,role,phone,status) VALUES(?,?,?,?,?,'ACTIVE')")->execute([$name,$email,password_hash($pw,PASSWORD_DEFAULT),$role,$phone]);
      $uid=$pdo->lastInsertId();
      if($role==='DONOR'){
        $pdo->prepare("INSERT INTO donors(user_id,blood_group,date_of_birth,weight_kg,gender,address,city,latitude,longitude,location_precise,last_donation_date,recent_illness,medication,preferred_organization_id) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
          ->execute([$uid,post('blood_group'),post('date_of_birth'),post('weight_kg'),post('gender'),post('address'),post('city'),$lat,$lng,$prec,post('last_donation_date')?:null,post('recent_illness')?:null,post('medication')?:null,(int)post('preferred_organization_id')?:null]);
        $did=$pdo->lastInsertId();
        $admins=$pdo->query("SELECT user_id FROM users WHERE role='ADMIN' AND status='ACTIVE'")->fetchAll(PDO::FETCH_COLUMN); foreach($admins as $au) notify($pdo,(int)$au,'NEW_DONOR','New donor registration',"{$name} registered and is awaiting review.");
        notify($pdo,(int)$uid,'WELCOME','Welcome to SEBD','Welcome! Your donor account is pending administrator review and checkup scheduling.');
        $pdo->prepare("INSERT INTO donor_availability_history(donor_id,availability_status) VALUES(?,'UNAVAILABLE')")->execute([$did]);
      } else {
        $pdo->prepare("INSERT INTO healthcare_organizations(user_id,organization_name,organization_type,registration_number,address,city,phone,latitude,longitude,certificate_file) VALUES(?,?,?,?,?,?,?,?,?,?)")
          ->execute([$uid,post('organization_name'),post('organization_type'),post('registration_number'),post('address'),post('city'),$phone,$lat,$lng,$certFile]);
        $admins=$pdo->query("SELECT user_id FROM users WHERE role='ADMIN' AND status='ACTIVE'")->fetchAll(PDO::FETCH_COLUMN); foreach($admins as $au) notify($pdo,(int)$au,'NEW_ORG','New organization registration',post('organization_name')." registered and is waiting for certificate review and verification.");
        notify($pdo,(int)$uid,'WELCOME','Welcome to SEBD','Welcome! Your organization is pending administrator verification. You can raise requests and manage inventory once it is verified.');
      }
      audit($pdo,$uid,'REGISTER','users',$uid,"New $role registered; privacy and donor-coordination consent recorded.");
      $pdo->commit(); flash('Registration successful. Please login. An administrator will verify your account.'); redirect('login.php');
    }catch(PDOException $e){ if($pdo->inTransaction()) $pdo->rollBack(); org_cert_delete($certFile); $err[]='Could not save registration.'; }
  }
  flash(implode(' ',$err),'err'); $_SESSION['old']=$_POST; unset($_SESSION['old']['password'],$_SESSION['old']['password_confirm'],$_SESSION['old']['csrf']); redirect('register.php');
}
$old=$_SESSION['old']??[]; unset($_SESSION['old']); $v=fn($k)=>h($old[$k]??'');
$title='Register'; require 'includes/header.php'; ?>
<style>
.wrap:has(.rg)>.page-title{display:none}
.rg-bg{position:fixed;inset:0;z-index:-1;overflow:hidden;background:radial-gradient(circle at 10% 12%,rgba(255,90,114,.42),transparent 40%),radial-gradient(circle at 92% 88%,rgba(91,75,219,.4),transparent 42%),linear-gradient(135deg,#2a030b 0%,#6d0a1d 45%,#b80f2b 100%)}
.rg-bg i{position:absolute;display:block;background:linear-gradient(135deg,rgba(255,255,255,.2),rgba(255,255,255,.03));border-radius:50% 0 50% 50%;transform:rotate(-45deg);animation:rgf 9s ease-in-out infinite}
.rg-bg i:nth-child(1){width:90px;height:90px;left:4%;top:18%}.rg-bg i:nth-child(2){width:46px;height:46px;left:14%;top:78%;animation-delay:-3s}.rg-bg i:nth-child(3){width:130px;height:130px;right:5%;top:12%;animation-delay:-5s}.rg-bg i:nth-child(4){width:60px;height:60px;right:12%;top:70%;animation-delay:-1s}

.rg{display:grid;grid-template-columns:340px 1fr;max-width:1180px;margin:10px auto 30px;border-radius:28px;overflow:hidden;border:1px solid rgba(255,255,255,.18);box-shadow:0 40px 90px rgba(0,0,0,.45);animation:rgin .7s ease both;color:#fff}
.rg *{box-sizing:border-box}

/* left: brand + steps */
.rg-side{position:relative;padding:40px 30px;background:linear-gradient(170deg,rgba(255,255,255,.13),rgba(255,255,255,.03));overflow:hidden}
.rg-side:after{content:"";position:absolute;right:-80px;bottom:-90px;width:250px;height:250px;border-radius:50%;background:rgba(255,255,255,.07)}
.rg-sticky{position:sticky;top:90px;display:flex;flex-direction:column;gap:18px;position:relative;z-index:2}
.rg-logo{width:68px;height:68px;border-radius:50%;display:grid;place-items:center;background:linear-gradient(135deg,#ff5a72,#c8102e);box-shadow:0 0 0 7px rgba(255,255,255,.1),0 16px 30px rgba(0,0,0,.35);animation:rgb 2s ease-in-out infinite}
.rg-logo svg{width:30px;height:30px;fill:#fff}
.rg-side h2{margin:0;font-size:1.7rem;line-height:1.15;letter-spacing:-.6px;color:#fff}
.rg-side h2 em{font-style:normal;color:#ffd27a}
.rg-side>div>p{margin:0;color:#ffd9df;line-height:1.6;font-size:.92rem}
.rg-steps{list-style:none;margin:6px 0 0;padding:0;display:grid;gap:0}
.rg-steps li{position:relative;display:flex;gap:13px;padding:0 0 18px}
.rg-steps li:not(:last-child):before{content:"";position:absolute;left:15px;top:32px;bottom:2px;width:2px;background:rgba(255,255,255,.22)}
.rg-steps b{width:32px;height:32px;flex:none;border-radius:50%;display:grid;place-items:center;font-size:.85rem;background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.35)}
.rg-steps li:first-child b{background:#ff4d68;border-color:#ff4d68;box-shadow:0 6px 14px rgba(255,77,104,.5)}
.rg-steps strong{display:block;font-size:.92rem;color:#fff}
.rg-steps span{display:block;font-size:.8rem;color:#ffd0d7;line-height:1.45}
.rg-tags{display:flex;gap:8px}
.rg-tags span{width:40px;height:40px;border-radius:50%;display:grid;place-items:center;font-weight:800;font-size:.76rem;border:2px solid rgba(255,255,255,.8);background:rgba(255,255,255,.12)}

/* right: form */
.rg-main{padding:38px 40px;background:rgba(20,6,12,.64);backdrop-filter:blur(18px);-webkit-backdrop-filter:blur(18px)}
.rg-main h1{margin:0 0 4px;font-size:1.65rem;letter-spacing:-.5px;color:#fff}
.rg-sub{margin:0 0 22px;color:#f3c3cb;font-size:.92rem}
.rg-h{display:flex;align-items:center;gap:11px;margin:28px 0 14px;font-size:1.02rem;font-weight:800;color:#fff}
.rg-h:first-of-type{margin-top:0}
.rg-h i{font-style:normal;width:30px;height:30px;border-radius:10px;display:grid;place-items:center;font-size:.95rem;background:rgba(255,255,255,.16)}
.rg-h:after{content:"";flex:1;height:1px;background:rgba(255,255,255,.16)}

.rg-roles{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.rg-rc{position:relative;display:flex;gap:13px;align-items:center;padding:15px 16px;border-radius:16px;border:1.5px solid rgba(255,255,255,.2);background:rgba(255,255,255,.06);cursor:pointer;transition:.25s;margin:0!important;text-transform:none!important;letter-spacing:0!important}
.rg-rc input{position:absolute;opacity:0;width:1px;height:1px}
.rg-rc .ic{width:44px;height:44px;flex:none;border-radius:13px;display:grid;place-items:center;font-size:1.3rem;background:rgba(255,255,255,.12)}
.rg-rc b{display:block;font-size:.95rem;color:#fff}
.rg-rc small{display:block;font-size:.78rem;color:#f3c3cb;line-height:1.35;margin-top:1px}
.rg-rc:hover{border-color:rgba(255,255,255,.5);transform:translateY(-2px)}
.rg-rc:has(input:checked){border-color:#ff7a8d;background:linear-gradient(135deg,rgba(255,77,104,.32),rgba(200,16,46,.2));box-shadow:0 10px 24px rgba(200,16,46,.35)}
.rg-rc:has(input:checked) .ic{background:#ff4d68}
.rg-rc:has(input:checked):after{content:"✓";position:absolute;right:12px;top:10px;width:20px;height:20px;border-radius:50%;background:#fff;color:#c8102e;font-size:.72rem;font-weight:800;display:grid;place-items:center}
.rg-rc:has(input:focus-visible){outline:3px solid #ffd27a}

.rg-g{display:grid;grid-template-columns:repeat(3,1fr);gap:16px 18px}
.rg-g.two{grid-template-columns:repeat(2,1fr)}
.rg-g>div{min-width:0}
.rg-main label.l{display:block;margin:0 0 7px;font-size:.74rem;font-weight:800;letter-spacing:.9px;text-transform:uppercase;color:#ffd0d7}
.rg-main input:not([type=radio]):not([type=checkbox]):not([type=hidden]),.rg-main select{width:100%;min-height:46px;padding:11px 14px;border-radius:13px;border:1.5px solid rgba(255,255,255,.2);background:rgba(255,255,255,.08);color:#fff;font:inherit;transition:.2s;color-scheme:dark}
.rg-main input::placeholder{color:rgba(255,255,255,.4)}
.rg-main input:focus,.rg-main select:focus{outline:0;border-color:#ff7a8d;background:rgba(255,255,255,.14);box-shadow:0 0 0 4px rgba(255,90,114,.22)}
.rg-main select option{background:#2b0d16;color:#fff}
.rg-main input:-webkit-autofill{-webkit-text-fill-color:#fff;caret-color:#fff;-webkit-box-shadow:0 0 0 40px #4a1320 inset}
.rg-main input[type=file]{padding:9px 12px;font-size:.85rem}
.rg-main input[type=file]::file-selector-button{margin-right:12px;border:0;border-radius:9px;padding:7px 13px;font:inherit;font-weight:700;background:#ff4d68;color:#fff;cursor:pointer}
.rg-hint{display:block;margin-top:6px;font-size:.76rem;color:#f3c3cb;line-height:1.45}
.rg-pw{position:relative}.rg-pw input{padding-right:48px!important}
.rg-eye{position:absolute;right:6px;top:50%;transform:translateY(-50%);border:0;background:none;color:#fff;cursor:pointer;padding:8px;border-radius:10px;opacity:.75;font-size:1rem}
.rg-eye:hover{opacity:1;background:rgba(255,255,255,.12)}
.rg-meter{display:grid;grid-template-columns:repeat(4,1fr);gap:4px;margin-top:8px}
.rg-meter i{height:5px;border-radius:5px;background:rgba(255,255,255,.18);transition:.3s}
.rg-meter[data-s="1"] i:nth-child(-n+1){background:#ff5a6e}.rg-meter[data-s="2"] i:nth-child(-n+2){background:#ffb020}.rg-meter[data-s="3"] i:nth-child(-n+3){background:#c6e24a}.rg-meter[data-s="4"] i{background:#4ade80}
.rg-match{display:block;margin-top:6px;font-size:.76rem;font-weight:700;min-height:1em}
.rg-match.ok{color:#4ade80}.rg-match.bad{color:#ffb4bf}

.rg-panel{border-radius:20px;padding:22px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.14);margin-top:26px}
.rg-panel .rg-h{margin-top:0}
.rg-geo{display:inline-flex;align-items:center;gap:7px;min-height:46px;width:100%;justify-content:center;border-radius:13px;border:1.5px dashed rgba(255,255,255,.4);background:rgba(255,255,255,.06);color:#fff;font:inherit;font-weight:700;font-size:.85rem;cursor:pointer;transition:.2s}
.rg-geo:hover{background:rgba(255,255,255,.14);border-color:#fff}
.rg-note{margin:14px 0 0;font-size:.84rem;color:#f3c3cb;display:flex;gap:10px;align-items:flex-start;background:rgba(255,255,255,.06);border-radius:12px;padding:11px 13px}

.rg-consent{display:flex;gap:13px;align-items:flex-start;margin:24px 0 0!important;padding:15px 16px;border-radius:15px;border:1.5px solid rgba(255,255,255,.2);background:rgba(255,255,255,.06);cursor:pointer;font-size:.84rem;line-height:1.6;color:#fde7ea;text-transform:none!important;letter-spacing:0!important;font-weight:500!important}
.rg-consent input{width:20px!important;min-height:20px!important;height:20px;flex:none;margin-top:2px;accent-color:#ff4d68;padding:0}
.rg-consent:has(input:checked){border-color:#4ade80;background:rgba(74,222,128,.1)}
.rg-act{display:flex;gap:12px;flex-wrap:wrap;margin-top:22px}
.rg-btn{position:relative;overflow:hidden;border:0;cursor:pointer;font:inherit;font-weight:800;font-size:1rem;color:#fff;padding:15px 34px;border-radius:14px;background:linear-gradient(135deg,#ff4d68,#c8102e);box-shadow:0 14px 28px rgba(200,16,46,.5);transition:.25s}
.rg-btn:hover{transform:translateY(-3px);box-shadow:0 20px 36px rgba(200,16,46,.6)}
.rg-btn:after{content:"";position:absolute;top:0;left:-60%;width:40%;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.35),transparent);transform:skewX(-20deg);transition:.6s}
.rg-btn:hover:after{left:130%}.rg-btn.busy{pointer-events:none;opacity:.8}
.rg-ghost{display:inline-flex;align-items:center;padding:15px 26px;border-radius:14px;border:1.5px solid rgba(255,255,255,.4);color:#fff;font-weight:700;text-decoration:none;transition:.2s}
.rg-ghost:hover{background:#fff;color:#c8102e}

@keyframes rgf{0%,100%{transform:rotate(-45deg) translateY(0)}50%{transform:rotate(-45deg) translateY(-26px)}}
@keyframes rgb{0%,100%{transform:scale(1)}50%{transform:scale(1.07)}}
@keyframes rgin{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:none}}
@media(prefers-reduced-motion:reduce){.rg,.rg *,.rg-bg *{animation:none!important;transition:none!important}}
@media(max-width:1020px){.rg{grid-template-columns:1fr}.rg-side{padding:26px}.rg-steps,.rg-tags,.rg-side>div>p{display:none}.rg-sticky{position:static}.rg-g{grid-template-columns:1fr 1fr}}
@media(max-width:620px){.rg-main{padding:26px 20px}.rg-g,.rg-g.two,.rg-roles{grid-template-columns:1fr}.rg-act>*{width:100%;justify-content:center}}
</style>

<div class="rg-bg" aria-hidden="true"><i></i><i></i><i></i><i></i></div>

<div class="rg">
  <aside class="rg-side"><div class="rg-sticky">
    <div class="rg-logo"><svg viewBox="0 0 24 24"><path d="M12 2.7s6 6.2 6 10.6a6 6 0 0 1-12 0c0-4.4 6-10.6 6-10.6z"/></svg></div>
    <h2>Join the network that <em>saves lives</em></h2>
    <p>Register as a donor or a healthcare organization. Every account is reviewed by an administrator before it goes live.</p>
    <ol class="rg-steps">
      <li><b>1</b><div><strong>Create your account</strong><span>Fill in the form on this page.</span></div></li>
      <li><b>2</b><div><strong>Administrator review</strong><span>Details and certificates are checked.</span></div></li>
      <li><b>3</b><div><strong>Health checkup</strong><span>Donors attend a body checkup first.</span></div></li>
      <li><b>4</b><div><strong>Start saving lives</strong><span>Get matched and respond to requests.</span></div></li>
    </ol>
    <div class="rg-tags"><span>A+</span><span>B&minus;</span><span>AB+</span><span>O&minus;</span></div>
  </div></aside>

  <div class="rg-main">
    <h1>Create your SEBD account</h1>
    <p class="rg-sub">New accounts are reviewed by an administrator before they are verified.</p>
    <form method="post" enctype="multipart/form-data" id="rg-form"><?= csrf_field() ?>

      <div class="rg-h"><i>👤</i>Register as</div>
      <div class="rg-roles">
        <label class="rg-rc"><input type="radio" name="role" value="DONOR" <?= ($old['role']??'DONOR')==='DONOR'?'checked':'' ?>><span class="ic">🩸</span><span><b>Donor</b><small>Give blood and respond to matched requests</small></span></label>
        <label class="rg-rc"><input type="radio" name="role" value="HEALTHCARE" <?= ($old['role']??'')==='HEALTHCARE'?'checked':'' ?>><span class="ic">🏥</span><span><b>Healthcare Organization / Blood Bank</b><small>Raise requests and manage inventory</small></span></label>
      </div>

      <div class="rg-h" style="margin-top:28px"><i>🔐</i>Account details</div>
      <div class="rg-g">
        <div><label class="l">Full name / Contact name</label><input name="full_name" value="<?= $v('full_name') ?>" required placeholder="Your full name"></div>
        <div><label class="l">Email</label><input type="email" name="email" value="<?= $v('email') ?>" required placeholder="you@example.com"></div>
        <div><label class="l">Phone</label><input name="phone" value="<?= $v('phone') ?>" required pattern="[0-9]{10}" maxlength="10" inputmode="numeric" title="Enter exactly 10 digits" placeholder="07XXXXXXXX"></div>
        <div><label class="l">Password</label><div class="rg-pw"><input type="password" name="password" id="rg-pw" minlength="8" autocomplete="new-password" required placeholder="••••••••"><button type="button" class="rg-eye" data-eye="rg-pw" aria-label="Show password">👁</button></div><div class="rg-meter" id="rg-meter" data-s="0"><i></i><i></i><i></i><i></i></div><small class="rg-hint">At least 8 characters, with a letter and a number.</small></div>
        <div><label class="l">Confirm password</label><div class="rg-pw"><input type="password" name="password_confirm" id="rg-pw2" minlength="8" autocomplete="new-password" required placeholder="••••••••"><button type="button" class="rg-eye" data-eye="rg-pw2" aria-label="Show password">👁</button></div><small class="rg-match" id="rg-match"></small></div>
        <div><label class="l">City</label><?= city_select('city',$old['city']??'') ?></div>
        <div style="grid-column:1/-1"><label class="l">Address</label><input name="address" value="<?= $v('address') ?>" maxlength="255" placeholder="Street address (optional)"></div>
      </div>

      <div data-for="DONOR" class="rg-panel">
        <div class="rg-h"><i>🩸</i>Donor details &amp; health questionnaire</div>
        <div class="rg-g">
          <div><label class="l">Blood group</label><?= blood_select('blood_group',$old['blood_group']??'') ?></div>
          <div><label class="l">Date of birth</label><input type="date" name="date_of_birth" value="<?= $v('date_of_birth') ?>" required></div>
          <div><label class="l">Weight (kg)</label><input type="number" step="0.1" min="50.1" max="200" name="weight_kg" value="<?= $v('weight_kg') ?>" required placeholder="e.g. 65"></div>
          <div><label class="l">Gender</label><select name="gender"><?php foreach(['Male','Female','Other'] as $gx): ?><option <?= ($old['gender']??'')===$gx?'selected':'' ?>><?= $gx ?></option><?php endforeach; ?></select></div>
          <div><label class="l">Preferred blood bank / hospital (optional)</label><select name="preferred_organization_id"><option value="">No preference</option><?php foreach($pdo->query("SELECT organization_id,organization_name,city FROM healthcare_organizations WHERE verification_status='VERIFIED' ORDER BY organization_name") as $po): ?><option value="<?= (int)$po['organization_id'] ?>" <?= ($old['preferred_organization_id']??'')==(string)$po['organization_id']?'selected':'' ?>><?= h($po['organization_name']) ?> — <?= h($po['city']) ?></option><?php endforeach; ?></select></div>
          <div data-geobox><label class="l">Your exact location (optional)</label><button type="button" class="rg-geo" data-geo>📍 Use my current location</button><input type="hidden" name="geo_lat"><input type="hidden" name="geo_lng"><small class="geo-status rg-hint">Lets us ask the nearest donors first. Rounded to about 100 m; hospitals only see an approximate distance. You can skip it - your city centre is used.</small></div>
          <div><label class="l">Last donation date (if any)</label><input type="date" name="last_donation_date" value="<?= $v('last_donation_date') ?>"></div>
          <div><label class="l">Recent illness (if any)</label><input name="recent_illness" value="<?= $v('recent_illness') ?>" placeholder="None"></div>
          <div><label class="l">Current medication (if any)</label><input name="medication" value="<?= $v('medication') ?>" placeholder="None"></div>
        </div>
      </div>

      <div data-for="HEALTHCARE" class="rg-panel">
        <div class="rg-h"><i>🏥</i>Organization details</div>
        <div class="rg-g two">
          <div><label class="l">Organization name</label><input name="organization_name" value="<?= $v('organization_name') ?>" maxlength="150" required placeholder="e.g. Colombo Blood Bank"></div>
          <div><label class="l">Type</label><select name="organization_type"><?php foreach(['Blood Bank','Hospital'] as $ot): ?><option <?= ($old['organization_type']??'')===$ot?'selected':'' ?>><?= $ot ?></option><?php endforeach; ?></select></div>
          <div><label class="l">Registration number</label><input name="registration_number" value="<?= $v('registration_number') ?>" maxlength="100" required placeholder="Official registration no."></div>
          <div><label class="l">Registration certificate / licence (PDF, JPG or PNG, max 2 MB)</label><input type="file" name="certificate" accept=".pdf,.jpg,.jpeg,.png" required></div>
        </div>
        <div class="rg-note"><span>ℹ️</span><span>Your account stays pending until an administrator checks your certificate and verifies the organization.</span></div>
      </div>

      <label class="rg-consent"><input type="checkbox" name="privacy_consent" value="1" required <?= !empty($old['privacy_consent'])?'checked':'' ?>><span>I confirm that the details I provide are accurate and consent to SEBD using them to manage donor verification, donation coordination, and related notifications. I understand that registration does not guarantee medical eligibility.</span></label>

      <div class="rg-act"><button class="rg-btn" id="rg-btn">Create account →</button><a class="rg-ghost" href="login.php">Already registered? Login</a></div>
    </form>
  </div>
</div>

<script>
(function(){
  document.querySelectorAll('[data-eye]').forEach(function(b){b.addEventListener('click',function(){var i=document.getElementById(b.dataset.eye),s=i.type==='password';i.type=s?'text':'password';b.textContent=s?'🙈':'👁'})});
  var pw=document.getElementById('rg-pw'),pw2=document.getElementById('rg-pw2'),m=document.getElementById('rg-meter'),mt=document.getElementById('rg-match');
  function strength(v){var s=0;if(v.length>=8)s++;if(/[A-Za-z]/.test(v)&&/[0-9]/.test(v))s++;if(v.length>=12)s++;if(/[^A-Za-z0-9]/.test(v)&&/[A-Z]/.test(v))s++;return v?Math.max(1,s):0}
  function chk(){m.dataset.s=strength(pw.value);
    if(!pw2.value){mt.textContent='';mt.className='rg-match';return}
    var ok=pw.value===pw2.value;mt.textContent=ok?'✓ Passwords match':'✗ Passwords do not match';mt.className='rg-match '+(ok?'ok':'bad')}
  pw.addEventListener('input',chk);pw2.addEventListener('input',chk);
  var ph=document.querySelector('input[name=phone]');ph.addEventListener('input',function(){ph.value=ph.value.replace(/\D/g,'').slice(0,10)});
  var f=document.getElementById('rg-form'),b=document.getElementById('rg-btn');
  f.addEventListener('submit',function(){if(f.checkValidity()){b.classList.add('busy');b.textContent='Creating account…'}});
})();
</script>
<?php require 'includes/footer.php';