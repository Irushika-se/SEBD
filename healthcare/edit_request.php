<?php require_once __DIR__.'/../includes/auth.php'; require_role('HEALTHCARE');
$o=my_org($pdo); require_verified_org($o); $oid=$o['organization_id']; expire_stock($pdo,$oid);
$rid=(int)get('id',$_POST['id']??0);
$s=$pdo->prepare("SELECT * FROM emergency_requests WHERE request_id=? AND organization_id=?"); $s->execute([$rid,$oid]); $r=$s->fetch();
if(!$r||!in_array($r['status'],OPEN_REQUEST,true)){ flash('Request cannot be edited.','err'); redirect('healthcare/emergency_requests.php'); }
$dn=completed_units($pdo,$rid); // units already donated against this request
$back='healthcare/edit_request.php?id='.$rid;
$curCity=nearest_city($r['latitude'],$r['longitude'],$o['city']); // city that matches the stored position
if($_SERVER['REQUEST_METHOD']==='POST'){
  $u=(int)post('units_required'); $urg=post('urgency'); $rt=str_replace('T',' ',post('required_time')); $loc=post('location'); $city=post('city');
  if($u<1||!in_array($urg,['CRITICAL','HIGH','NORMAL'],true)||!array_key_exists($city,SL_CITIES)){ flash('Invalid values.','err'); redirect($back); }
  if($rt===''||strtotime($rt)===false||strtotime($rt)<=time()||$loc===''){ flash('Required time must be in the future and location is required.','err'); redirect($back); }
  if(($urg!==$r['urgency']||substr($rt,0,16)!==substr($r['required_time'],0,16)) && ($e=urgency_time_error($urg,$rt))){ flash($e,'err'); redirect($back); }
  if($u<$dn){ flash("$dn unit(s) have already been donated for this request - units cannot be set below that.",'err'); redirect($back); }
  if($d2=duplicate_request_exists($pdo,$oid,$r['blood_group'],$loc,$rt,$rid)){ flash("Request #$d2 already asks for {$r['blood_group']} at the same location within ".(DUP_WINDOW_MIN/60)." hours of this time. Change the location (ward/bed) or the time.",'err'); redirect($back); }
  [$lat,$lng,$prec,$gerr]=location_from_post($city); if($gerr){ flash($gerr,'err'); redirect($back); }
  if(post('geo_lat')==='' && $r['latitude']!==null && $city===$curCity){ $lat=(float)$r['latitude']; $lng=(float)$r['longitude']; $prec=(int)$r['location_precise']; } // city not changed and no new position: keep what is stored
  $remaining=$u-$dn; // only the still-missing units need stock/donors
  $warn='';
  if($remaining>0){
    $mn=$pdo->prepare("SELECT minimum_stock FROM blood_inventory WHERE organization_id=? AND blood_group=?"); $mn->execute([$oid,$r['blood_group']]); $mn=(int)$mn->fetchColumn();
    if(stock_sufficient(usable_stock($pdo,$oid,$r['blood_group'],$rt),$mn,$remaining)){ $warn=' Note: usable stock now covers the remaining '.$remaining.' unit(s) without dropping below the minimum - consider cancelling the request and fulfilling from inventory.'; }
  }
  try{
    $pdo->beginTransaction();
    $q=$pdo->prepare("SELECT * FROM emergency_requests WHERE request_id=? AND organization_id=? FOR UPDATE"); $q->execute([$rid,$oid]); $cur=$q->fetch();
    if(!$cur||!in_array($cur['status'],OPEN_REQUEST,true)) throw new RuntimeException('This request was closed while you were editing and can no longer be changed.');
    $dn=completed_units($pdo,$rid); if($u<$dn) throw new RuntimeException("$dn unit(s) have already been donated for this request - units cannot be set below that."); $remaining=$u-$dn;
    $chg=[]; if((int)$cur['units_required']!==$u) $chg[]="units {$cur['units_required']} -> $u"; if($cur['urgency']!==$urg) $chg[]="urgency {$cur['urgency']} -> $urg";
    if(substr($cur['required_time'],0,16)!==substr($rt,0,16)) $chg[]='needed by '.substr($rt,0,16); if($cur['location']!==$loc) $chg[]="location $loc"; 
    $locChanged=round((float)$cur['latitude'],3)!==round((float)$lat,3)||round((float)$cur['longitude'],3)!==round((float)$lng,3);
    if($locChanged) $chg[]=$city!==$curCity?"city $city":'pinned location updated';
    $fulfilled=($remaining===0);
    $pdo->prepare("UPDATE emergency_requests SET units_required=?,urgency=?,required_time=?,location=?,latitude=?,longitude=?,location_precise=?,reason=?,notes=?,status=?,closed_at=? WHERE request_id=?")
        ->execute([$u,$urg,$rt,$loc,$lat,$lng,$prec,post('reason'),post('notes'),$fulfilled?'FULFILLED':$cur['status'],$fulfilled?date('Y-m-d H:i:s'):null,$rid]);
    close_request_history($pdo,$rid,$cur['status'],$fulfilled?'FULFILLED':$cur['status'],'Request edited'.($chg?': '.implode(', ',$chg):'').($fulfilled?" - units reduced to the $dn already donated, request fulfilled.":'.'));
    audit($pdo,$_SESSION['uid'],'UPDATE','emergency_requests',$rid,'Request edited'.($chg?': '.implode(', ',$chg):'').'.');
    if(!$fulfilled && $u<(int)$cur['units_required']){ // fewer units needed: release the most recent excess accepted donors
      $ex=$pdo->prepare("SELECT m.match_id,m.donor_id,d.user_id FROM donor_matches m JOIN donors d USING(donor_id) WHERE m.request_id=? AND m.status='ACCEPTED' ORDER BY m.responded_at DESC,m.match_id DESC"); $ex->execute([$rid]); $acc=$ex->fetchAll();
      foreach(array_slice($acc,0,max(0,count($acc)-$remaining)) as $x){
        $pdo->prepare("UPDATE donor_matches SET decline_reason='Fewer units needed - enough donors already accepted',status='DECLINED',responded_at=NOW() WHERE match_id=? AND status='ACCEPTED'")->execute([$x['match_id']]);
        restore_availability($pdo,(int)$x['donor_id']);
        notify($pdo,(int)$x['user_id'],'REQUEST_UPDATED','No longer needed',"Emergency request #$rid now needs fewer units and enough donors have already accepted. You do not need to attend.",$rid,$x['match_id']);
      }
    }
    if($fulfilled){ // nothing more needed: tell everyone still waiting (and free accepted donors) BEFORE closing their matches
      notify_request_donors($pdo,$rid,'REQUEST_FULFILLED','Request fulfilled','The emergency request has been fulfilled. You do not need to attend.',true);
      $pdo->prepare("UPDATE donor_matches SET decline_reason=IF(status='ACCEPTED','Request fulfilled - you did not need to attend','Request fulfilled by other donor(s)'),status='DECLINED',responded_at=NOW() WHERE request_id=? AND status IN ('NOTIFIED','ACCEPTED')")->execute([$rid]);
    } elseif($chg){
      notify_request_donors($pdo,$rid,'REQUEST_UPDATED','Request updated',"Emergency request #$rid was updated (".implode('; ',$chg).'). Please re-check the details and withdraw if you can no longer attend.');
    }
    $pdo->commit();
  }catch(RuntimeException $e){ if($pdo->inTransaction()) $pdo->rollBack(); flash($e->getMessage(),'err'); redirect('healthcare/emergency_requests.php');
  }catch(Throwable $e){ if($pdo->inTransaction()) $pdo->rollBack(); flash('Could not save the changes.','err'); redirect($back); }
  // the needed-by time moved EARLIER by 30+ min: donors asked before may no longer arrive in time, so ask the (nearer) ones not yet asked
  $earlier=strtotime($cur['required_time'])-strtotime($rt)>=1800;
  $n=(!$fulfilled&&($u>$cur['units_required']||$urg!==$cur['urgency']||$locChanged||$earlier))?run_matching($pdo,$rid):0;
  flash($fulfilled?'Request updated and marked fulfilled.':'Request updated.'.($n?" $n additional donor(s) notified.":'').$warn); redirect('healthcare/emergency_requests.php');
}
$title='Edit Request #'.$r['request_id']; require '../includes/header.php'; ?>
<?php if($dn): ?><div class="alert"><?= $dn ?> unit(s) already donated for this request. Units cannot go below <?= $dn ?>; setting it to exactly <?= $dn ?> marks the request fulfilled.</div><?php endif; ?>
<form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['request_id'] ?>"><div class="row">
<div><label>Blood group</label><input value="<?= h($r['blood_group']) ?>" disabled></div>
<div><label>Units required</label><input type="number" name="units_required" min="<?= max(1,$dn) ?>" value="<?= h($r['units_required']) ?>"></div>
<div><label>Urgency</label><select name="urgency"><?php foreach(['CRITICAL','HIGH','NORMAL'] as $x) echo "<option ".($r['urgency']===$x?'selected':'').">$x</option>"; ?></select></div>
<div><label>Required time</label><input type="datetime-local" name="required_time" value="<?= h(str_replace(' ','T',substr($r['required_time'],0,16))) ?>"></div>
<div><label>Location (hospital / ward)</label><input name="location" value="<?= h($r['location']) ?>"></div>
<div><label>City</label><?= city_select('city',$curCity) ?></div>
<div data-geobox><label>Exact patient location (optional)</label><button type="button" class="btn sm alt" data-geo>Use my current location</button><input type="hidden" name="geo_lat"><input type="hidden" name="geo_lng"><small class="geo-status muted"><?= $r['location_precise']?'A pinned location is saved. Capture again to replace it.':'City centre is used for ranking.' ?></small></div>
<div><label>Reason</label><input name="reason" value="<?= h($r['reason']) ?>"></div></div>
<label>Notes</label><textarea name="notes"><?= h($r['notes']) ?></textarea>
<p class="muted">Donors who were notified or accepted are told about any change to time, location, units or urgency.</p><button class="btn">Save</button> <a class="btn alt" href="emergency_requests.php">Back</a></form>
<?php require '../includes/footer.php';
