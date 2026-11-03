<?php require_once __DIR__.'/../includes/auth.php'; require_role('HEALTHCARE');
$o=my_org($pdo); $oid=$o['organization_id'];
if($_SERVER['REQUEST_METHOD']==='POST'){
  require_verified_org($o);
  if(post('action')==='opening'){ // one-time opening stock for all blood groups at once (new organization)
    $chk=$pdo->prepare("SELECT (SELECT COALESCE(SUM(units_available),0) FROM blood_inventory WHERE organization_id=?)+(SELECT COUNT(*) FROM blood_batches WHERE organization_id=?)"); $chk->execute([$oid,$oid]);
    if((int)$chk->fetchColumn()>0){ flash('Opening stock can only be entered while your inventory is empty. Use "Update stock" to make changes.','err'); redirect('healthcare/inventory.php'); }
    $pu=is_array($_POST['units']??null)?$_POST['units']:[]; $pe=is_array($_POST['expiry']??null)?$_POST['expiry']:[]; $pm=is_array($_POST['min']??null)?$_POST['min']:[];
    $str=fn($a,$g)=>(isset($a[$g])&&is_string($a[$g]))?trim($a[$g]):'';
    $today=date('Y-m-d'); $maxExp=date('Y-m-d',strtotime('+'.MAX_SHELF_LIFE_DAYS.' days')); $rows=[]; $err='';
    foreach(BLOOD as $g){
      $q=$str($pu,$g); $mn=$str($pm,$g); $ex=$str($pe,$g);
      if($q===''&&$mn==='') continue;
      if(($q!==''&&!ctype_digit($q))||($mn!==''&&!ctype_digit($mn))){ $err="$g: enter whole numbers only (0 or more)."; break; }
      $q=(int)$q; $mn=$mn===''?0:(int)$mn;
      if($q>MAX_MANUAL_UNITS||$mn>MAX_MANUAL_UNITS){ $err="$g: enter at most ".MAX_MANUAL_UNITS.'.'; break; }
      if($q>0){
        if($ex==='') $ex=date('Y-m-d',strtotime('+'.SHELF_LIFE_DAYS.' days'));
        $okd=preg_match('/^\d{4}-\d{2}-\d{2}$/',$ex)&&strtotime($ex)!==false;
        if(!$okd||$ex<$today||$ex>$maxExp){ $err="$g: expiry date must be between today and $maxExp."; break; }
      }
      if($q===0&&$mn===0) continue;
      $rows[]=[$g,$q,$mn,$ex];
    }
    if(!$err&&!$rows) $err='Enter units or a minimum stock level for at least one blood group.';
    if($err){ flash($err,'err'); redirect('healthcare/inventory.php'); }
    try{ $pdo->beginTransaction();
      foreach($rows as [$g,$q,$mn,$ex]){
        if($q>0){ adjust_inventory($pdo,$oid,$g,$q,'RESTOCK',null,$_SESSION['uid'],$mn); add_batch($pdo,$oid,$g,$q,$ex,'RESTOCK'); }
        else adjust_inventory($pdo,$oid,$g,0,'ADJUSTMENT',null,$_SESSION['uid'],$mn);
      }
      audit($pdo,$_SESSION['uid'],'UPDATE','blood_inventory',null,'Opening stock entered for '.count($rows).' blood group(s).'); $pdo->commit();
      flash('Opening stock saved for '.count($rows).' blood group(s).');
    }catch(Throwable $e){ if($pdo->inTransaction()) $pdo->rollBack(); error_log('opening stock failed: '.$e->getMessage()); flash('Could not save the opening stock. Nothing was changed.','err'); }
    redirect('healthcare/inventory.php');
  }
  if(post('action')==='release'){ // screening result for a quarantined donation bag
    $bid=(int)post('batch_id'); $res=post('result'); $cg=post('confirmed_group');
    try{ $pdo->beginTransaction();
      $q=$pdo->prepare("SELECT * FROM blood_batches WHERE batch_id=? AND organization_id=? AND status='QUARANTINE' FOR UPDATE"); $q->execute([$bid,$oid]); $b=$q->fetch();
      if(!$b||!in_array($res,['CLEAR','REACTIVE'],true)) throw new RuntimeException('Batch not found or already processed.');
      if($res==='REACTIVE'){
        $pdo->prepare("UPDATE blood_batches SET status='REJECTED',released_at=NOW(),released_by=? WHERE batch_id=?")->execute([$_SESSION['uid'],$bid]);
        if($b['donation_id']){ // confidential: no test result in the app, just ask the donor to contact the blood bank
          $du=$pdo->prepare("SELECT d.user_id FROM donations n JOIN donors d ON d.donor_id=n.donor_id WHERE n.donation_id=?"); $du->execute([$b['donation_id']]); $du=(int)$du->fetchColumn();
          if($du) notify($pdo,$du,'DONATION_FOLLOWUP','Please contact the blood bank','Your recent donation could not be used. Please contact the blood bank that collected it for a confidential follow-up. Do not donate again until you have spoken to them.');
        }
        audit($pdo,$_SESSION['uid'],'UPDATE','blood_batches',$bid,"Screening reactive: {$b['units_remaining']} unit(s) of {$b['blood_group']} rejected, to be discarded (never counted as stock)."); $msg='Reactive result recorded. The bag was rejected and is listed below for disposal; it was never counted as stock.';
      } else {
        if(!in_array($cg,BLOOD,true)) throw new RuntimeException('Select the blood group confirmed by the lab.');
        if($b['expiry_date']<date('Y-m-d')) throw new RuntimeException('This bag has already passed its expiry date and cannot be released.');
        $note='';
        if($cg!==$b['blood_group']){ // the lab group wins over the group in the donor profile
          $pdo->prepare("UPDATE blood_batches SET blood_group=? WHERE batch_id=?")->execute([$cg,$bid]);
          if($b['donation_id']){ $pdo->prepare("UPDATE donations SET blood_group=? WHERE donation_id=?")->execute([$cg,$b['donation_id']]); $pdo->prepare("UPDATE donation_history SET blood_group=? WHERE donation_id=?")->execute([$cg,$b['donation_id']]); }
          $note=" Lab group {$cg} differs from the donor profile group {$b['blood_group']}: the donor's profile group was NOT changed; tell the administrator if the donor's registered group is wrong.";
        }
        adjust_inventory($pdo,$oid,$cg,(int)$b['units_remaining'],'DONATION',$b['donation_id']?:null,$_SESSION['uid']);
        $pdo->prepare("UPDATE blood_batches SET status='ACTIVE',released_at=NOW(),released_by=? WHERE batch_id=?")->execute([$_SESSION['uid'],$bid]);
        audit($pdo,$_SESSION['uid'],'UPDATE','blood_batches',$bid,"Screening clear: {$b['units_remaining']} unit(s) of $cg released to stock.$note"); $msg='Released to stock.'.$note;
      }
      $pdo->commit(); flash($msg);
    }catch(RuntimeException $e){ if($pdo->inTransaction()) $pdo->rollBack(); flash($e->getMessage(),'err');
    }catch(Exception $e){ if($pdo->inTransaction()) $pdo->rollBack(); flash('Could not update the batch.','err'); }
    redirect('healthcare/inventory.php');
  }
  if(post('action')==='dispose'){ // staff confirm that expired bags were physically discarded
    $note=mb_substr(post('disposal_note'),0,255); $bid=(int)post('batch_id');
    $sql="UPDATE blood_batches SET disposed_at=NOW(),disposed_by=?,disposal_note=? WHERE organization_id=? AND status IN ('EXPIRED','REJECTED') AND disposed_at IS NULL".($bid?' AND batch_id=?':'');
    $args=[$_SESSION['uid'],$note!==''?$note:null,$oid]; if($bid) $args[]=$bid;
    $u=$pdo->prepare($sql); $u->execute($args); $n=$u->rowCount();
    if($n){ audit($pdo,$_SESSION['uid'],'UPDATE','blood_batches',$bid?:null,"Confirmed physical disposal of $n expired batch(es).".($note!==''?" Note: $note":'')); flash("$n expired batch(es) marked as disposed."); }
    else flash('Nothing to confirm (already disposed or not found).','err');
    redirect('healthcare/inventory.php');
  }
  $bg=post('blood_group'); $type=post('change_type'); $q=(int)post('quantity'); $min=post('minimum_stock'); $exp=post('expiry_date');
  if(!in_array($bg,BLOOD)||!in_array($type,['EMERGENCY_USE','ADJUSTMENT','RESTOCK'])){ flash('Invalid input. Donated blood is recorded on the Donations page; it stays in quarantine until screening is cleared.','err'); redirect('healthcare/inventory.php'); }
  if(abs($q)>MAX_MANUAL_UNITS){ flash('Enter at most '.MAX_MANUAL_UNITS.' units per update.','err'); redirect('healthcare/inventory.php'); }
  if($q===0 && $min===''){ flash('Nothing to save: enter units or a minimum stock level.','err'); redirect('healthcare/inventory.php'); }
  if($type==='RESTOCK') $q=abs($q); elseif($type==='EMERGENCY_USE') $q=-abs($q);
  if($q>0){ // incoming units need an expiry date (default = standard shelf life)
    if($exp==='') $exp=date('Y-m-d',strtotime('+'.SHELF_LIFE_DAYS.' days'));
    $ok=preg_match('/^\d{4}-\d{2}-\d{2}$/',$exp) && strtotime($exp)!==false;
    if(!$ok||$exp<date('Y-m-d')||$exp>date('Y-m-d',strtotime('+'.MAX_SHELF_LIFE_DAYS.' days'))){ flash('Expiry date must be between today and '.MAX_SHELF_LIFE_DAYS.' days from today (whole blood / red cells last up to '.MAX_SHELF_LIFE_DAYS.' days).','err'); redirect('healthcare/inventory.php'); }
  }
  try{ $pdo->beginTransaction();
    expire_stock($pdo,$oid,$_SESSION['uid']); // discard anything already expired first
    $cur=$pdo->prepare("SELECT units_available FROM blood_inventory WHERE organization_id=? AND blood_group=? FOR UPDATE"); $cur->execute([$oid,$bg]); $cur=(int)$cur->fetchColumn();
    if($q<0 && -$q>$cur) throw new RuntimeException("Cannot remove ".(-$q)." unit(s): only $cur usable (non-expired) unit(s) of $bg in stock.");
    $m=$min!==''?max(0,(int)$min):null;
    if($q>0){ adjust_inventory($pdo,$oid,$bg,$q,$type,null,$_SESSION['uid'],$m); add_batch($pdo,$oid,$bg,$q,$exp,$type==='ADJUSTMENT'?'ADJUSTMENT':'RESTOCK'); }
    elseif($q<0){ adjust_inventory($pdo,$oid,$bg,$q,$type,null,$_SESSION['uid'],$m); consume_batches($pdo,$oid,$bg,-$q); }
    elseif($m!==null){ adjust_inventory($pdo,$oid,$bg,0,'ADJUSTMENT',null,$_SESSION['uid'],$m); }
    audit($pdo,$_SESSION['uid'],'UPDATE','blood_inventory',null,"Inventory $type $q for $bg."); $pdo->commit(); flash('Inventory updated.');
  }catch(RuntimeException $e){ $pdo->rollBack(); flash($e->getMessage(),'err');
  }catch(Exception $e){ $pdo->rollBack(); flash('Update failed.','err'); }
  redirect('healthcare/inventory.php');
}
expire_stock($pdo,$oid,$_SESSION['uid']);
$bgf=get('blood_group'); $lowf=get('alert')==='LOW'; $sql="SELECT * FROM blood_inventory WHERE organization_id=?"; $p=[$oid]; if($bgf){$sql.=" AND blood_group=?";$p[]=$bgf;} if($lowf) $sql.=" AND units_available<=minimum_stock";
$s=$pdo->prepare($sql." ORDER BY blood_group"); $s->execute($p); $rows=$s->fetchAll();
$have=array_column($rows,'blood_group'); if(!$lowf) foreach(BLOOD as $g){ if(($bgf===''||$bgf===$g) && !in_array($g,$have,true)) $rows[]=['blood_group'=>$g,'units_available'=>0,'minimum_stock'=>0,'last_updated'=>'never stocked']; } usort($rows,fn($a,$b)=>array_search($a['blood_group'],BLOOD)<=>array_search($b['blood_group'],BLOOD)); // every group is always listed, even if never stocked
$bats=active_batches($pdo,$oid); $warn=date('Y-m-d',strtotime('+'.EXPIRY_WARN_DAYS.' days'));
$soon=[]; foreach($bats as $b) if($b['expiry_date']<=$warn) $soon[$b['blood_group']]=($soon[$b['blood_group']]??0)+$b['units_remaining'];
$pend=pending_disposal($pdo,$oid);
$qq=$pdo->prepare("SELECT * FROM blood_batches WHERE organization_id=? AND status='QUARANTINE' ORDER BY expiry_date,batch_id"); $qq->execute([$oid]); $quar=$qq->fetchAll();
$disp=$pdo->prepare("SELECT b.*,u.full_name FROM blood_batches b LEFT JOIN users u ON u.user_id=b.disposed_by WHERE b.organization_id=? AND b.status IN ('EXPIRED','REJECTED') AND b.disposed_at IS NOT NULL ORDER BY b.disposed_at DESC LIMIT 10"); $disp->execute([$oid]); $disp=$disp->fetchAll();
$setup=$pdo->prepare("SELECT COUNT(*) FROM blood_inventory WHERE organization_id=? AND (units_available>0 OR minimum_stock>0)"); $setup->execute([$oid]); $needSetup=((int)$setup->fetchColumn()===0); // brand-new organization: nothing entered yet
$oc=$pdo->prepare("SELECT (SELECT COALESCE(SUM(units_available),0) FROM blood_inventory WHERE organization_id=?)+(SELECT COUNT(*) FROM blood_batches WHERE organization_id=?)"); $oc->execute([$oid,$oid]); $canOpening=((int)$oc->fetchColumn()===0); // empty inventory: the one-time opening stock form is offered
$title='Blood Inventory'; require '../includes/header.php'; ?>
<?php if($needSetup): ?><div class="alert err"><b>Set up your inventory first.</b> Your stock shows 0 because nothing has been entered yet. Enter the blood you currently hold for all groups in the <b>Opening stock</b> form below, with a <b>Minimum stock</b> level for each (for example 5). Groups you do not hold can stay empty.</div><?php endif; ?>
<?php if($canOpening): ?><form method="post" class="card"><?= csrf_field() ?><input type="hidden" name="action" value="opening"><b>Opening stock - enter everything you hold now (all blood groups at once)</b>
<p class="muted">Use this once, when you start using SEBD. Leave a group empty if you hold none of it. If the expiry date is left empty, <?= SHELF_LIFE_DAYS ?> days from today is used (maximum <?= MAX_SHELF_LIFE_DAYS ?> days). Afterwards, use <b>Update stock</b> for changes.</p>
<div class="tw"><table><thead><tr><th>Blood group</th><th>Units in stock now</th><th>Expiry date <span class="muted">(optional)</span></th><th>Minimum stock</th></tr></thead><tbody>
<?php foreach(BLOOD as $g): ?><tr><td><b><?= h($g) ?></b></td><td><input type="number" name="units[<?= h($g) ?>]" min="0" max="<?= MAX_MANUAL_UNITS ?>" placeholder="0"></td><td><input type="date" name="expiry[<?= h($g) ?>]" min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d',strtotime('+'.MAX_SHELF_LIFE_DAYS.' days')) ?>"></td><td><input type="number" name="min[<?= h($g) ?>]" min="0" max="<?= MAX_MANUAL_UNITS ?>" placeholder="e.g. 5"></td></tr><?php endforeach; ?></tbody></table></div>
<button class="btn">Save opening stock</button></form><?php endif; ?>
<form method="post" class="card"><?= csrf_field() ?><b>Update stock</b><div class="row">
<div><label>Blood group</label><?= blood_select('blood_group') ?></div>
<div><label>Change type</label><select name="change_type"><option>RESTOCK</option><option>EMERGENCY_USE</option><option>ADJUSTMENT</option></select></div>
<div><label>Units (adjustment may be negative)</label><input type="number" name="quantity" value="0"></div>
<div><label>Expiry date (incoming units; default <?= SHELF_LIFE_DAYS ?> days)</label><input type="date" name="expiry_date" min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d',strtotime('+'.MAX_SHELF_LIFE_DAYS.' days')) ?>"></div>
<div><label>Minimum stock (optional)</label><input type="number" name="minimum_stock" min="0"></div></div>
<p class="muted">Whole blood / red cells expire after about <?= SHELF_LIFE_DAYS ?> days (up to <?= MAX_SHELF_LIFE_DAYS ?> with additive solution); expired units are discarded automatically and the oldest units are used first. Set the minimum to roughly 3-7 days of your normal usage, higher for O+ and O-.</p>
<button class="btn">Save</button> <a class="btn alt" href="inventory_history.php">History</a></form>
<form class="filter"><?= blood_select('blood_group',$bgf,true) ?><select name="alert"><option value="">All stock levels</option><option value="LOW" <?= $lowf?'selected':'' ?>>Low stock only</option></select><button class="btn">Filter</button></form>
<div class="tw"><table><thead><tr><th>Group</th><th>Units</th><th>Expiring in <?= EXPIRY_WARN_DAYS ?> days</th><th>Minimum</th><th>Alert</th><th>Updated</th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td><?= h($r['blood_group']) ?></td><td><?= $r['units_available'] ?></td><td><?= !empty($soon[$r['blood_group']])?badge('EXPIRING').' '.$soon[$r['blood_group']]:'0' ?></td><td><?= $r['minimum_stock'] ?></td><td><?= $r['units_available']<=$r['minimum_stock']?badge('LOW').($r['minimum_stock']==0?' <small class="muted">(set a minimum)</small>':''):'OK' ?></td><td><?= h($r['last_updated']) ?></td></tr><?php endforeach; ?></tbody></table></div>
<h3 id="quarantine">Quarantine - waiting for screening results</h3>
<?php if($quar): ?><p class="muted">Collected blood is not usable stock until the lab finishes screening (HIV, hepatitis B/C, syphilis, malaria). Confirm the blood group the lab reports, then release or reject the bag.</p>
<div class="tw"><table><thead><tr><th>Group (donor profile)</th><th>Units</th><th>Collected</th><th>Expires</th><th>Lab result</th></tr></thead><tbody>
<?php foreach($quar as $b): ?><tr><td><?= h($b['blood_group']) ?></td><td><?= $b['units_remaining'] ?></td><td><?= h($b['collected_date']) ?></td><td><?= h($b['expiry_date']) ?></td><td><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="release"><input type="hidden" name="batch_id" value="<?= (int)$b['batch_id'] ?>">
<select name="confirmed_group"><?php foreach(BLOOD as $g) echo "<option".($g===$b['blood_group']?' selected':'').">".h($g)."</option>"; ?></select>
<button class="btn sm" name="result" value="CLEAR" data-confirm="Tests clear: release to stock?">Tests clear - release</button> <button class="btn sm alt" name="result" value="REACTIVE" data-confirm="Reactive result: reject this bag?">Reactive - reject</button></form></td></tr><?php endforeach; ?></tbody></table></div>
<?php else: ?><p class="muted">No bags waiting for screening.</p><?php endif; ?>
<h3>Batches (oldest expiry first)</h3>
<div class="tw"><table><thead><tr><th>Group</th><th>Units left</th><th>Collected</th><th>Expires</th><th>Days left</th><th>Source</th></tr></thead><tbody>
<?php foreach($bats as $b){ if($bgf && $b['blood_group']!==$bgf) continue; $dl=(int)((strtotime($b['expiry_date'])-strtotime(date('Y-m-d')))/86400); ?><tr><td><?= h($b['blood_group']) ?></td><td><?= $b['units_remaining'] ?></td><td><?= h($b['collected_date']) ?></td><td><?= h($b['expiry_date']) ?></td><td><?= $dl<=EXPIRY_WARN_DAYS?badge('EXPIRING').' ':'' ?><?= $dl ?></td><td><?= h($b['source']) ?></td></tr><?php } ?></tbody></table></div>
<h3 id="disposal">Expired / rejected - awaiting physical disposal</h3>
<?php if($pend): ?><div class="alert err">Remove these bags from the fridge and discard them following your biohazard procedure, then confirm below.</div>
<div class="tw"><table><thead><tr><th>Group</th><th>Units</th><th>Expired / rejected</th><th>Confirm</th></tr></thead><tbody>
<?php foreach($pend as $b): ?><tr><td><?= h($b['blood_group']) ?></td><td><?= $b['units_remaining'] ?></td><td><?= h($b['expiry_date']) ?></td><td><form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="dispose"><input type="hidden" name="batch_id" value="<?= (int)$b['batch_id'] ?>"><input name="disposal_note" maxlength="255" placeholder="Note (optional)"> <button class="btn sm">Confirm disposed</button></form></td></tr><?php endforeach; ?></tbody></table></div>
<form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="dispose"><button class="btn alt">Confirm all disposed</button></form>
<?php else: ?><p class="muted">No expired units waiting for disposal.</p><?php endif; ?>
<?php if($disp): ?><h3>Recently disposed</h3><div class="tw"><table><thead><tr><th>Group</th><th>Units</th><th>Expired on</th><th>Disposed</th><th>By</th><th>Note</th></tr></thead><tbody>
<?php foreach($disp as $b): ?><tr><td><?= h($b['blood_group']) ?></td><td><?= $b['units_remaining'] ?></td><td><?= h($b['expiry_date']) ?></td><td><?= h($b['disposed_at']) ?></td><td><?= h($b['full_name']) ?></td><td><?= h($b['disposal_note']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
<?php require '../includes/footer.php';
