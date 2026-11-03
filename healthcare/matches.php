<?php require_once __DIR__.'/../includes/auth.php'; require_role('HEALTHCARE');
$o=my_org($pdo); $oid=$o['organization_id']; $reopen=0;
if($_SERVER['REQUEST_METHOD']==='POST' && post('do')==='complete'){
  if(post('screened')!=='1'){ flash('Confirm that qualified staff completed the pre-donation screening (haemoglobin, weight, blood pressure) before recording the donation.','err'); redirect('healthcare/matches.php'.(get('request_id')?'?request_id='.(int)get('request_id'):'')); }
  try{ $pdo->beginTransaction();
    $s=$pdo->prepare("SELECT m.*,d.blood_group dbg,d.user_id donor_user_id FROM donor_matches m JOIN donors d ON d.donor_id=m.donor_id JOIN emergency_requests r ON r.request_id=m.request_id WHERE m.match_id=? AND r.organization_id=? FOR UPDATE");
    $s->execute([(int)post('match_id'),$oid]); $m=$s->fetch(); $r=null;
    if($m){ $q=$pdo->prepare("SELECT * FROM emergency_requests WHERE request_id=? FOR UPDATE"); $q->execute([$m['request_id']]); $r=$q->fetch(); }
    if($m && $r && $m['status']==='ACCEPTED' && !in_array($r['status'],['FULFILLED','CANCELLED'],true)
       && ($why=donation_block_reason($pdo,(int)$m['donor_id']))!==''){
      // the donor already donated elsewhere (4-month gap): do NOT record a second donation; release this place and keep searching
      $pdo->prepare("UPDATE donor_matches SET status='DECLINED',responded_at=NOW(),decline_reason=? WHERE match_id=? AND status='ACCEPTED'")->execute(['Donor not eligible to donate now - '.mb_substr($why,0,150),$m['match_id']]);
      restore_availability($pdo,(int)$m['donor_id']);
      notify($pdo,(int)$m['donor_user_id'],'REQUEST_UPDATED','Not able to donate',"Your place on emergency request #{$m['request_id']} was released: $why",$m['request_id'],$m['match_id']);
      close_request_history($pdo,$m['request_id'],$r['status'],$r['status'],"Match #{$m['match_id']} released: donor not eligible to donate ($why)");
      audit($pdo,$_SESSION['uid'],'UPDATE','donor_matches',$m['match_id'],'Completion refused: donor inside the donation interval; match released.');
      $pdo->commit(); $reopen=(int)$m['request_id'];
      flash('Donation NOT recorded. '.$why.' The donor was released from this request and the search for a replacement continues.','err');
    } elseif($m && $r && $m['status']==='ACCEPTED' && !in_array($r['status'],['FULFILLED','CANCELLED'],true)){
      record_donation($pdo,['donor_id'=>$m['donor_id'],'blood_group'=>$m['dbg'],'user_id'=>$m['donor_user_id']],$oid,'EMERGENCY',1,'Emergency request donation.',null,$m['match_id'],$_SESSION['uid']);
      $pdo->prepare("UPDATE donor_matches SET status='COMPLETED',completed_at=NOW() WHERE match_id=?")->execute([$m['match_id']]);
      $c=$pdo->prepare("SELECT COUNT(*) FROM donor_matches WHERE request_id=? AND status='COMPLETED'"); $c->execute([$m['request_id']]); $done=(int)$c->fetchColumn();
      $new=$done>=(int)$r['units_required']?'FULFILLED':'PARTIALLY_FULFILLED';
      $pdo->prepare("UPDATE emergency_requests SET status=?,closed_at=? WHERE request_id=?")->execute([$new,$new==='FULFILLED'?date('Y-m-d H:i:s'):null,$m['request_id']]);
      if($new==='FULFILLED'){
        $open=$pdo->prepare("SELECT m.match_id,m.status,m.donor_id,d.user_id FROM donor_matches m JOIN donors d ON d.donor_id=m.donor_id WHERE m.request_id=? AND m.match_id<>? AND m.status IN ('NOTIFIED','ACCEPTED')"); $open->execute([$m['request_id'],$m['match_id']]);
        foreach($open->fetchAll() as $other){
          $pdo->prepare("UPDATE donor_matches SET decline_reason=IF(status='ACCEPTED','Request fulfilled - you did not need to attend','Request fulfilled by other donor(s)'),status='DECLINED',responded_at=NOW() WHERE match_id=? AND status IN ('NOTIFIED','ACCEPTED')")->execute([$other['match_id']]);
          if($other['status']==='ACCEPTED') restore_availability($pdo,(int)$other['donor_id']);
          notify($pdo,(int)$other['user_id'],'REQUEST_FULFILLED','Request fulfilled','The emergency request has been fulfilled by other donor(s). You do not need to attend.',$m['request_id'],$other['match_id']);
        }
      }
      close_request_history($pdo,$m['request_id'],$r['status'],$new,"Donation completed ($done/{$r['units_required']}).");
      audit($pdo,$_SESSION['uid'],'COMPLETE','donor_matches',$m['match_id'],'Match completed and donation recorded.');
      $pdo->commit(); flash('Donation recorded and inventory updated.');
    } else { $pdo->rollBack(); flash('This match can no longer be completed: it may not be accepted, or the request is already fulfilled or cancelled.','err'); }
  }catch(Exception $e){ if($pdo->inTransaction()) $pdo->rollBack(); flash('Could not complete the match.','err'); }
  if($reopen) rematch_if_exhausted($pdo,$reopen);
  redirect('healthcare/matches.php');
}
if($_SERVER['REQUEST_METHOD']==='POST' && post('do')==='noshow'){
  try{ $pdo->beginTransaction();
    $s=$pdo->prepare("SELECT m.*,d.user_id donor_user_id,r.status rstatus,r.required_time FROM donor_matches m JOIN donors d ON d.donor_id=m.donor_id JOIN emergency_requests r ON r.request_id=m.request_id WHERE m.match_id=? AND r.organization_id=? FOR UPDATE");
    $s->execute([(int)post('match_id'),$oid]); $m=$s->fetch();
    if(!$m || $m['status']!=='ACCEPTED' || !in_array($m['rstatus'],OPEN_REQUEST,true)) throw new RuntimeException('This match can no longer be marked as a no-show.');
    if(strtotime($m['required_time'])>time()) throw new RuntimeException('A donor can only be marked as a no-show after the required time has passed.');
    $pdo->prepare("UPDATE donor_matches SET status='DECLINED',responded_at=NOW(),decline_reason='No-show (did not attend)' WHERE match_id=? AND status='ACCEPTED'")->execute([$m['match_id']]);
    $back=restore_availability($pdo,(int)$m['donor_id']);
    notify($pdo,(int)$m['donor_user_id'],'NO_SHOW','Marked as did not attend',"You were marked as not having attended emergency request #{$m['request_id']}.".($back?' Your availability was restored.':''),$m['request_id'],$m['match_id']);
    close_request_history($pdo,$m['request_id'],$m['rstatus'],$m['rstatus'],"Donor marked as no-show (match #{$m['match_id']}).");
    audit($pdo,$_SESSION['uid'],'UPDATE','donor_matches',$m['match_id'],'Accepted donor marked as no-show.');
    $pdo->commit(); flash('Donor marked as no-show. To find a replacement, edit the request with a new needed-by time, then use Re-match.');
  }catch(RuntimeException $e){ if($pdo->inTransaction()) $pdo->rollBack(); flash($e->getMessage(),'err');
  }catch(Throwable $e){ if($pdo->inTransaction()) $pdo->rollBack(); flash('Could not update the match.','err'); }
  redirect('healthcare/matches.php'.(get('request_id')?'?request_id='.(int)get('request_id'):''));
}
$rid=(int)get('request_id'); $st=get('status'); $fbg=get('blood_group'); $fur=get('urgency'); $ff=get('from'); $ft=get('to');
$okd=fn($x)=>$x===''||(bool)(DateTime::createFromFormat('Y-m-d',$x)&&DateTime::createFromFormat('Y-m-d',$x)->format('Y-m-d')===$x); if(!$okd($ff))$ff=''; if(!$okd($ft))$ft='';
$sql="SELECT m.*,r.blood_group rbg,r.urgency,r.status rstatus,d.blood_group,d.location_precise dprec,r.location_precise rprec,r.required_time,u.full_name,u.phone FROM donor_matches m JOIN emergency_requests r USING(request_id) JOIN donors d USING(donor_id) JOIN users u ON u.user_id=d.user_id WHERE r.organization_id=?"; $p=[$oid];
if($rid){$sql.=" AND m.request_id=?";$p[]=$rid;} if($st){$sql.=" AND m.status=?";$p[]=$st;}
if(in_array($fbg,BLOOD,true)){$sql.=" AND d.blood_group=?";$p[]=$fbg;} if(in_array($fur,['CRITICAL','HIGH','NORMAL'],true)){$sql.=" AND r.urgency=?";$p[]=$fur;}
if($ff){$sql.=" AND DATE(m.matched_at)>=?";$p[]=$ff;} if($ft){$sql.=" AND DATE(m.matched_at)<=?";$p[]=$ft;}
$s=$pdo->prepare($sql." ORDER BY m.matched_at DESC"); $s->execute($p);
$autorefresh=true; $title='Donor Matches'.($rid?" - Request #$rid":''); require '../includes/header.php'; ?>
<form class="filter"><input type="number" name="request_id" placeholder="Request #" value="<?= $rid?:'' ?>"><select name="status"><option value="">All statuses</option><?php foreach(['NOTIFIED','ACCEPTED','DECLINED','COMPLETED'] as $x) echo "<option ".($st===$x?'selected':'').">$x</option>"; ?></select><?= blood_select('blood_group',$fbg,true) ?><select name="urgency"><option value="">All urgency</option><?php foreach(['CRITICAL','HIGH','NORMAL'] as $x) echo "<option ".($fur===$x?'selected':'').">$x</option>"; ?></select><label class="dl">Donor notified from <input type="date" name="from" value="<?= h($ff) ?>"></label><label class="dl">Donor notified to <input type="date" name="to" value="<?= h($ft) ?>"></label><button class="btn">Filter</button></form>
<p class="muted">A donor's phone number is shown only after the donor accepts (privacy). Blood group filter = the donor's group.</p>
<div class="tw"><table><thead><tr><th>Request</th><th>Donor</th><th>Group</th><th>Phone</th><th>Distance</th><th>Score</th><th>Status</th><th>Responded</th><th></th></tr></thead><tbody>
<?php foreach($s as $m): ?><tr><td>#<?= $m['request_id'] ?> <?= h($m['rbg']) ?> <?= badge($m['urgency']) ?></td><td><?= h($m['full_name']) ?></td><td><?= h($m['blood_group']) ?><?= $m['blood_group']===$m['rbg']?'':' <span class="muted">(compatible)</span>' ?></td><td><?= in_array($m['status'],['ACCEPTED','COMPLETED'],true)?h($m['phone']):'<span class="muted">after accept</span>' ?></td><td><?= h(distance_label($m['distance_km'],$m['dprec']&&$m['rprec'])) ?></td><td><?= h($m['match_score']) ?></td><td><?= badge($m['status']) ?><?php if(!empty($m['decline_reason'])): ?><br><span class="muted"><?= h($m['decline_reason']) ?></span><?php endif; ?></td><td><?= h($m['responded_at']) ?></td>
<td><?php if($m['status']==='ACCEPTED' && in_array($m['rstatus'],OPEN_REQUEST,true)): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="complete"><input type="hidden" name="match_id" value="<?= $m['match_id'] ?>"><label class="chk"><input type="checkbox" name="screened" value="1" required> Screening passed on the day</label> <button class="btn sm" data-confirm="Donor has donated? Record donation.">Mark completed</button></form>
<?php if(strtotime($m['required_time'])<=time()): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="noshow"><input type="hidden" name="match_id" value="<?= $m['match_id'] ?>"><button class="btn sm alt" data-confirm="Mark this donor as a no-show? They will be released.">No-show</button></form><?php endif; ?><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php require '../includes/footer.php';
