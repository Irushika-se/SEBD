<?php require_once __DIR__.'/includes/auth.php';
if(!empty($_SESSION['uid'])) redirect(home_for($_SESSION['role']));
$title='Welcome';
$urgent=null; $campaigns=[]; $stats=['donors'=>0,'orgs'=>0,'requests'=>0,'units'=>0];
try {
  $urgent=$pdo->query("SELECT r.*,o.organization_name FROM emergency_requests r JOIN healthcare_organizations o ON o.organization_id=r.organization_id WHERE r.status IN ('PENDING','MATCHING','PARTIALLY_FULFILLED') AND r.required_time>=NOW() ORDER BY FIELD(r.urgency,'CRITICAL','HIGH','NORMAL'),r.created_at DESC LIMIT 1")->fetch();
  campaign_auto_complete($pdo);
  $campaigns=$pdo->query("SELECT c.*,o.organization_name FROM donation_campaigns c JOIN healthcare_organizations o ON o.organization_id=c.organization_id WHERE c.status IN ('UPCOMING','ACTIVE') AND ".CAMPAIGN_END_SQL.">=NOW() ORDER BY c.campaign_date ASC LIMIT 3")->fetchAll();
  $stats['donors']=(int)$pdo->query("SELECT COUNT(*) FROM donors WHERE verification_status='VERIFIED'")->fetchColumn();
  $stats['orgs']=(int)$pdo->query("SELECT COUNT(*) FROM healthcare_organizations WHERE verification_status='VERIFIED'")->fetchColumn();
  $stats['requests']=(int)$pdo->query("SELECT COUNT(*) FROM emergency_requests WHERE status IN ('PENDING','MATCHING','PARTIALLY_FULFILLED') AND required_time>=NOW()")->fetchColumn();
  $stats['units']=(int)$pdo->query("SELECT COALESCE(SUM(units_available),0) FROM blood_inventory")->fetchColumn();
} catch(Throwable $e) {}
$compat=['A+'=>'A+ · A− · O+ · O−','A−'=>'A− · O−','B+'=>'B+ · B− · O+ · O−','B−'=>'B− · O−','AB+'=>'All groups','AB−'=>'A− · B− · AB− · O−','O+'=>'O+ · O−','O−'=>'O− only'];
// OPTIONAL: put your own photo at assets/images/hero.jpg and it appears behind the hero automatically.
$heroImg = file_exists(__DIR__.'/assets/images/hero.jpg') ? BASE.'/assets/images/hero.jpg' : '';
require 'includes/header.php'; ?>
<style>
.hm{--r:#c8102e;--r2:#7a0a1d;--t:#0f8b8d;--i:#5b4bd6;--a:#f59e0b;--g:#12a150;--ink:#1d2329;--mut:#5d6b78;overflow-x:hidden}
.hm *{box-sizing:border-box}.hm-in{max-width:1240px;margin:0 auto;padding:0 22px}
/* HERO */
.hm-hero{position:relative;overflow:hidden;color:#fff;padding:70px 0 130px;background:radial-gradient(900px 480px at 88% 0%,#ff6b7d 0%,transparent 60%),radial-gradient(700px 400px at 0% 100%,#5b4bd6 0%,transparent 55%),linear-gradient(130deg,#2a040c 0%,#7a0a1d 45%,#c8102e 100%)}
.hm-photo{position:absolute;inset:0;background-size:cover;background-position:center;opacity:.28;mix-blend-mode:luminosity}
.hm-hero .hm-in{display:grid;grid-template-columns:1.05fr .95fr;gap:30px;align-items:center;position:relative;z-index:2}
.hm-kick{display:inline-flex;gap:8px;align-items:center;padding:8px 18px;border-radius:30px;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.3);font-size:.76rem;font-weight:800;letter-spacing:1.4px;text-transform:uppercase;backdrop-filter:blur(8px)}
.hm-hero h1{font-size:clamp(2.3rem,4.8vw,3.7rem);line-height:1.05;letter-spacing:-1.8px;margin:20px 0 16px;color:#fff}
.hm-hero h1 em{font-style:normal;background:linear-gradient(90deg,#ffd166,#ffb3bd 60%,#fff);-webkit-background-clip:text;background-clip:text;color:transparent}
.hm-hero p.lead{font-size:1.08rem;line-height:1.75;max-width:560px;margin:0 0 28px;color:#ffe9ec}
.hm-btns{display:flex;gap:14px;flex-wrap:wrap}
.hm-b{display:inline-flex;align-items:center;gap:8px;padding:15px 30px;border-radius:40px;font-weight:800;text-decoration:none;transition:.25s;font-size:.95rem}
.hm-b.w{background:#fff;color:var(--r);box-shadow:0 14px 30px rgba(0,0,0,.28)}.hm-b.w:hover{transform:translateY(-3px);box-shadow:0 20px 38px rgba(0,0,0,.34)}
.hm-b.o{border:2px solid rgba(255,255,255,.75);color:#fff}.hm-b.o:hover{background:rgba(255,255,255,.16)}
.hm-b.r{background:linear-gradient(135deg,#ff5a6e,#c8102e);color:#fff;box-shadow:0 12px 26px rgba(200,16,46,.35)}.hm-b.r:hover{transform:translateY(-3px)}
.hm-badges{display:flex;gap:10px;flex-wrap:wrap;margin-top:28px}.hm-badges span{font-size:.78rem;font-weight:700;padding:7px 14px;border-radius:30px;background:rgba(0,0,0,.22);border:1px solid rgba(255,255,255,.12)}
/* hero art */
.hm-art{position:relative;height:440px;display:grid;place-items:center}
.hm-art svg.big{width:340px;max-width:80%;filter:drop-shadow(0 30px 40px rgba(0,0,0,.35));animation:hmb 5s ease-in-out infinite}
@keyframes hmb{0%,100%{transform:translateY(0)}50%{transform:translateY(-14px)}}
.hm-ring{position:absolute;width:400px;height:400px;max-width:92%;aspect-ratio:1;border-radius:50%;border:2px dashed rgba(255,255,255,.28);animation:hms 40s linear infinite}
.hm-ring.two{width:300px;height:300px;border-color:rgba(255,209,102,.35);animation-direction:reverse;animation-duration:30s}
@keyframes hms{to{transform:rotate(360deg)}}
.hm-bub{position:absolute;width:62px;height:62px;border-radius:50%;display:grid;place-items:center;font-weight:900;color:#fff;font-size:1.05rem;box-shadow:0 14px 28px rgba(0,0,0,.28);animation:hmf 6s ease-in-out infinite;border:3px solid rgba(255,255,255,.55)}
.hm-bub:nth-of-type(1){background:linear-gradient(135deg,#f59e0b,#ffd166);left:4%;top:12%}
.hm-bub:nth-of-type(2){background:linear-gradient(135deg,#0f8b8d,#34c5b8);right:2%;top:20%;animation-delay:1.2s}
.hm-bub:nth-of-type(3){background:linear-gradient(135deg,#5b4bd6,#8f7dff);left:8%;bottom:10%;animation-delay:2.4s}
.hm-bub:nth-of-type(4){background:linear-gradient(135deg,#12a150,#4ade80);right:8%;bottom:6%;animation-delay:3.2s}
@keyframes hmf{0%,100%{transform:translateY(0)}50%{transform:translateY(-16px)}}
.hm-wave{position:absolute;left:0;right:0;bottom:-1px;line-height:0;z-index:3}.hm-wave svg{width:100%;height:80px}
/* urgent strip */
.hm-urg{position:relative;z-index:6;margin-top:-92px;display:grid;grid-template-columns:auto 1fr auto;gap:22px;align-items:center;background:#fff;border-radius:24px;padding:20px 26px;box-shadow:0 22px 55px rgba(16,24,40,.2);border-left:8px solid var(--r)}
.hm-ubg{width:92px;height:92px;border-radius:50% 0 50% 50%;transform:rotate(-45deg);background:linear-gradient(135deg,#ff5a6e,#c8102e);display:grid;place-items:center;color:#fff;box-shadow:0 12px 24px rgba(200,16,46,.35)}
.hm-ubg span{transform:rotate(45deg);font-size:1.7rem;font-weight:900}
.hm-ulab{display:flex;gap:10px;align-items:center;font-size:.74rem;font-weight:800;letter-spacing:1.2px;color:var(--r)}
.hm-pulse{width:10px;height:10px;border-radius:50%;background:var(--r);animation:hmp 1.6s infinite}
@keyframes hmp{0%{box-shadow:0 0 0 0 rgba(200,16,46,.6)}70%{box-shadow:0 0 0 14px rgba(200,16,46,0)}100%{box-shadow:0 0 0 0 rgba(200,16,46,0)}}
.hm-tag{background:#fff0f2;color:var(--r);padding:3px 11px;border-radius:20px}
.hm-urg h3{margin:4px 0 2px;font-size:1.2rem;color:var(--ink)}.hm-urg p{margin:0;color:var(--mut);font-size:.9rem}
/* ribbon */
.hm-rib{margin-top:34px;overflow:hidden;border-radius:18px;background:linear-gradient(90deg,#c8102e,#7a0a1d);padding:14px 0;color:#fff}
.hm-rib div{display:inline-flex;gap:46px;white-space:nowrap;animation:hmm 26s linear infinite;font-weight:800;letter-spacing:.5px}
.hm-rib span:before{content:"🩸";margin-right:10px}
@keyframes hmm{to{transform:translateX(-50%)}}
/* sections */
.hm-sec{padding:60px 0 4px}.hm-head{text-align:center;max-width:700px;margin:0 auto 32px}
.hm-pill{display:inline-block;font-size:.72rem;font-weight:800;letter-spacing:1.6px;text-transform:uppercase;color:var(--r);background:#fff0f2;padding:5px 14px;border-radius:20px;margin-bottom:10px}
.hm-head h2{font-size:2rem;margin:0 0 8px;letter-spacing:-.7px;color:var(--ink)}.hm-head p{margin:0;color:var(--mut);line-height:1.65}
/* stats */
.hm-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-top:30px}
.hm-stat{background:#fff;border-radius:22px;padding:24px;text-align:center;box-shadow:0 14px 34px rgba(16,24,40,.1);position:relative;overflow:hidden}
.hm-stat:before{content:"";position:absolute;left:0;right:0;top:0;height:6px;background:var(--c)}
.hm-stat:nth-child(1){--c:linear-gradient(90deg,#c8102e,#ff5a6e)}.hm-stat:nth-child(2){--c:linear-gradient(90deg,#0f8b8d,#34c5b8)}.hm-stat:nth-child(3){--c:linear-gradient(90deg,#f59e0b,#ffd166)}.hm-stat:nth-child(4){--c:linear-gradient(90deg,#5b4bd6,#8f7dff)}
.hm-stat .e{font-size:1.7rem}.hm-stat b{display:block;font-size:2.5rem;line-height:1.1;color:var(--ink);margin-top:4px}.hm-stat span{font-size:.82rem;color:var(--mut);font-weight:600}
/* split scene */
.hm-split{display:grid;grid-template-columns:1fr 1fr;gap:34px;align-items:center}
.hm-scene{background:linear-gradient(160deg,#fff1f3,#ece9ff);border-radius:30px;padding:20px;box-shadow:0 18px 44px rgba(16,24,40,.12)}
.hm-scene svg{width:100%;height:auto;display:block}
.hm-list{list-style:none;margin:18px 0 22px;padding:0}.hm-list li{display:flex;gap:12px;padding:10px 0;color:#3b4651;line-height:1.55}
.hm-list li:before{content:"✓";flex:0 0 auto;width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,#12a150,#4ade80);color:#fff;display:grid;place-items:center;font-size:.8rem;font-weight:900;margin-top:2px}
.hm-split h2{font-size:1.9rem;margin:6px 0;letter-spacing:-.6px;color:var(--ink)}
/* steps */
.hm-steps{display:grid;grid-template-columns:repeat(4,1fr);gap:18px}
.hm-step{background:#fff;border-radius:22px;padding:26px 20px;border:1px solid #eceef1;box-shadow:0 10px 26px rgba(16,24,40,.07);transition:.25s;position:relative;overflow:hidden}
.hm-step:hover{transform:translateY(-8px);box-shadow:0 20px 42px rgba(16,24,40,.14)}
.hm-step em{position:absolute;right:14px;top:4px;font-style:normal;font-size:4rem;font-weight:900;opacity:.07}
.hm-ic{width:58px;height:58px;border-radius:18px;display:grid;place-items:center;font-size:1.7rem;margin-bottom:14px;color:#fff;box-shadow:0 10px 20px rgba(0,0,0,.16)}
.hm-step:nth-child(1) .hm-ic{background:linear-gradient(135deg,#c8102e,#ff5a6e)}.hm-step:nth-child(2) .hm-ic{background:linear-gradient(135deg,#f59e0b,#ffd166)}.hm-step:nth-child(3) .hm-ic{background:linear-gradient(135deg,#0f8b8d,#34c5b8)}.hm-step:nth-child(4) .hm-ic{background:linear-gradient(135deg,#5b4bd6,#8f7dff)}
.hm-step h3{margin:0 0 6px;font-size:1.08rem;color:var(--ink)}.hm-step p{margin:0;font-size:.86rem;color:var(--mut);line-height:1.6}
/* compat */
.hm-comp{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
.hm-cp{background:#fff;border-radius:18px;padding:16px;border:1px solid #eceef1;display:flex;gap:16px;align-items:center;box-shadow:0 8px 20px rgba(16,24,40,.06);transition:.2s}
.hm-cp:hover{transform:translateY(-4px)}
.hm-cp b{flex:0 0 auto;width:56px;height:56px;border-radius:50% 0 50% 50%;transform:rotate(-45deg);background:linear-gradient(135deg,#ff5a6e,#c8102e);display:grid;place-items:center;color:#fff;box-shadow:0 8px 16px rgba(200,16,46,.3)}
.hm-cp b span{transform:rotate(45deg);font-size:1.05rem;font-weight:900}
.hm-cp small{display:block;font-size:.66rem;font-weight:800;letter-spacing:1px;color:var(--mut);text-transform:uppercase}.hm-cp strong{font-size:.9rem;color:var(--ink)}
/* roles */
.hm-roles{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}
.hm-role{border-radius:26px;padding:30px;color:#fff;position:relative;overflow:hidden;box-shadow:0 18px 40px rgba(16,24,40,.18);transition:.25s}
.hm-role:hover{transform:translateY(-6px)}.hm-role:after{content:"";position:absolute;right:-50px;bottom:-60px;width:200px;height:200px;border-radius:50%;background:rgba(255,255,255,.14)}
.hm-role:before{content:"";position:absolute;left:-30px;top:-30px;width:110px;height:110px;border-radius:50%;background:rgba(255,255,255,.1)}
.hm-role.d{background:linear-gradient(135deg,#c8102e,#ff5a6e)}.hm-role.h{background:linear-gradient(135deg,#0f8b8d,#34c5b8)}.hm-role.a{background:linear-gradient(135deg,#5b4bd6,#8f7dff)}
.hm-role .e{font-size:2.2rem;position:relative}.hm-role h3{margin:8px 0;font-size:1.3rem;color:#fff;position:relative}.hm-role p{margin:0 0 18px;font-size:.9rem;line-height:1.65;opacity:.96;position:relative}
.hm-role a{display:inline-block;background:#fff;padding:10px 22px;border-radius:30px;font-weight:800;font-size:.85rem;text-decoration:none;position:relative;z-index:2}
.hm-role.d a{color:var(--r)}.hm-role.h a{color:var(--t)}.hm-role.a a{color:var(--i)}
/* campaigns */
.hm-camp{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
.hm-c{display:flex;gap:16px;background:#fff;border-radius:20px;padding:20px;border:1px solid #eceef1;box-shadow:0 10px 26px rgba(16,24,40,.07);transition:.25s}
.hm-c:hover{transform:translateY(-5px)}
.hm-dt{flex:0 0 auto;width:68px;height:74px;border-radius:18px;background:linear-gradient(160deg,#c8102e,#ff5a6e);color:#fff;display:grid;align-content:center;text-align:center;box-shadow:0 10px 20px rgba(200,16,46,.3)}
.hm-dt b{font-size:1.6rem;line-height:1}.hm-dt span{font-size:.72rem;font-weight:800;letter-spacing:1px;text-transform:uppercase}
.hm-c h3{margin:0 0 4px;font-size:1.02rem;color:var(--ink)}.hm-c p{margin:0;font-size:.83rem;color:var(--mut);line-height:1.55}
.hm-empty{grid-column:1/-1;text-align:center;padding:30px;background:#fff;border:2px dashed #e2e6ea;border-radius:20px;color:var(--mut)}
/* trust */
.hm-trust{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
.hm-tr{padding:24px 18px;border-radius:22px;background:#fff;border:1px solid #eceef1;text-align:center;box-shadow:0 8px 22px rgba(16,24,40,.06);transition:.25s}
.hm-tr:hover{transform:translateY(-5px)}
.hm-tr .e{width:60px;height:60px;border-radius:50%;margin:0 auto 10px;display:grid;place-items:center;font-size:1.7rem}
.hm-tr:nth-child(1) .e{background:#d8f5e5}.hm-tr:nth-child(2) .e{background:#ffe3e7}.hm-tr:nth-child(3) .e{background:#e6e2fb}.hm-tr:nth-child(4) .e{background:#fff0cf}
.hm-tr b{display:block;margin-bottom:4px;color:var(--ink)}.hm-tr span{font-size:.82rem;color:var(--mut);line-height:1.55}
/* CTA */
.hm-cta{margin:64px 0 40px;border-radius:32px;padding:56px 24px;text-align:center;color:#fff;background:radial-gradient(500px 260px at 90% 0%,#ff6b7d,transparent 60%),linear-gradient(120deg,#2a040c,#c8102e 75%);box-shadow:0 24px 56px rgba(200,16,46,.3);position:relative;overflow:hidden}
.hm-cta:before{content:"";position:absolute;left:-70px;top:-70px;width:240px;height:240px;border-radius:50%;background:rgba(255,255,255,.1)}
.hm-cta h2{font-size:2.2rem;margin:0 0 8px;color:#fff;position:relative}.hm-cta p{margin:0 0 24px;color:#ffe9ec;position:relative}.hm-cta .hm-btns{justify-content:center;position:relative}
@media(max-width:1000px){.hm-hero .hm-in,.hm-split{grid-template-columns:1fr}.hm-art{height:340px}.hm-stats,.hm-steps,.hm-comp,.hm-trust{grid-template-columns:1fr 1fr}.hm-roles,.hm-camp{grid-template-columns:1fr}.hm-urg{grid-template-columns:1fr;text-align:center;justify-items:center}}
@media(max-width:560px){.hm-stats,.hm-steps,.hm-comp,.hm-trust{grid-template-columns:1fr}.hm-hero{padding:46px 0 120px}.hm-art{height:300px}.hm-bub{width:50px;height:50px;font-size:.9rem}}
@media(prefers-reduced-motion:reduce){.hm *{animation:none!important}}
</style>

<div class="hm">
  <section class="hm-hero">
    <?php if($heroImg): ?><div class="hm-photo" style="background-image:url('<?= h($heroImg) ?>')"></div><?php endif; ?>
    <div class="hm-in">
      <div>
        <span class="hm-kick">🩸 Give blood · Save lives</span>
        <h1>Emergency Blood Bank &amp; <em>Donor Matching</em> System</h1>
        <p class="lead">SEBD connects healthcare organizations with compatible, available and verified donors the moment blood is urgently needed.</p>
        <div class="hm-btns"><a class="hm-b w" href="register.php">❤ Become a donor</a><a class="hm-b o" href="login.php">Login →</a></div>
        <div class="hm-badges"><span>✅ Verified donors</span><span>📍 Nearest-first matching</span><span>⚡ Urgency-aware</span><span>📦 Live inventory</span></div>
      </div>
      <div class="hm-art" aria-hidden="true">
        <div class="hm-ring"></div><div class="hm-ring two"></div>
        <div class="hm-bub">A+</div><div class="hm-bub">O−</div><div class="hm-bub">B+</div><div class="hm-bub">AB+</div>
        <svg class="big" viewBox="0 0 200 250" xmlns="http://www.w3.org/2000/svg">
          <defs>
            <linearGradient id="hmg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#ff7a8a"/><stop offset=".55" stop-color="#e01e3c"/><stop offset="1" stop-color="#8d0a22"/></linearGradient>
            <linearGradient id="hmh" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff" stop-opacity=".7"/><stop offset="1" stop-color="#fff" stop-opacity="0"/></linearGradient>
          </defs>
          <path d="M100 8C100 8 24 98 24 156a76 76 0 0 0 152 0C176 98 100 8 100 8Z" fill="url(#hmg)"/>
          <path d="M62 120C62 98 80 66 96 44 86 80 80 108 84 134 70 150 56 140 62 120Z" fill="url(#hmh)" opacity=".75"/>
          <path d="M100 196c-24-17-38-30-38-46 0-12 9-20 19-20 8 0 15 5 19 11 4-6 11-11 19-11 10 0 19 8 19 20 0 16-14 29-38 46Z" fill="#fff"/>
          <polyline points="46,160 76,160 88,134 104,184 118,150 130,160 154,160" fill="none" stroke="#c8102e" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </div>
    </div>
    <div class="hm-wave"><svg viewBox="0 0 1440 80" preserveAspectRatio="none"><path fill="#fff" d="M0,50 C240,95 480,0 720,38 C960,76 1200,14 1440,50 L1440,80 L0,80 Z"/></svg></div>
  </section>

  <div class="hm-in">
    <div class="hm-urg">
      <?php if($urgent): ?>
        <div class="hm-ubg"><span><?= h($urgent['blood_group']) ?></span></div>
        <div>
          <div class="hm-ulab"><i class="hm-pulse"></i>URGENT BLOOD REQUEST <span class="hm-tag"><?= h($urgent['urgency']) ?></span></div>
          <h3><?= h($urgent['organization_name']) ?></h3>
          <p>📍 <?= h($urgent['location']) ?> &nbsp;·&nbsp; 🕒 <?= h(date('j M Y, g:i A',strtotime($urgent['required_time']))) ?></p>
        </div>
        <a class="hm-b r" href="login.php">View details →</a>
      <?php else: ?>
        <div class="hm-ubg" style="background:linear-gradient(135deg,#34c5b8,#0f8b8d);box-shadow:0 12px 24px rgba(15,139,141,.35)"><span>✔</span></div>
        <div>
          <div class="hm-ulab" style="color:#0f8b8d"><i class="hm-pulse" style="background:#0f8b8d"></i>ALL CLEAR</div>
          <h3>No active emergency requests</h3>
          <p>Requests raised by verified organizations appear here in real time.</p>
        </div>
        <a class="hm-b r" href="login.php">Login →</a>
      <?php endif; ?>
    </div>

    <div class="hm-rib"><div>
      <?php for($k=0;$k<2;$k++) foreach(['Give blood','Save lives','Verified donors','Nearest-first matching','Live inventory','Emergency ready','A+','A−','B+','B−','AB+','AB−','O+','O−'] as $w): ?><span><?= h($w) ?></span><?php endforeach; ?>
    </div></div>

    <div class="hm-stats">
      <div class="hm-stat"><div class="e">🩸</div><b data-count="<?= (int)$stats['donors'] ?>"><?= number_format($stats['donors']) ?></b><span>Verified donors</span></div>
      <div class="hm-stat"><div class="e">🏥</div><b data-count="<?= (int)$stats['orgs'] ?>"><?= number_format($stats['orgs']) ?></b><span>Verified organizations</span></div>
      <div class="hm-stat"><div class="e">🚨</div><b data-count="<?= (int)$stats['requests'] ?>"><?= number_format($stats['requests']) ?></b><span>Active requests</span></div>
      <div class="hm-stat"><div class="e">📦</div><b data-count="<?= (int)$stats['units'] ?>"><?= number_format($stats['units']) ?></b><span>Blood units in stock</span></div>
    </div>

    <section class="hm-sec">
      <div class="hm-split">
        <div class="hm-scene" aria-hidden="true">
          <svg viewBox="0 0 520 360" xmlns="http://www.w3.org/2000/svg">
            <defs><linearGradient id="hms1" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#ff7a8a"/><stop offset="1" stop-color="#c8102e"/></linearGradient><linearGradient id="hms2" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#34c5b8"/><stop offset="1" stop-color="#0f8b8d"/></linearGradient></defs>
            <circle cx="90" cy="300" r="120" fill="#fff" opacity=".6"/><circle cx="450" cy="70" r="90" fill="#fff" opacity=".6"/>
            <path d="M60 250 C170 120 330 330 450 120" fill="none" stroke="#5b4bd6" stroke-width="4" stroke-dasharray="4 12" stroke-linecap="round"/>
            <g><circle cx="90" cy="250" r="46" fill="url(#hms1)"/><circle cx="90" cy="232" r="14" fill="#fff"/><path d="M62 276c4-18 16-26 28-26s24 8 28 26" fill="#fff"/></g>
            <rect x="372" y="60" width="150" height="120" rx="16" fill="#fff" stroke="#e8ebee"/><rect x="372" y="60" width="150" height="30" rx="16" fill="url(#hms2)"/>
            <rect x="436" y="108" width="22" height="58" rx="5" fill="#c8102e"/><rect x="418" y="126" width="58" height="22" rx="5" fill="#c8102e"/>
            <rect x="388" y="104" width="22" height="22" rx="4" fill="#d9f3f3"/><rect x="388" y="138" width="22" height="22" rx="4" fill="#d9f3f3"/><rect x="484" y="104" width="22" height="22" rx="4" fill="#d9f3f3"/><rect x="484" y="138" width="22" height="22" rx="4" fill="#d9f3f3"/>
            <g transform="translate(215 150)"><path d="M0 -34 C0 -34 -26 -4 -26 16 a26 26 0 0 0 52 0 C26 -4 0 -34 0 -34Z" fill="url(#hms1)"/><path d="M0 40c-9-6-14-11-14-17 0-4 3-7 7-7 3 0 5 2 7 4 2-2 4-4 7-4 4 0 7 3 7 7 0 6-5 11-14 17Z" fill="#fff" transform="translate(0 -22)"/></g>
            <g><circle cx="300" cy="300" r="26" fill="#f59e0b"/><text x="300" y="308" text-anchor="middle" font-size="22" font-weight="900" fill="#fff" font-family="Arial">📍</text></g>
            <text x="90" y="330" text-anchor="middle" font-size="14" font-weight="700" fill="#5d6b78" font-family="Arial">Donor</text>
            <text x="447" y="206" text-anchor="middle" font-size="14" font-weight="700" fill="#5d6b78" font-family="Arial">Hospital / Blood bank</text>
            <text x="300" y="344" text-anchor="middle" font-size="14" font-weight="700" fill="#5d6b78" font-family="Arial">Nearest match</text>
          </svg>
        </div>
        <div>
          <span class="hm-pill">Why SEBD</span>
          <h2>The right donor, in the right place, in time</h2>
          <p style="color:#5d6b78;line-height:1.7;margin:0">When every minute counts, SEBD replaces phone calls and guesswork with a controlled, traceable workflow.</p>
          <ul class="hm-list">
            <li>Only verified, health-cleared and available donors are matched.</li>
            <li>Blood-group compatibility is checked before every match.</li>
            <li>Nearest donors are contacted first, and the search widens if nobody accepts.</li>
            <li>Inventory, history and audit logs update automatically.</li>
          </ul>
          <div class="hm-btns"><a class="hm-b r" href="register.php">Get started</a><a class="hm-b r" style="background:#23292f;box-shadow:none" href="about.php">Learn more</a></div>
        </div>
      </div>
    </section>

    <section class="hm-sec">
      <div class="hm-head"><span class="hm-pill">The process</span><h2>How SEBD works</h2><p>From a shortage to a completed donation in four steps.</p></div>
      <div class="hm-steps">
        <div class="hm-step"><em>1</em><div class="hm-ic">📦</div><h3>Check inventory</h3><p>Healthcare staff check their own stock, then raise an emergency request when it is not enough.</p></div>
        <div class="hm-step"><em>2</em><div class="hm-ic">🚨</div><h3>Create request</h3><p>Blood group, units, urgency, needed time and location are submitted in one form.</p></div>
        <div class="hm-step"><em>3</em><div class="hm-ic">🎯</div><h3>Match donors</h3><p>Verified, available and compatible donors are matched by distance and urgency, then notified.</p></div>
        <div class="hm-step"><em>4</em><div class="hm-ic">❤️</div><h3>Donate &amp; record</h3><p>Donors respond, the donation is recorded and the inventory and history update automatically.</p></div>
      </div>
    </section>

    <section class="hm-sec">
      <div class="hm-head"><span class="hm-pill">Compatibility</span><h2>Blood-group compatibility</h2><p>Who can donate to each recipient group. SEBD checks this before creating any match.</p></div>
      <div class="hm-comp">
        <?php foreach($compat as $g=>$who): ?><div class="hm-cp"><b><span><?= h($g) ?></span></b><div><small>Can receive from</small><strong><?= h($who) ?></strong></div></div><?php endforeach; ?>
      </div>
    </section>

    <section class="hm-sec">
      <div class="hm-head"><span class="hm-pill">Roles</span><h2>Who can use SEBD?</h2><p>Three roles, one coordinated workflow.</p></div>
      <div class="hm-roles">
        <div class="hm-role d"><div class="e">🩸</div><h3>Donor</h3><p>Register, complete verification, set your availability, respond to matched requests and join campaigns.</p><a href="register.php">Register</a></div>
        <div class="hm-role h"><div class="e">🏥</div><h3>Healthcare Organization</h3><p>Raise emergency requests, track donor matches, and manage inventory, campaigns and donation records.</p><a href="register.php">Register</a></div>
        <div class="hm-role a"><div class="e">🛡️</div><h3>Administrator</h3><p>Verify accounts, schedule donor checkups, manage users, and review audit logs and reports.</p><a href="login.php">Login</a></div>
      </div>
    </section>

    <section class="hm-sec">
      <div class="hm-head"><span class="hm-pill">Campaigns</span><h2>Upcoming donation campaigns</h2><p>Donate voluntarily through a campaign, independent of an emergency request.</p></div>
      <div class="hm-camp">
        <?php if($campaigns): foreach($campaigns as $c): ?>
          <article class="hm-c"><div class="hm-dt"><b><?= h(date('d',strtotime($c['campaign_date']))) ?></b><span><?= h(date('M',strtotime($c['campaign_date']))) ?></span></div><div><h3><?= h($c['campaign_name']) ?></h3><p>📍 <?= h($c['location']) ?><br><?= h($c['organization_name']) ?></p></div></article>
        <?php endforeach; else: ?>
          <div class="hm-empty">No upcoming campaigns right now. Verified organizations publish new campaigns here.</div>
        <?php endif; ?>
      </div>
    </section>

    <section class="hm-sec">
      <div class="hm-head"><span class="hm-pill">Trust</span><h2>Built for trust and safety</h2><p>Every step is controlled and recorded.</p></div>
      <div class="hm-trust">
        <div class="hm-tr"><div class="e">✅</div><b>Admin verification</b><span>Donors and organizations are approved before they can act.</span></div>
        <div class="hm-tr"><div class="e">🩺</div><b>Health checkups</b><span>Donors are cleared through a body checkup first.</span></div>
        <div class="hm-tr"><div class="e">🔐</div><b>Secure access</b><span>Role-based access, hashed passwords and login protection.</span></div>
        <div class="hm-tr"><div class="e">📜</div><b>Audit trail</b><span>Key actions are logged for administrators to review.</span></div>
      </div>
    </section>

    <section class="hm-cta">
      <h2>Ready to save a life today?</h2>
      <p>Join SEBD as a donor or register your healthcare organization.</p>
      <div class="hm-btns"><a class="hm-b w" href="register.php">Create an account</a><a class="hm-b o" href="login.php">Login →</a></div>
    </section>
  </div>
</div>
<script>
(function(){
  if(window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches)return;
  document.querySelectorAll('[data-count]').forEach(function(el){
    var end=parseInt(el.getAttribute('data-count'),10)||0; if(end<1)return;
    var t0=null; el.textContent='0';
    function step(ts){ if(!t0)t0=ts; var p=Math.min((ts-t0)/1200,1); el.textContent=Math.round(end*(1-Math.pow(1-p,3))).toLocaleString(); if(p<1)requestAnimationFrame(step); }
    requestAnimationFrame(step);
  });
})();
</script>
<?php require 'includes/footer.php'; ?>