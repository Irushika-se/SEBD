<?php require_once __DIR__.'/../includes/auth.php'; require_role('HEALTHCARE');
$o=my_org($pdo); $oid=(int)$o['organization_id'];
if($_SERVER['REQUEST_METHOD']==='POST') {
  $out=post('outcome'); $s=$pdo->prepare("SELECT * FROM donor_checkups WHERE checkup_id=? AND organization_id=? AND outcome='PENDING'"); $s->execute([(int)post('checkup_id'),$oid]); $c=$s->fetch();
  if($c && $c['scheduled_date']>date('Y-m-d')) flash('This checkup is scheduled for '.$c['scheduled_date'].'. You can record the outcome on or after that date.','err');
  elseif($c && in_array($out,['CLEARED','NO_SHOW'],true)){
    try {
      $pdo->beginTransaction();
      // Guarded UPDATE: only a still-PENDING checkup can be closed, so a double-click/second tab cannot overwrite the first result.
      $u=$pdo->prepare("UPDATE donor_checkups SET outcome=?,notes=?,actual_date=CURDATE(),recorded_by=? WHERE checkup_id=? AND organization_id=? AND outcome='PENDING'");
      $u->execute([$out,post('notes'),$_SESSION['uid'],$c['checkup_id'],$oid]);
      if($u->rowCount()!==1){ $pdo->rollBack(); flash('This checkup was already recorded. Ask the administrator to reopen it if the result must be corrected.','err'); redirect('healthcare/checkups.php'); }
      if($out!=='NO_SHOW') $pdo->prepare("UPDATE donors SET health_status=? WHERE donor_id=?")->execute([$out,$c['donor_id']]);
      $du=$pdo->prepare('SELECT user_id FROM donors WHERE donor_id=?'); $du->execute([$c['donor_id']]); $donorUid=$du->fetchColumn();
      $pdo->commit();
    } catch(Throwable $e) {
      if($pdo->inTransaction()) $pdo->rollBack();
      error_log('checkup record failed: '.$e->getMessage());
      flash('Could not save the checkup result. Nothing was changed - please try again.','err'); redirect('healthcare/checkups.php');
    }
    if($donorUid) notify($pdo,$donorUid,'CHECKUP_RESULT','Health checkup result recorded',"Your health checkup was recorded as $out. The administrator completes final donor verification or schedules a follow-up. Notes: ".(post('notes')?:'None provided'));
    $admins=$pdo->query("SELECT user_id FROM users WHERE role='ADMIN' AND status='ACTIVE'")->fetchAll(PDO::FETCH_COLUMN); foreach($admins as $au) notify($pdo,(int)$au,'CHECKUP_OUTCOME','Checkup outcome recorded',"Outcome $out recorded for donor #{$c['donor_id']} at checkup #{$c['checkup_id']}. Notes: ".(post('notes')?:'None provided'));
    audit($pdo,$_SESSION['uid'],'UPDATE','donor_checkups',$c['checkup_id'],"Checkup outcome $out."); flash('Checkup outcome recorded. The administrator will complete final verification.');
  } else flash('Invalid checkup.','err');
  redirect('healthcare/checkups.php');
}
$s=$pdo->prepare("SELECT c.*,u.full_name,d.blood_group FROM donor_checkups c JOIN donors d USING(donor_id) JOIN users u ON u.user_id=d.user_id WHERE c.organization_id=? ORDER BY c.outcome='PENDING' DESC,c.scheduled_date DESC"); $s->execute([$oid]); $checkups=$s->fetchAll();
$title='Donor Checkups'; require '../includes/header.php'; ?>
<p class="muted">Only the administrator schedules checkups. Your organization records the actual outcome after the donor attends. Record <b>Cleared</b> only if the donor meets the NBTS criteria: age 18&ndash;60 (first-time donors 18&ndash;55), weight over <?= DONOR_MIN_WEIGHT_KG ?> kg, haemoglobin at least <?= DONOR_MIN_HB ?> g/dl, at least <?= DONATION_GAP_MONTHS ?> months since the last donation, and not pregnant or ill.</p>
<h2>Scheduled checkups</h2><div class="tw"><table><thead><tr><th>Donor</th><th>Group</th><th>Scheduled</th><th>Outcome</th><th>Record outcome</th></tr></thead><tbody>
<?php foreach($checkups as $c): ?><tr><td><?= h($c['full_name']) ?></td><td><?= h($c['blood_group']) ?></td><td><?= h($c['scheduled_date']) ?></td><td><?= badge($c['outcome']) ?><br><small><?= h($c['notes']) ?></small></td><td><?php if($c['outcome']==='PENDING' && $c['scheduled_date']>date('Y-m-d')): ?><small class="muted">Available on <?= h($c['scheduled_date']) ?></small><?php elseif($c['outcome']==='PENDING'): ?><form method="post" data-confirm="Record this donor as {outcome}? Only the administrator can reopen a recorded checkup."><?= csrf_field() ?><input type="hidden" name="checkup_id" value="<?= (int)$c['checkup_id'] ?>"><select name="outcome"><option value="CLEARED">Cleared</option><option value="NO_SHOW">No-show</option></select><input name="notes" maxlength="2000" placeholder="Assessment notes (optional)"><button class="btn sm">Save result</button></form><?php endif; ?></td></tr><?php endforeach; ?><?php if(!$checkups): ?><tr><td colspan="5">No checkups assigned to this organization.</td></tr><?php endif; ?></tbody></table></div><?php require '../includes/footer.php'; ?>