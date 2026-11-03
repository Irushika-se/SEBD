<?php require_once __DIR__.'/includes/auth.php';
// Contact form handler (same file): saves the message as a notification for every active admin (existing table).
if($_SERVER['REQUEST_METHOD']==='POST'){
  header('Content-Type: application/json; charset=utf-8');
  $out=function($ok,$m,$c=200){ http_response_code($c); echo json_encode(['ok'=>$ok,'message'=>$m]); exit; };
  if(post('website')!=='') $out(true,'Thank you! Your message has been sent.');
  $now=time(); $_SESSION['contact_times']=array_values(array_filter($_SESSION['contact_times']??[],function($t) use($now){return $now-$t<600;}));
  if(count($_SESSION['contact_times'])>=3) $out(false,'You have sent several messages recently. Please wait a few minutes and try again.',429);
  $cn=mb_substr(post('name'),0,80); $ce=mb_substr(post('email'),0,120); $cm=mb_substr(post('message'),0,600);
  $okSub=['General enquiry','Login or account support','Donor registration','Healthcare organization support','Emergency request support'];
  $cs=in_array(post('subject'),$okSub,true)?post('subject'):'General enquiry';
  if($cn===''||$cm===''||!filter_var($ce,FILTER_VALIDATE_EMAIL)) $out(false,'Please enter your name, a valid email address and a message.',422);
  $ad=$pdo->query("SELECT user_id FROM users WHERE role='ADMIN' AND status='ACTIVE'")->fetchAll(PDO::FETCH_COLUMN);
  if(!$ad) $out(false,'Sorry, we could not deliver your message right now. Please try again later.',503);
  try{ $pdo->beginTransaction(); foreach($ad as $a) notify($pdo,(int)$a,'CONTACT_MESSAGE','Contact message: '.$cs,"From: $cn <$ce>\nTopic: $cs\n\n$cm"); $pdo->commit(); }
  catch(Throwable $e){ if($pdo->inTransaction()) $pdo->rollBack(); $out(false,'Sorry, we could not save your message. Please try again.',500); }
  $_SESSION['contact_times'][]=$now;
  $out(true,'Thank you! Your message has been sent to the SEBD team. We will follow up during support hours.');
}
$title='Contact'; $page_intro='We are here to help with SEBD access, emergency coordination and general project enquiries.'; require 'includes/header.php'; ?>
<style>
.ct{--r:#c8102e;--ink:#20252a;--mut:#667685;--line:#e9edf0;display:grid;gap:24px}
.ct *{box-sizing:border-box}
.ct h3{margin:0;color:var(--ink)}
.ico{width:46px;height:46px;flex:none;border-radius:14px;display:grid;place-items:center;font-size:1.3rem;line-height:1}

/* HERO: slim, one strong colour block */
.ct-hero{position:relative;overflow:hidden;border-radius:24px;padding:34px 36px 64px;color:#fff;background:linear-gradient(120deg,#430612,#8d0b22 55%,#d9163a);box-shadow:0 18px 40px rgba(200,16,46,.22)}
.ct-hero:before{content:"";position:absolute;right:-80px;top:-110px;width:320px;height:320px;border-radius:50%;background:rgba(255,255,255,.08)}
.ct-hero>*{position:relative}
.ct-status{display:inline-flex;align-items:center;gap:8px;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.28);padding:6px 14px;border-radius:30px;font-size:.76rem;font-weight:700}
.ct-status i{width:8px;height:8px;border-radius:50%;background:#aab2bb}
.ct-status.open i{background:#4ade80;animation:ctp 1.8s infinite}
.ct-hero h2{margin:14px 0 8px;font-size:clamp(1.7rem,3.4vw,2.4rem);letter-spacing:-.8px;line-height:1.12}
.ct-hero h2 em{font-style:normal;color:#ffd27a}
.ct-hero p{margin:0;max-width:540px;color:#ffe6ea;line-height:1.65}
.ct-ecg{position:absolute;left:0;right:0;bottom:12px;width:100%;height:40px;opacity:.5}
.ct-ecg path{fill:none;stroke:#fff;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:1;stroke-dashoffset:1;animation:ctecg 5s ease-in-out infinite}

/* CONTACT CARDS: equal height, calm white */
.ct-cards{display:grid;grid-template-columns:repeat(3,1fr);gap:20px}
.ct-c{display:flex;flex-direction:column;gap:4px;background:#fff;border:1px solid var(--line);border-radius:20px;padding:22px;text-decoration:none;color:var(--ink);position:relative;overflow:hidden;transition:.25s;box-shadow:0 6px 18px rgba(16,24,40,.05)}
.ct-c:before{content:"";position:absolute;left:0;top:0;bottom:0;width:5px;background:var(--c)}
.ct-c:hover{transform:translateY(-5px);box-shadow:0 18px 34px rgba(16,24,40,.12)}
.ct-c .ico{background:var(--bg);margin-bottom:10px}
.ct-c small{font-size:.68rem;font-weight:800;letter-spacing:1.8px;text-transform:uppercase;color:var(--c)}
.ct-c b{font-size:1.12rem;word-break:break-word}
.ct-c p{margin:0;color:var(--mut);font-size:.86rem;line-height:1.55}
.ct-c .go{margin-top:auto;padding-top:12px;font-size:.8rem;font-weight:800;color:var(--c)}
.ct-c.e{--c:#c8102e;--bg:#ffe8ec}.ct-c.p{--c:#0f8f84;--bg:#dcf5f1}.ct-c.l{--c:#5b4bdb;--bg:#ebe8ff}

/* MAIN */
.ct-split{display:grid;grid-template-columns:1.2fr .95fr;gap:24px;align-items:stretch}
.ct-box{background:#fff;border:1px solid var(--line);border-radius:22px;padding:30px;box-shadow:0 6px 18px rgba(16,24,40,.05)}
.ct-box h3{font-size:1.3rem}
.lead{color:var(--mut);margin:4px 0 20px;font-size:.92rem}
.ct label{display:block;font-weight:700;font-size:.82rem;margin:16px 0 6px;color:var(--ink)}
.ct input,.ct textarea{width:100%;padding:13px 15px;border:1.5px solid #e3e7eb;border-radius:13px;font:inherit;background:#fafbfc;transition:.2s}
.ct input:focus,.ct textarea:focus{outline:0;border-color:var(--r);background:#fff;box-shadow:0 0 0 4px rgba(200,16,46,.1)}
.ct textarea{min-height:120px;resize:vertical}
.ct-2{display:grid;grid-template-columns:1fr 1fr;gap:14px}.ct-2 label{margin-top:0}
.ct-chips{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.ct-chips button{border:1.5px solid #e3e7eb;background:#fff;border-radius:12px;padding:10px 8px;font:inherit;font-size:.8rem;font-weight:700;color:#4b5763;cursor:pointer;transition:.2s;text-align:center;line-height:1.25}
.ct-chips button:hover{border-color:var(--r);color:var(--r)}
.ct-chips button.on{background:var(--r);border-color:var(--r);color:#fff}
.ct-count{text-align:right;font-size:.72rem;color:var(--mut);margin-top:4px}
.ct-send{margin-top:14px;border:0;cursor:pointer;font:inherit;font-weight:800;color:#fff;padding:13px 28px;border-radius:30px;background:var(--r);box-shadow:0 10px 20px rgba(200,16,46,.28);transition:.2s}
.ct-send:hover{transform:translateY(-2px);background:#a90d26}
#contact-note{margin-top:14px;padding:12px 15px;border-radius:12px;font-weight:600;font-size:.88rem}
#contact-note.ok{background:#e9f8f0;color:#087443}#contact-note.err{background:#fff0ee;color:#b4231a}

/* URGENT */
.ct-urg{display:flex;flex-direction:column;background:linear-gradient(180deg,#fff,#fff6f7)}
.ct-alert{display:flex;gap:14px;align-items:flex-start;border-radius:16px;padding:16px 18px;color:#fff;background:linear-gradient(120deg,#a90d26,#dc2340);margin-bottom:16px}
.ct-alert .ico{width:40px;height:40px;border-radius:50%;background:rgba(255,255,255,.2);font-size:1.15rem}
.ct-alert b{display:block;margin-bottom:3px}
.ct-alert span{font-size:.86rem;line-height:1.55;color:#ffe3e8}
.ct-roles{list-style:none;margin:0 0 20px;padding:0;display:grid;gap:10px}
.ct-roles li{display:flex;gap:14px;align-items:center;padding:12px 14px;background:#fff;border:1px solid var(--line);border-radius:14px}
.ct-roles .ico{width:40px;height:40px;background:var(--bg);font-size:1.1rem}
.ct-roles b{display:block;font-size:.92rem;color:var(--ink)}
.ct-roles span{font-size:.82rem;color:var(--mut);line-height:1.4}
.ct-btns{display:flex;flex-wrap:wrap;gap:10px;margin-top:auto}
.ct-b{padding:12px 22px;border-radius:30px;font-weight:800;text-decoration:none;font-size:.9rem;transition:.2s}
.ct-b.f{background:var(--r);color:#fff;box-shadow:0 8px 18px rgba(200,16,46,.28)}
.ct-b.o{background:#fff;border:1.5px solid #dfe3e7;color:var(--ink)}
.ct-b:hover{transform:translateY(-2px)}

/* INFO: 4 equal soft cards in one grid */
.ct-info{display:grid;grid-template-columns:repeat(2,1fr);gap:20px}
.ct-i{display:flex;gap:16px;align-items:flex-start;border-radius:20px;padding:22px;background:var(--bg);border:1px solid var(--bd)}
.ct-i .ico{background:#fff;box-shadow:0 4px 10px rgba(16,24,40,.08)}
.ct-i h3{font-size:1.02rem;margin-bottom:4px}
.ct-i p{margin:0;color:#56626e;font-size:.9rem;line-height:1.65}
.ct-i.a{--bg:#fff8e8;--bd:#f6e5b8}.ct-i.b{--bg:#eafaf2;--bd:#c6ecd7}.ct-i.c{--bg:#f0eeff;--bd:#d9d3fb}.ct-i.d{--bg:#fff0f2;--bd:#fbcdd5}

@keyframes ctp{70%{box-shadow:0 0 0 8px rgba(74,222,128,0)}100%{box-shadow:0 0 0 0 rgba(74,222,128,0)}}
@keyframes ctecg{0%{stroke-dashoffset:1}60%,100%{stroke-dashoffset:0}}
.ct-rv{opacity:0;transform:translateY(22px);transition:opacity .6s,transform .6s}.ct-rv.in{opacity:1;transform:none}
@media(prefers-reduced-motion:reduce){.ct *{animation:none!important;transition:none!important}.ct-rv{opacity:1;transform:none}.ct-ecg path{stroke-dashoffset:0}}
@media(max-width:960px){.ct-split,.ct-info,.ct-cards{grid-template-columns:1fr}}
@media(max-width:560px){.ct-2{grid-template-columns:1fr}.ct-chips{grid-template-columns:1fr 1fr}.ct-hero{padding:28px 22px 60px}.ct-box{padding:22px}}
</style>

<div class="ct">

<div class="ct-hero ct-rv">
  <span class="ct-status" id="ct-status"><i></i><b id="ct-status-t">Mon–Fri, 8:30 AM–5:00 PM (Sri Lanka time)</b></span>
  <h2>Let’s get you <em>connected</em></h2>
  <p>Questions about access, verification or emergency coordination? Reach the SEBD team below.</p>
  <svg class="ct-ecg" viewBox="0 0 1200 40" preserveAspectRatio="none" aria-hidden="true"><path pathLength="1" d="M0 22H420l14-14 16 30 18-36 14 20h90l14-10 16 22 18-28 14 16H1200"/></svg>
</div>

<div class="ct-cards ct-rv">
  <a class="ct-c e" href="mailto:support@sebd-project.com" data-copy="support@sebd-project.com">
    <div class="ico">✉️</div><small>Email us</small><b>support@sebd-project.com</b><p>For general questions, account support and project enquiries.</p><span class="go">Send an email →</span>
  </a>
  <a class="ct-c p" href="tel:+94112345678" data-copy="+94 11 234 5678">
    <div class="ico">📞</div><small>Call us</small><b>+94 11 234 5678</b><p>Monday–Friday, 8:30 AM–5:00 PM (Sri Lanka time).</p><span class="go">Call now →</span>
  </a>
  <a class="ct-c l" href="https://www.google.com/maps/search/?api=1&query=Colombo%2C+Sri+Lanka" target="_blank" rel="noopener">
    <div class="ico">📍</div><small>Our location</small><b>Colombo, Sri Lanka</b><p>SEBD Project Support Centre, Colombo.</p><span class="go">Open in Maps →</span>
  </a>
</div>

<div class="ct-split ct-rv">
  <div class="ct-box">
    <h3>Send us a message</h3>
    <p class="lead">Tell us what you need help with and our project team can follow up.</p>
    <form id="contact-form" novalidate><?= csrf_field() ?><input type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;opacity:0;height:0;width:0">
      <div class="ct-2">
        <div><label for="c-name">Name</label><input id="c-name" required name="name" placeholder="Your name" autocomplete="name"></div>
        <div><label for="c-email">Email</label><input id="c-email" required type="email" name="email" placeholder="you@example.com" autocomplete="email"></div>
      </div>
      <label>What is this about?</label>
      <input type="hidden" id="c-subject" name="subject" value="General enquiry">
      <div class="ct-chips" id="ct-chips">
        <button type="button" class="on" data-v="General enquiry">General enquiry</button>
        <button type="button" data-v="Login or account support">Login or account</button>
        <button type="button" data-v="Donor registration">Donor registration</button>
        <button type="button" data-v="Healthcare organization support">Healthcare organization</button>
        <button type="button" data-v="Emergency request support">Emergency request</button>
      </div>
      <label for="c-message">Message</label>
      <textarea id="c-message" required name="message" maxlength="600" placeholder="How can we help?"></textarea>
      <div class="ct-count"><span id="ct-n">0</span> / 600</div>
      <div id="contact-note" role="status" aria-live="polite" hidden></div>
      <button class="ct-send" type="submit">Send message ✈</button>
    </form>
  </div>

  <div class="ct-box ct-urg">
    <h3>For urgent blood needs</h3>
    <p class="lead">SEBD coordinates emergency requests only through verified healthcare organizations and donors.</p>
    <div class="ct-alert"><div class="ico">🚨</div><div><b>Need access now?</b><span>Log in to your authorized account to manage emergency requests, donor matching, inventory and responses.</span></div></div>
    <ul class="ct-roles">
      <li><div class="ico" style="--bg:#ffe8ec">🩸</div><div><b>Donors</b><span>Register, get verified and respond to matched requests.</span></div></li>
      <li><div class="ico" style="--bg:#dcf5f1">🏥</div><div><b>Healthcare organizations</b><span>Raise emergency requests and manage inventory.</span></div></li>
      <li><div class="ico" style="--bg:#ebe8ff">🛡️</div><div><b>Administrators</b><span>Verify accounts and reset passwords.</span></div></li>
    </ul>
    <div class="ct-btns"><a class="ct-b f" href="<?= BASE ?>/login.php">Open SEBD login</a><a class="ct-b o" href="<?= BASE ?>/register.php">Create an account</a></div>
  </div>
</div>

<div class="ct-info ct-rv">
  <div class="ct-i a"><div class="ico">🔑</div><div><h3>Forgot password</h3><p>Contact the SEBD administrator for a temporary password. After 5 failed login attempts, an account is locked for 15 minutes.</p></div></div>
  <div class="ct-i b"><div class="ico">🛡️</div><div><h3>Account verification</h3><p>New donor and organization accounts stay pending until an administrator reviews them. Donors also attend a body checkup before final verification.</p></div></div>
  <div class="ct-i c"><div class="ico">⏱️</div><div><h3>Response time</h3><p>Project enquiries are reviewed during support hours.</p></div></div>
  <div class="ct-i d"><div class="ico">❤️</div><div><h3>Safety first</h3><p>For a real medical emergency, contact the appropriate emergency medical service or blood bank directly.</p></div></div>
</div>

</div>

<script>
(function(){
  var rv=document.querySelectorAll('.ct-rv');
  if('IntersectionObserver' in window){var io=new IntersectionObserver(function(en){en.forEach(function(x){if(x.isIntersecting){x.target.classList.add('in');io.unobserve(x.target)}})},{threshold:.08});rv.forEach(function(e){io.observe(e)})}else{rv.forEach(function(e){e.classList.add('in')})}

  try{
    var p=new Intl.DateTimeFormat('en-US',{timeZone:'Asia/Colombo',weekday:'short',hour:'numeric',minute:'numeric',hour12:false}).formatToParts(new Date()),g={};
    p.forEach(function(x){g[x.type]=x.value});
    var mins=(+g.hour%24)*60+ +g.minute,wk=['Mon','Tue','Wed','Thu','Fri'].indexOf(g.weekday)>-1,open=wk&&mins>=510&&mins<1020;
    document.getElementById('ct-status').classList.toggle('open',open);
    document.getElementById('ct-status-t').textContent=open?'Support team is online now':'Offline · back Mon–Fri, 8:30 AM–5:00 PM';
  }catch(e){}

  document.querySelectorAll('[data-copy]').forEach(function(a){a.addEventListener('click',function(e){
    if(!e.shiftKey||!navigator.clipboard)return;e.preventDefault();
    navigator.clipboard.writeText(a.dataset.copy);var h=a.querySelector('.go'),o=h.textContent;h.textContent='Copied ✓';setTimeout(function(){h.textContent=o},1600);
  })});

  var chips=document.querySelectorAll('#ct-chips button'),subj=document.getElementById('c-subject');
  chips.forEach(function(b){b.addEventListener('click',function(){chips.forEach(function(x){x.classList.remove('on')});b.classList.add('on');subj.value=b.dataset.v})});

  var m=document.getElementById('c-message'),n=document.getElementById('ct-n');
  m.addEventListener('input',function(){n.textContent=m.value.length});

  var f=document.getElementById('contact-form'),note=document.getElementById('contact-note');
  f.addEventListener('submit',function(e){
    e.preventDefault();
    var name=f.name.value.trim(),email=f.email.value.trim(),msg=f.message.value.trim();
    note.hidden=false;
    if(!name||!msg||!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)){note.className='err';note.textContent='Please enter your name, a valid email address and a message.';return}
    var btn=f.querySelector('button[type=submit]'); if(btn)btn.disabled=true;
    note.className='ok';note.textContent='Sending…';
    fetch('contact.php',{method:'POST',body:new FormData(f),credentials:'same-origin',headers:{'Accept':'application/json'}})
      .then(function(r){return r.text().then(function(t){var d;try{d=JSON.parse(t)}catch(x){d={ok:false,message:'Could not send your message. Please refresh the page and try again.'}}return d})})
      .then(function(d){
        note.className=d.ok?'ok':'err';note.textContent=d.message;
        if(d.ok){f.reset();n.textContent='0';chips.forEach(function(x,i){x.classList.toggle('on',i===0)});subj.value='General enquiry'}
      })
      .catch(function(){note.className='err';note.textContent='Network error. Please try again.'})
      .then(function(){if(btn)btn.disabled=false});
  });
})();
</script>
<?php require 'includes/footer.php'; ?>