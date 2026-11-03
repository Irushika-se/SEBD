<?php require_once __DIR__.'/includes/auth.php'; $title='About SEBD'; $page_intro='A role-based web application for emergency blood coordination and donor matching.'; require 'includes/header.php'; ?>
<style>
.ab{--r1:#c8102e;--r2:#7a0a1d;--ink:#20252a;--mut:#667685;--teal:#0f9d8f;--vio:#5b4bdb;--amb:#f59e0b;--grn:#16a34a;--org:#ea580c}
.ab *{box-sizing:border-box}
.ab h2.t{font-size:1.7rem;margin:0 0 4px;letter-spacing:-.5px;color:var(--ink)}
.ab .kick{display:inline-block;font-size:.68rem;font-weight:800;letter-spacing:2.5px;text-transform:uppercase;color:var(--r1);background:#fff0f2;padding:5px 12px;border-radius:20px;margin-bottom:8px}
.ab .sub{color:var(--mut);margin:0 0 20px}
.ab section{margin-top:52px}

/* HERO */
.ab-hero{position:relative;overflow:hidden;border-radius:26px;padding:56px 48px;color:#fff;display:grid;grid-template-columns:1.4fr .8fr;gap:30px;align-items:center;background:radial-gradient(circle at 85% 20%,#ff5a72 0,transparent 38%),linear-gradient(135deg,#3b0510 0%,#8d0b22 50%,#d9163a 100%);box-shadow:0 25px 60px rgba(200,16,46,.28)}
.ab-hero:before,.ab-hero:after{content:"";position:absolute;border-radius:50%;background:rgba(255,255,255,.07)}
.ab-hero:before{width:340px;height:340px;right:-90px;top:-120px}
.ab-hero:after{width:220px;height:220px;left:-60px;bottom:-110px}
.ab-hero>*{position:relative;z-index:2}
.ab-badge{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.28);padding:6px 14px;border-radius:30px;font-size:.7rem;font-weight:800;letter-spacing:2px;text-transform:uppercase;backdrop-filter:blur(6px)}
.ab-badge i{width:8px;height:8px;border-radius:50%;background:#ff8095;box-shadow:0 0 0 0 rgba(255,128,149,.7);animation:abpulse 1.8s infinite}
.ab-hero h2{font-size:clamp(1.9rem,4vw,3rem);line-height:1.08;margin:16px 0 14px;letter-spacing:-1.2px}
.ab-hero h2 em{font-style:normal;background:linear-gradient(90deg,#ffd27a,#ffb4c0);-webkit-background-clip:text;background-clip:text;color:transparent}
.ab-hero p{max-width:600px;font-size:1.04rem;line-height:1.7;margin:0 0 22px;color:#ffe9ed}
.ab-pills{display:flex;flex-wrap:wrap;gap:10px}
.ab-pills span{background:rgba(255,255,255,.13);border:1px solid rgba(255,255,255,.25);padding:8px 15px;border-radius:30px;font-size:.82rem;font-weight:700;backdrop-filter:blur(6px);transition:.25s}
.ab-pills span:hover{background:#fff;color:var(--r1);transform:translateY(-3px)}
.ab-drop{display:grid;place-items:center;position:relative;min-height:230px}
.ab-drop svg{width:190px;filter:drop-shadow(0 18px 30px rgba(0,0,0,.35));animation:abfloat 4s ease-in-out infinite}
.ab-drop .ring{position:absolute;width:250px;height:250px;border:1.5px dashed rgba(255,255,255,.35);border-radius:50%;animation:abspin 30s linear infinite}
.ab-drop .tag{position:absolute;width:52px;height:52px;border-radius:50%;display:grid;place-items:center;font-weight:800;font-size:.85rem;color:#fff;border:3px solid rgba(255,255,255,.85);box-shadow:0 10px 20px rgba(0,0,0,.25);animation:abfloat 5s ease-in-out infinite}
.ab-drop .t1{background:var(--vio);top:6%;left:6%}
.ab-drop .t2{background:var(--grn);bottom:6%;right:4%;animation-delay:-2s}

/* PILLARS */
.ab-cards{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
.ab-card{position:relative;background:#fff;border:1px solid #edf0f2;border-radius:20px;padding:24px 22px;overflow:hidden;transition:.3s;box-shadow:0 6px 20px rgba(16,24,40,.05)}
.ab-card:before{content:"";position:absolute;left:0;top:0;right:0;height:4px;background:var(--c);transform:scaleX(0);transform-origin:left;transition:.35s}
.ab-card:hover{transform:translateY(-8px);box-shadow:0 22px 40px rgba(16,24,40,.12)}
.ab-card:hover:before{transform:scaleX(1)}
.ab-ico{width:54px;height:54px;border-radius:16px;display:grid;place-items:center;font-size:1.6rem;margin-bottom:14px;background:linear-gradient(135deg,var(--c),var(--c2));box-shadow:0 10px 20px -6px var(--c);color:#fff}
.ab-card h3{margin:0 0 6px;font-size:1.05rem;color:var(--ink)}
.ab-card p{margin:0;color:var(--mut);font-size:.9rem;line-height:1.6}
.c-red{--c:#c8102e;--c2:#ff5a72}.c-teal{--c:#0f9d8f;--c2:#4fd1c5}.c-vio{--c:#5b4bdb;--c2:#8b7cf6}.c-amb{--c:#f59e0b;--c2:#fbbf24}

/* ROLES */
.ab-roles{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
.ab-role{position:relative;overflow:hidden;border-radius:24px;padding:28px 26px 30px;color:#fff;min-height:250px;transition:.3s}
.ab-role:after{content:"";position:absolute;right:-50px;bottom:-60px;width:190px;height:190px;border-radius:50%;background:rgba(255,255,255,.14)}
.ab-role:hover{transform:translateY(-8px) rotate(-.4deg);box-shadow:0 26px 46px rgba(16,24,40,.22)}
.ab-role .big{font-size:2.3rem;margin-bottom:6px}
.ab-role .rt{display:inline-block;background:rgba(255,255,255,.2);border:1px solid rgba(255,255,255,.35);padding:4px 13px;border-radius:20px;font-size:.72rem;font-weight:800;letter-spacing:.5px;margin-bottom:10px}
.ab-role h3{margin:0 0 8px;font-size:1.35rem}
.ab-role p{margin:0;font-size:.92rem;line-height:1.65;color:rgba(255,255,255,.93);position:relative;z-index:2}
.r-don{background:linear-gradient(135deg,#a90d26,#ef4560)}
.r-org{background:linear-gradient(135deg,#087f74,#35c4b3)}
.r-adm{background:linear-gradient(135deg,#40329f,#7c6cf0)}

/* WORKFLOW */
.ab-flow{position:relative;display:grid;grid-template-columns:repeat(6,1fr);gap:14px}
.ab-flow:before{content:"";position:absolute;left:8%;right:8%;top:27px;height:3px;background:repeating-linear-gradient(90deg,#d6dbe0 0 8px,transparent 8px 16px);z-index:0}
.ab-step{position:relative;z-index:1;background:#fff;border:1px solid #edf0f2;border-radius:18px;padding:0 14px 18px;text-align:center;box-shadow:0 6px 18px rgba(16,24,40,.05);transition:.3s}
.ab-step:hover{transform:translateY(-6px);box-shadow:0 18px 32px rgba(16,24,40,.12)}
.ab-num{width:54px;height:54px;margin:-0px auto 10px;border-radius:50%;display:grid;place-items:center;color:#fff;font-weight:800;font-size:1.15rem;border:4px solid #fff;box-shadow:0 8px 18px rgba(16,24,40,.2);position:relative;top:0;transform:translateY(-1px)}
.ab-step h4{margin:0 0 5px;font-size:.95rem;color:var(--ink)}
.ab-step p{margin:0;font-size:.8rem;color:var(--mut);line-height:1.5}

/* TECH */
.ab-tech{display:flex;flex-wrap:wrap;gap:10px}
.ab-tech span{padding:10px 20px;border-radius:30px;color:#fff;font-weight:800;font-size:.85rem;box-shadow:0 8px 16px rgba(16,24,40,.15);transition:.25s;cursor:default}
.ab-tech span:hover{transform:translateY(-5px) scale(1.06)}

/* CTA */
.ab-cta{position:relative;overflow:hidden;text-align:center;border-radius:28px;padding:52px 24px;color:#fff;background:radial-gradient(circle at 15% 20%,rgba(255,255,255,.18),transparent 35%),linear-gradient(120deg,#4a0613,#a50f29 55%,#e0253f);box-shadow:0 25px 55px rgba(200,16,46,.28)}
.ab-cta h2{margin:0 0 8px;font-size:2rem;letter-spacing:-.8px}
.ab-cta p{margin:0 0 22px;color:#ffe3e8}
.ab-btn{display:inline-block;margin:4px;padding:13px 30px;border-radius:30px;font-weight:800;text-decoration:none;transition:.25s}
.ab-btn.w{background:#fff;color:var(--r1);box-shadow:0 10px 24px rgba(0,0,0,.25)}
.ab-btn.o{border:2px solid rgba(255,255,255,.7);color:#fff}
.ab-btn:hover{transform:translateY(-4px)}.ab-btn.o:hover{background:#fff;color:var(--r1)}

/* ANIMATION */
.ab-rv{opacity:0;transform:translateY(28px);transition:opacity .7s,transform .7s}
.ab-rv.in{opacity:1;transform:none}
@keyframes abfloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-14px)}}
@keyframes abspin{to{transform:rotate(360deg)}}
@keyframes abpulse{70%{box-shadow:0 0 0 10px rgba(255,128,149,0)}100%{box-shadow:0 0 0 0 rgba(255,128,149,0)}}
@media(prefers-reduced-motion:reduce){.ab *{animation:none!important;transition:none!important}.ab-rv{opacity:1;transform:none}}

@media(max-width:1000px){.ab-cards{grid-template-columns:1fr 1fr}.ab-flow{grid-template-columns:1fr 1fr 1fr}.ab-flow:before{display:none}.ab-step{padding-top:14px}}
@media(max-width:820px){.ab-hero{grid-template-columns:1fr;padding:38px 24px}.ab-drop{min-height:200px;order:-1}.ab-roles{grid-template-columns:1fr}}
@media(max-width:560px){.ab-cards,.ab-flow{grid-template-columns:1fr}.ab-cta h2{font-size:1.5rem}}
</style>

<div class="ab">

<div class="ab-hero ab-rv">
  <div>
    <span class="ab-badge"><i></i> Give blood &middot; Save lives</span>
    <h2>Smart Emergency Blood Bank &amp; <em>Donor Matching</em> System</h2>
    <p>SEBD supports healthcare organizations and verified donors when blood is urgently needed. It brings donor availability, blood-group compatibility, urgency and location-based matching into one coordinated workflow.</p>
    <div class="ab-pills"><span>🩸 Verified donors</span><span>📍 Location-based matching</span><span>⚡ Urgency-aware</span><span>📦 Live inventory</span></div>
  </div>
  <div class="ab-drop" aria-hidden="true">
    <div class="ring"></div>
    <svg viewBox="0 0 120 150"><defs><linearGradient id="abg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#ff6b81"/><stop offset="1" stop-color="#c8102e"/></linearGradient></defs><path d="M60 6C60 6 14 62 14 98a46 46 0 0 0 92 0C106 62 60 6 60 6z" fill="url(#abg)"/><path d="M34 96c0-18 10-36 20-50" stroke="#fff" stroke-opacity=".5" stroke-width="7" stroke-linecap="round" fill="none"/><path d="M36 100h14l8-16 10 30 8-14h12" stroke="#fff" stroke-width="6" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg>
    <div class="tag t1">A+</div><div class="tag t2">O&minus;</div>
  </div>
</div>

<section class="ab-rv">
  <span class="kick">Why SEBD</span>
  <h2 class="t">How the system works</h2><p class="sub">Four ideas that keep every request safe and fast.</p>
  <div class="ab-cards">
    <div class="ab-card c-red"><div class="ab-ico">✅</div><h3>Verification first</h3><p>Donor and organization access is managed through the administrator verification workflow.</p></div>
    <div class="ab-card c-teal"><div class="ab-ico">🧬</div><h3>Compatibility matters</h3><p>Matching checks blood-group compatibility before any donor match is created.</p></div>
    <div class="ab-card c-vio"><div class="ab-ico">🗺️</div><h3>Nearest donors first</h3><p>Distance and urgency decide who is notified, and the search widens when nobody accepts.</p></div>
    <div class="ab-card c-amb"><div class="ab-ico">📦</div><h3>Stock under control</h3><p>Organizations track batches, expiry and low-stock alerts, so requests start with real numbers.</p></div>
  </div>
</section>

<section class="ab-rv">
  <span class="kick">Roles</span>
  <h2 class="t">System roles</h2><p class="sub">What each role is responsible for.</p>
  <div class="ab-roles">
    <div class="ab-role r-don"><div class="big">🩸</div><span class="rt">Donor</span><h3>Give and respond</h3><p>Registers with required details, completes verification, manages availability, responds to compatible emergency requests and joins walk-in or campaign donations.</p></div>
    <div class="ab-role r-org"><div class="big">🏥</div><span class="rt">Healthcare Organization</span><h3>Request and manage</h3><p>Records checkup outcomes, creates and tracks emergency requests, manages blood inventory, organizes campaigns, records donations and generates reports.</p></div>
    <div class="ab-role r-adm"><div class="big">🛡️</div><span class="rt">Administrator</span><h3>Verify and oversee</h3><p>Reviews registrations, schedules donor checkups, verifies organizations and donors, manages accounts and monitors audit records and reports.</p></div>
  </div>
</section>

<section class="ab-rv">
  <span class="kick">The process</span>
  <h2 class="t">Emergency request workflow</h2><p class="sub">From a shortage to a completed donation.</p>
  <div class="ab-flow" style="padding-top:27px">
    <div class="ab-step"><div class="ab-num" style="background:#c8102e;margin-top:-27px">1</div><h4>Check inventory</h4><p>Staff check current stock first.</p></div>
    <div class="ab-step"><div class="ab-num" style="background:#ea580c;margin-top:-27px">2</div><h4>Create request</h4><p>Blood group, units, urgency, time and location.</p></div>
    <div class="ab-step"><div class="ab-num" style="background:#f59e0b;margin-top:-27px">3</div><h4>Find donors</h4><p>Compatible, available and nearby donors are matched.</p></div>
    <div class="ab-step"><div class="ab-num" style="background:#16a34a;margin-top:-27px">4</div><h4>Donor responds</h4><p>Donors accept or decline from their account.</p></div>
    <div class="ab-step"><div class="ab-num" style="background:#0f9d8f;margin-top:-27px">5</div><h4>Record donation</h4><p>The organization records the completed donation.</p></div>
    <div class="ab-step"><div class="ab-num" style="background:#5b4bdb;margin-top:-27px">6</div><h4>Update stock</h4><p>Inventory and history update automatically.</p></div>
  </div>
</section>

<section class="ab-rv">
  <span class="kick">Safety</span>
  <h2 class="t">Who can donate</h2><p class="sub">The basic rules SEBD applies before a donor can be matched.</p>
  <div class="ab-cards">
    <div class="ab-card c-red"><div class="ab-ico">🎂</div><h3>Age</h3><p><?= DONOR_MIN_AGE ?> to <?= DONOR_MAX_AGE_REGULAR ?> years for regular donors, and <?= DONOR_MIN_AGE ?> to <?= DONOR_MAX_AGE_FIRST ?> for first-time donors.</p></div>
    <div class="ab-card c-teal"><div class="ab-ico">⚖️</div><h3>Weight</h3><p>Donors must weigh more than <?= DONOR_MIN_WEIGHT_KG ?> kg to be eligible for matching.</p></div>
    <div class="ab-card c-vio"><div class="ab-ico">📅</div><h3>Rest period</h3><p>A minimum gap of <?= DONATION_GAP_MONTHS ?> months is kept between donations before a donor is matched again.</p></div>
    <div class="ab-card c-amb"><div class="ab-ico">🩺</div><h3>Final screening</h3><p>Healthcare staff make the final eligibility decision at the checkup and before every donation.</p></div>
  </div>
</section>

<section class="ab-rv">
  <div class="ab-cta">
    <h2>Ready to save a life?</h2>
    <p>Join SEBD as a donor or register your healthcare organization.</p>
    <a class="ab-btn w" href="<?= BASE ?>/register.php">Create an account</a>
    <a class="ab-btn o" href="<?= BASE ?>/login.php">Login</a>
  </div>
</section>

</div>

<script>
(function(){
  var els=document.querySelectorAll('.ab-rv');
  if(!('IntersectionObserver' in window)){els.forEach(function(e){e.classList.add('in')});return;}
  var io=new IntersectionObserver(function(en){en.forEach(function(x){if(x.isIntersecting){x.target.classList.add('in');io.unobserve(x.target);}})},{threshold:.12});
  els.forEach(function(e){io.observe(e)});
})();
</script>
<?php require 'includes/footer.php'; ?>