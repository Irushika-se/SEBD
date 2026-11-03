<?php require_once __DIR__.'/../includes/auth.php'; require_role('HEALTHCARE');
$o=my_org($pdo); $oid=$o['organization_id']; if($o['verification_status']==='VERIFIED') expire_stock($pdo,$oid);
$c=function($sql)use($pdo,$oid){$s=$pdo->prepare($sql);$s->execute([$oid]);return $s->fetchColumn();};
$active=$c("SELECT COUNT(*) FROM emergency_requests WHERE organization_id=? AND status IN ('PENDING','MATCHING','PARTIALLY_FULFILLED') AND required_time>=NOW()");
$overdue=$c("SELECT COUNT(*) FROM emergency_requests WHERE organization_id=? AND status IN ('PENDING','MATCHING','PARTIALLY_FULFILLED') AND required_time<NOW()"); // past needed-by time, closes automatically after the grace period
$acc=$c("SELECT COUNT(*) FROM donor_matches m JOIN emergency_requests r USING(request_id) WHERE r.organization_id=? AND m.status='ACCEPTED' AND r.status IN ('PENDING','MATCHING','PARTIALLY_FULFILLED')");
$done=$c("SELECT COUNT(*) FROM donations WHERE organization_id=? AND status='COMPLETED'");
$chk=$c("SELECT COUNT(*) FROM donor_checkups WHERE organization_id=? AND outcome='PENDING'");
$low=$pdo->prepare("SELECT * FROM blood_inventory WHERE organization_id=? AND units_available<=minimum_stock"); $low->execute([$oid]); $low=$low->fetchAll();
$exs=$pdo->prepare("SELECT blood_group,SUM(units_remaining) u,MIN(expiry_date) d FROM blood_batches WHERE organization_id=? AND status='ACTIVE' AND expiry_date>=? AND expiry_date<=? GROUP BY blood_group"); $exs->execute([$oid,date('Y-m-d'),date('Y-m-d',strtotime('+'.EXPIRY_WARN_DAYS.' days'))]); $exs=$exs->fetchAll();
$wst=$pdo->prepare("SELECT blood_group,SUM(-quantity_change) u,MAX(created_at) d FROM inventory_logs WHERE organization_id=? AND change_type='EXPIRED' AND created_at>=? GROUP BY blood_group ORDER BY blood_group"); $wst->execute([$oid,date('Y-m-d 00:00:00',strtotime('-7 days'))]); $wst=$wst->fetchAll();
$pdis=pending_disposal($pdo,$oid);
$quar=(int)$c("SELECT COALESCE(SUM(units_remaining),0) FROM blood_batches WHERE organization_id=? AND status='QUARANTINE'");
$n=$pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 5"); $n->execute([$_SESSION['uid']]);
$autorefresh=true; $title=$o['organization_name']; require '../includes/header.php'; ?>
<?php if($o['verification_status']==='REJECTED'): ?><div class="alert err">Your registration was <b>rejected</b>. Reason: <?= h($o['rejection_reason']) ?>. Upload a corrected certificate in <a href="profile.php">Profile</a> to resubmit.</div>
<?php elseif($o['verification_status']!=='VERIFIED'): ?><div class="alert">Organization status: <b><?= h($o['verification_status']) ?></b>. An administrator is reviewing your certificate. You can raise requests and manage inventory once verified.</div><?php endif; ?>
<?php
$vs=$o['verification_status']; $hasCert=org_cert_valid_name($o['certificate_file']??'') || $vs==='VERIFIED'; // an organization the administrator has verified has, by definition, passed the certificate step (older / seeded accounts may have no file on record)
$osteps=[ // [label, done?, sub-text, icon, rejected?]
  ['Registered',true,'Completed','check',false],
  ['Certificate submitted',$hasCert,$hasCert?'Done':'Missing','file',false],
  ['Admin review',$vs!=='PENDING',$vs==='PENDING'?'In progress':'Done','clipboard',false],
  ['Verified by administrator',$vs==='VERIFIED',$vs==='VERIFIED'?'Done':($vs==='REJECTED'?'Rejected':'Pending'),'shield',$vs==='REJECTED'],
]; ?>
<div class="card vp"><div class="vp-head"><span class="ci"><?= icon('shield') ?></span><div><h2>Verification progress</h2><span class="muted">Complete all steps to become a verified organization.</span></div></div>
<ol class="vsteps"><?php foreach($osteps as $i=>$st): ?><li class="<?= $st[1]?'done':($st[4]?'bad':'') ?>"><span class="vc"><?= icon($st[1]?'check':($st[4]?'ban':$st[3])) ?></span><div><b><?= ($i+1).'. '.h($st[0]) ?></b><small><?= h($st[2]) ?></small></div></li><?php endforeach; ?></ol></div>
<div class="grid"><div class="stat"><b><?= $active ?></b><span>Open requests</span></div><div class="stat"><b><?= $acc ?></b><span>Accepted donor responses</span></div>
<div class="stat"><b><?= $done ?></b><span>Donations recorded</span></div><div class="stat"><b><?= $chk ?></b><span>Checkups awaiting outcome</span></div>
<div class="stat"><b><?= count($low) ?></b><span>Low-stock groups</span></div></div>
<?php if($overdue): ?><div class="alert err"><b><?= $overdue ?> request(s) are past their needed-by time.</b> Record any late donation or cancel them; otherwise they close automatically <?= EXPIRY_GRACE_HOURS ?> hours after the needed-by time. <a href="emergency_requests.php">View requests</a></div><?php endif; ?>
<?php if($quar): ?><div class="alert"><b><?= $quar ?> unit(s)</b> are in quarantine waiting for screening results and are NOT counted as stock yet. <a href="inventory.php#quarantine">Record lab result</a></div><?php endif; ?>
<?php if($pdis): ?><div class="alert err"><b><?= array_sum(array_column($pdis,'units_remaining')) ?> expired unit(s)</b> (<?= count($pdis) ?> batch(es)) still need physical disposal. <a href="inventory.php#disposal">Confirm disposal</a></div><?php endif; ?>
<?php if($wst): ?><div class="card"><h2>Recently expired (last 7 days)</h2><?php foreach($wst as $w) echo badge('EXPIRED').' '.h($w['blood_group']).': '.(int)$w['u'].' unit(s) discarded<br>'; ?><a class="btn sm alt" href="inventory_history.php?type=EXPIRED">View history</a> <a class="btn sm alt" href="reports.php">Wastage report</a></div><?php endif; ?>
<?php if($exs): ?><div class="card"><h2>Expiring soon</h2><?php foreach($exs as $e) echo badge('EXPIRING').' '.h($e['blood_group']).': '.$e['u'].' unit(s) expire by '.h($e['d']).' - use or plan for them<br>'; ?></div><?php endif; ?>
<?php if($low): ?><div class="card"><h2>Low-stock alerts</h2><?php foreach($low as $l) echo badge('LOW').' '.h($l['blood_group']).': '.$l['units_available'].' unit(s) (minimum '.$l['minimum_stock'].')<br>'; ?></div><?php endif; ?>
<div class="card"><h2>Recent notifications</h2><?php if(!$n) echo '<div class="empty">No notifications yet.</div>'; ?><?php foreach($n as $x) echo '<p><b>'.h($x['title']).'</b> - '.h($x['message']).' <span class="muted">'.h($x['created_at']).'</span></p>'; ?></div>
<a class="btn" href="create_request.php">New emergency request</a> <a class="btn alt" href="create_campaign.php">New campaign</a>
<?php require '../includes/footer.php';
