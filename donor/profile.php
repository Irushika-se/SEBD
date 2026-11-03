<?php require_once __DIR__.'/../includes/auth.php'; require_role('DONOR');
$d=my_donor($pdo);
if($_SERVER['REQUEST_METHOD']==='POST'){
  {
    $name=post('full_name'); $phone=post('phone'); $city=post('city'); $weight=(float)post('weight_kg');
    $errors=[];
    if(mb_strlen($name)<2||mb_strlen($name)>100) $errors[]='Enter a name between 2 and 100 characters.';
    if(!preg_match('/^[0-9]{10}$/',$phone)) $errors[]='Phone number must be exactly 10 digits.'; // same rule as registration, so one number cannot be stored in two formats
    if(!array_key_exists($city,SL_CITIES)) $errors[]='Choose a valid city.';
    if(!donor_weight_ok($weight)||$weight>200) $errors[]='Donors must weigh over '.DONOR_MIN_WEIGHT_KG.' kg (up to 200 kg).';
    if(mb_strlen(post('address'))>255||mb_strlen(post('recent_illness'))>255||mb_strlen(post('medication'))>255) $errors[]='Address, illness, and medication fields must be 255 characters or fewer.';
    $dup=$pdo->prepare('SELECT 1 FROM users WHERE phone=? AND user_id<>?'); $dup->execute([$phone,$_SESSION['uid']]); if($dup->fetch()) $errors[]='That phone number is already used by another account.';
    if($errors) flash(implode(' ',$errors),'err');
    else{
      [$lat,$lng,$prec,$gerr]=location_from_post($city);
      if($gerr){ flash($gerr,'err'); redirect('donor/profile.php'); }
      if(post('geo_lat')==='' && post('geo_clear')==='') { if($city===$d['city'] && $d['location_precise'] && $d['latitude']!==null){ $lat=(float)$d['latitude']; $lng=(float)$d['longitude']; $prec=1; } } // keep the saved precise position when the city did not change
      $rematch=[]; $reReview=false; $adminNote='';
      $pdo->beginTransaction();
      $fr=$pdo->prepare('SELECT recent_illness,medication,verification_status,health_status,availability_status FROM donors WHERE donor_id=? FOR UPDATE'); $fr->execute([$d['donor_id']]); $old=$fr->fetch(); // fresh, locked copy
      $norm=fn($v)=>mb_strtolower(trim(preg_replace('/\s+/',' ',(string)$v)));
      $newIll=post('recent_illness'); $newMed=post('medication');
      // newly declared (or changed, non-empty) illness / medication invalidates an earlier checkup result; clearing a field never grants clearance
      $declares=($norm($newIll)!=='' && $norm($newIll)!==$norm($old['recent_illness'])) || ($norm($newMed)!=='' && $norm($newMed)!==$norm($old['medication']));
      $reReview=$declares && ($old['verification_status']==='VERIFIED' || $old['health_status']==='CLEARED');
      $pdo->prepare('UPDATE users SET full_name=?,phone=? WHERE user_id=?')->execute([$name,$phone,$_SESSION['uid']]);
      $pdo->prepare('UPDATE donors SET address=?,city=?,latitude=?,longitude=?,location_precise=?,weight_kg=?,recent_illness=?,medication=? WHERE donor_id=?')->execute([post('address'),$city,$lat,$lng,$prec,$weight,post('recent_illness')?:null,post('medication')?:null,$d['donor_id']]);
      $chg=($norm($newIll)!==$norm($old['recent_illness'])||$norm($newMed)!==$norm($old['medication']))?' Illness: ['.mb_substr((string)$old['recent_illness'],0,60).'] -> ['.mb_substr($newIll,0,60).']; medication: ['.mb_substr((string)$old['medication'],0,60).'] -> ['.mb_substr($newMed,0,60).'].':'';
      if($reReview){
        // back to the normal workflow: administrator schedules a new checkup, the healthcare organization records it, the administrator verifies again
        $pdo->prepare("UPDATE donors SET health_status='PENDING',verification_status=IF(verification_status='VERIFIED','PENDING',verification_status),availability_status='UNAVAILABLE' WHERE donor_id=?")->execute([$d['donor_id']]);
        if($old['availability_status']==='AVAILABLE') $pdo->prepare("INSERT INTO donor_availability_history(donor_id,availability_status) VALUES(?,'UNAVAILABLE')")->execute([$d['donor_id']]);
        $rematch=release_donor_commitments($pdo,(int)$d['donor_id'],'Donor updated health information - re-screening needed','donor reported new health information and needs a new checkup');
        $adminNote=' Re-screening required: clearance reset to PENDING, donor set UNAVAILABLE.';
      }
      audit($pdo,$_SESSION['uid'],'UPDATE','donors',$d['donor_id'],'Donor profile and health questionnaire updated.'.$chg.$adminNote); $pdo->commit(); $_SESSION['name']=$name;
      if($reReview){
        foreach($rematch as $rr) rematch_if_exhausted($pdo,$rr);
        foreach($pdo->query("SELECT user_id FROM users WHERE role='ADMIN' AND status='ACTIVE'")->fetchAll(PDO::FETCH_COLUMN) as $au) notify($pdo,(int)$au,'VERIFICATION','Donor health information changed',"Donor #{$d['donor_id']} ({$name}) reported new illness/medication details. Clearance was reset - please schedule a new body checkup.");
        notify($pdo,(int)$_SESSION['uid'],'VERIFICATION','Re-screening required','You reported new health information. Your clearance is on hold and you are set to unavailable until you attend a new checkup arranged by the administrator.');
        flash('Profile updated. Because you reported new health information, your clearance is on hold and you are set to Unavailable until a healthcare professional re-checks you. Any open acceptance or campaign registration was released.');
      } else flash('Profile updated successfully.');
    }
  }
  redirect('donor/profile.php');
}
$title='My Profile'; $page_intro='Keep your contact details and health questionnaire accurate.'; require '../includes/header.php'; ?>
<div class="card"><div class="row"><div><strong>Account verification</strong><p><?= badge($d['verification_status']) ?></p></div><div><strong>Health status</strong><p><?= badge($d['health_status']) ?></p></div><div><strong>Availability</strong><p><?= badge($d['availability_status']) ?></p></div></div><a class="btn sm alt" href="verification-status.php">View verification progress</a></div>
<form method="post" class="card"><?= csrf_field() ?><h2>Contact details</h2><div class="row">
<div><label>Full name</label><input name="full_name" maxlength="100" value="<?= h($d['full_name']) ?>" required></div>
<div><label>Email</label><input value="<?= h($d['email']) ?>" disabled><small class="muted">Email changes are not available from this profile.</small></div>
<div><label>Phone</label><input name="phone" maxlength="10" pattern="[0-9]{10}" title="10 digits" value="<?= h($d['phone']) ?>" required></div>
<div><label>City</label><?= city_select('city',$d['city']) ?></div>
<div><label>Address</label><input name="address" maxlength="255" value="<?= h($d['address']) ?>"></div>
<div data-geobox><label>Exact location for matching</label><button type="button" class="btn sm alt" data-geo>Use my current location</button><input type="hidden" name="geo_lat"><input type="hidden" name="geo_lng"><small class="geo-status muted"><?= $d['location_precise']?'Your exact location is saved (about 100 m). Capture again to update it.':'Only your city centre is used, so donors in the same city look equally near.' ?></small><?php if($d['location_precise']): ?><label class="consent-row"><input type="checkbox" name="geo_clear" value="1"> Remove my exact location and use the city centre</label><?php endif; ?></div>
<div><label>Weight (kg)</label><input type="number" step="0.1" min="50.1" max="200" name="weight_kg" value="<?= h($d['weight_kg']) ?>" required></div>
</div><h2 style="margin-top:28px">Health questionnaire</h2><p class="muted">Keep these details truthful and current. Final donation eligibility is determined by qualified healthcare staff. If you report a <b>new</b> illness or medication, your current clearance is put on hold and you will need a new checkup; saving this form never grants clearance.</p><div class="row">
<div><label>Recent illness (if any)</label><input name="recent_illness" maxlength="255" value="<?= h($d['recent_illness']) ?>"></div>
<div><label>Current medication (if any)</label><input name="medication" maxlength="255" value="<?= h($d['medication']) ?>"></div>
<div><label>Blood group (registered)</label><input value="<?= h($d['blood_group']) ?>" disabled></div>
<div><label>Date of birth</label><input value="<?= h($d['date_of_birth']) ?>" disabled></div>
<div><label>Last recorded donation</label><input value="<?= h($d['last_donation_date']??'Not recorded') ?>" disabled></div></div><br><button class="btn">Save profile</button> <a class="btn alt" href="change-password.php">Change password</a></form>
<?php require '../includes/footer.php'; ?>
