<?php require_once __DIR__.'/../includes/auth.php'; require_role('HEALTHCARE');
$o=my_org($pdo); $oid=$o['organization_id'];
if($_SERVER['REQUEST_METHOD']==='POST'){
  require_verified_org($o);
  $rid=(int)post('request_id'); $do=post('do');
  try{
    $pdo->beginTransaction();
    $s=$pdo->prepare("SELECT * FROM emergency_requests WHERE request_id=? AND organization_id=? FOR UPDATE"); $s->execute([$rid,$oid]); $r=$s->fetch();
    if(!$r) throw new RuntimeException('Request not found.');
    if(!in_array($r['status'],OPEN_REQUEST,true)) throw new RuntimeException('This request is already '.strtolower($r['status']).' and can no longer be changed.');
    if($do==='cancel'){
      $reason=post('cancel_reason'); if(!in_array($reason,CANCEL_REASONS,true)) $reason='';
      $done=completed_units($pdo,$rid);
      $pdo->prepare("UPDATE emergency_requests SET status='CANCELLED',closed_at=NOW() WHERE request_id=?")->execute([$rid]);
      close_request_history($pdo,$rid,$r['status'],'CANCELLED','Cancelled by organization.'.($reason!==''?" Reason: $reason.":'')." Units donated before cancellation: $done/{$r['units_required']}.");
      notify_request_donors($pdo,$rid,'REQUEST_CANCELLED','Request cancelled',"Emergency request #$rid was cancelled.".($reason!==''?" Reason: $reason.":'')." You do not need to attend.",true);
      $pdo->prepare("UPDATE donor_matches SET decline_reason=IF(status='ACCEPTED','Request cancelled by the organization (you had accepted)','Request cancelled by the organization'),status='DECLINED',responded_at=NOW() WHERE request_id=? AND status IN ('NOTIFIED','ACCEPTED')")->execute([$rid]);
      audit($pdo,$_SESSION['uid'],'UPDATE','emergency_requests',$rid,'Request cancelled.'.($reason!==''?" Reason: $reason.":''));
      $pdo->commit(); flash('Request cancelled.');
    } elseif($do==='rematch'){
      if(strtotime($r['required_time'])<=time()) throw new RuntimeException('The required time has passed - edit the request with a new time before re-matching.');
      $pdo->commit(); $rd=rematch_rounds($pdo,$rid)+1; $n=run_matching($pdo,$rid,$rd,$mi);
      close_request_history($pdo,$rid,$r['status'],$r['status'],"Re-match round $rd: $n additional donor(s) notified (search widened".($n?'; '.match_info_text($mi,$r['blood_group']):'').").");
      flash($n.' additional donor(s) notified'.($n?' ('.match_info_text($mi,$r['blood_group']).')':' - no more eligible donors found').'.');
    } else { $pdo->rollBack(); }
  }catch(RuntimeException $e){ if($pdo->inTransaction()) $pdo->rollBack(); flash($e->getMessage(),'err');
  }catch(Throwable $e){ if($pdo->inTransaction()) $pdo->rollBack(); flash('Could not complete the action. Please try again.','err'); }
  redirect('healthcare/emergency_requests.php');
}
$st=get('status'); $ur=get('urgency'); $bg=get('blood_group'); $ff=get('from'); $ft=get('to');
$okd=fn($x)=>$x===''||(bool)(DateTime::createFromFormat('Y-m-d',$x)&&DateTime::createFromFormat('Y-m-d',$x)->format('Y-m-d')===$x); if(!$okd($ff))$ff=''; if(!$okd($ft))$ft='';
$sql="SELECT r.*,".AUTOCLOSED_SQL." autoclosed,(SELECT COUNT(*) FROM donor_matches m WHERE m.request_id=r.request_id) mc,(SELECT COUNT(*) FROM donor_matches m WHERE m.request_id=r.request_id AND m.status IN ('ACCEPTED','COMPLETED')) ac FROM emergency_requests r WHERE organization_id=?"; $p=[$oid];
foreach(['status'=>$st,'urgency'=>$ur,'blood_group'=>$bg] as $k=>$v) if($v && !($k==='status' && $v==='EXPIRED')){ $sql.=" AND r.$k=?"; $p[]=$v; }
if($st==='EXPIRED') $sql.=" AND r.status='CANCELLED' AND ".AUTOCLOSED_SQL; elseif($st==='CANCELLED') $sql.=" AND NOT ".AUTOCLOSED_SQL;
if($ff){ $sql.=" AND DATE(r.created_at)>=?"; $p[]=$ff; } if($ft){ $sql.=" AND DATE(r.created_at)<=?"; $p[]=$ft; }
$s=$pdo->prepare($sql." ORDER BY (r.status IN ('PENDING','MATCHING','PARTIALLY_FULFILLED')) DESC, FIELD(r.urgency,'CRITICAL','HIGH','NORMAL'), r.created_at DESC"); $s->execute($p);
$autorefresh=true; $title='Emergency Requests'; require '../includes/header.php'; ?>
<a class="btn" href="create_request.php">New request</a><br><br>
<form class="filter"><select name="status"><option value="">All statuses</option><?php foreach(['PENDING'=>'Pending','MATCHING'=>'Matching','PARTIALLY_FULFILLED'=>'Partially fulfilled','FULFILLED'=>'Fulfilled','CANCELLED'=>'Cancelled (by hospital)','EXPIRED'=>'Expired (auto-closed)'] as $x=>$xl) echo "<option value='$x' ".($st===$x?'selected':'').">$xl</option>"; ?></select>
<select name="urgency"><option value="">All urgency</option><?php foreach(['CRITICAL','HIGH','NORMAL'] as $x) echo "<option ".($ur===$x?'selected':'').">$x</option>"; ?></select><?= blood_select('blood_group',$bg,true) ?><label class="dl">Request created from <input type="date" name="from" value="<?= h($ff) ?>"></label><label class="dl">Request created to <input type="date" name="to" value="<?= h($ft) ?>"></label><button class="btn">Filter</button></form>
<div class="tw"><table><thead><tr><th>#</th><th>Group</th><th>Units</th><th>Urgency</th><th>Needed by</th><th>Location</th><th>Status</th><th>Matches</th><th></th></tr></thead><tbody>
<?php foreach($s as $r): $open=in_array($r['status'],['PENDING','MATCHING','PARTIALLY_FULFILLED']); ?><tr><td><?= $r['request_id'] ?></td><td><?= h($r['blood_group']) ?></td><td><?= $r['units_required'] ?></td><td><?= badge($r['urgency']) ?></td><td><?= h($r['required_time']) ?></td><td><?= h($r['location']) ?></td><td><?= $r['status']==='CANCELLED'&&$r['autoclosed']?badge('EXPIRED').' <span class="muted">auto-closed</span>':badge($r['status']) ?><?= $open&&strtotime($r['required_time'])<time()?' '.badge('OVERDUE'):'' ?></td><td><?= $r['ac'] ?>/<?= $r['mc'] ?></td>
<td><a class="btn sm alt" href="matches.php?request_id=<?= $r['request_id'] ?>">Matches</a>
<?php if($open): ?><a class="btn sm alt" href="edit_request.php?id=<?= $r['request_id'] ?>">Edit</a>
<form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="request_id" value="<?= $r['request_id'] ?>"><button name="do" value="rematch" class="btn sm alt">Re-match</button>
<select name="cancel_reason" title="Reason for cancelling"><option value="">Cancel reason...</option><?php foreach(CANCEL_REASONS as $cr) echo '<option>'.h($cr).'</option>'; ?></select>
<button name="do" value="cancel" class="btn sm" data-confirm="Cancel this request? Notified and accepted donors will be told.">Cancel</button></form><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
<script>/* refresh every 60 s (only when nothing is being typed/selected) so escalations and donor responses appear without a manual reload */
setInterval(function(){var a=document.activeElement;if(!a||a===document.body)location.reload();},60000);</script>
<?php require '../includes/footer.php';
