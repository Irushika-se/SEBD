<?php require_once __DIR__.'/../includes/auth.php'; require_role('DONOR');
if($_SERVER['REQUEST_METHOD']!=='POST') redirect('donor/emergency_requests.php');
$d=my_donor($pdo); $act=post('action'); $rematch=0;
if(!in_array($act,['ACCEPTED','DECLINED','WITHDRAW'],true)){ flash('Invalid response.','err'); redirect('donor/emergency_requests.php'); }
try{
  $pdo->beginTransaction();
  $s=$pdo->prepare("SELECT m.*,r.status AS request_status,r.request_id,r.blood_group,r.required_time,r.units_required,o.user_id AS organization_user_id FROM donor_matches m JOIN emergency_requests r USING(request_id) JOIN healthcare_organizations o ON o.organization_id=r.organization_id WHERE m.match_id=? AND m.donor_id=? FOR UPDATE");
  $s->execute([(int)post('match_id'),$d['donor_id']]); $m=$s->fetch();
  $f=$pdo->prepare("SELECT d.verification_status,d.health_status,d.availability_status,d.last_donation_date,u.status AS account_status FROM donors d JOIN users u ON u.user_id=d.user_id WHERE d.donor_id=? FOR UPDATE"); $f->execute([$d['donor_id']]); $d=array_merge($d,$f->fetch()); // fresh, locked: a suspension that happened after page load must not be bypassed
  $closed=!$m || in_array($m['request_status'],['CANCELLED','FULFILLED'],true);
  if($act==='WITHDRAW'){
    if($closed || $m['status']!=='ACCEPTED') throw new RuntimeException('This acceptance can no longer be withdrawn.');
    $pdo->prepare("UPDATE donor_matches SET status='DECLINED',responded_at=NOW(),decline_reason='Donor withdrew acceptance' WHERE match_id=? AND status='ACCEPTED'")->execute([$m['match_id']]);
    $back=restore_availability($pdo,$d['donor_id']);
    notify($pdo,$m['organization_user_id'],'DONOR_RESPONSE','Donor withdrew acceptance',"{$d['full_name']} ({$d['blood_group']}) withdrew their acceptance of emergency request #{$m['request_id']}.",$m['request_id'],$m['match_id']);
    audit($pdo,$_SESSION['uid'],'RESPOND','donor_matches',$m['match_id'],'Donor withdrew acceptance.'); $pdo->commit();
    flash('You withdrew your acceptance. The organization has been told.'.($back?' Your availability was restored.':'')); $rematch=(int)$m['request_id'];
  } else {
    if($closed || $m['status']!=='NOTIFIED' || $m['required_time'] < date('Y-m-d H:i:s')) throw new RuntimeException('This request can no longer be answered.');
    if($act==='ACCEPTED'){
      // Cap: once enough donors have accepted (or donated) for the units needed, other notified donors are not committed.
      // Lock the request row so simultaneous accepts cannot all slip under the cap. A withdrawal re-opens a place.
      $lk=$pdo->prepare("SELECT request_id FROM emergency_requests WHERE request_id=? FOR UPDATE"); $lk->execute([$m['request_id']]); $lk->fetchColumn();
      $c=$pdo->prepare("SELECT COUNT(*) FROM donor_matches WHERE request_id=? AND status IN ('ACCEPTED','COMPLETED')"); $c->execute([$m['request_id']]);
      if((int)$c->fetchColumn()>=(int)$m['units_required']) throw new RuntimeException('Enough donors have already accepted this request. Thank you - you will not be needed unless someone withdraws, so please keep your availability up to date.');
      $eligible=$d['account_status']==='ACTIVE' && $d['verification_status']==='VERIFIED' && $d['health_status']==='CLEARED' && $d['availability_status']==='AVAILABLE' && donor_gap_ok($d['last_donation_date']);
      if(!$eligible){
        $o2=$pdo->prepare("SELECT COUNT(*) FROM donor_matches m JOIN emergency_requests r USING(request_id) WHERE m.donor_id=? AND m.status='ACCEPTED' AND r.status IN ('PENDING','MATCHING','PARTIALLY_FULFILLED') AND m.match_id<>?"); $o2->execute([$d['donor_id'],$m['match_id']]);
        throw new RuntimeException($o2->fetchColumn() ? 'You have already accepted another open request, so you cannot accept this one now. Withdraw that acceptance first if you cannot attend it.' : 'Your donor eligibility or availability has changed. Please review your status before accepting.');
      }
    }
    $allowed=['Not available at that time','Travelling / out of town','Too far away','Feeling unwell','Other']; $reason=post('reason'); if($act!=='DECLINED'||!in_array($reason,$allowed,true)) $reason=null;
    $pdo->prepare("UPDATE donor_matches SET status=?,responded_at=NOW(),decline_reason=? WHERE match_id=? AND status='NOTIFIED'")->execute([$act,$reason,$m['match_id']]);
    if($act==='ACCEPTED'){
      $pdo->prepare("UPDATE donors SET availability_status='UNAVAILABLE' WHERE donor_id=?")->execute([$d['donor_id']]);
      $pdo->prepare("INSERT INTO donor_availability_history(donor_id,availability_status) VALUES(?,'UNAVAILABLE')")->execute([$d['donor_id']]);
    }
    notify($pdo,$m['organization_user_id'],'DONOR_RESPONSE','Donor '.strtolower($act),"{$d['full_name']} ({$d['blood_group']}) ".strtolower($act)." emergency request #{$m['request_id']}.".($act==='DECLINED'&&$reason?" Reason: $reason.":''),$m['request_id'],$m['match_id']);
    audit($pdo,$_SESSION['uid'],'RESPOND','donor_matches',$m['match_id'],"Donor $act match."); $pdo->commit();
    flash($act==='ACCEPTED'?'You accepted the request. Hospital details are shown on the request. Your availability is set to unavailable; you can withdraw if you cannot attend. Healthcare staff make the final decision on whether you can donate when you attend.':'Your response has been recorded.'); if($act==='DECLINED') $rematch=(int)$m['request_id'];
  }
}catch(RuntimeException $e){ if($pdo->inTransaction()) $pdo->rollBack(); flash($e->getMessage(),'err'); }
catch(Throwable $e){ if($pdo->inTransaction()) $pdo->rollBack(); flash('We could not save your response. Please try again.','err'); }
if($rematch) rematch_if_exhausted($pdo,$rematch); // after the response is committed
redirect('donor/emergency_requests.php');
