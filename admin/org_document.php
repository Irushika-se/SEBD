<?php require_once __DIR__.'/../includes/auth.php'; require_role('ADMIN');
$id=(int)get('id'); $s=$pdo->prepare("SELECT certificate_file FROM healthcare_organizations WHERE organization_id=?"); $s->execute([$id]); $f=$s->fetchColumn();
$path=org_cert_valid_name($f) ? ORG_CERT_DIR.'/'.$f : null;
if(!$path || !is_file($path)){ http_response_code(404); die('Certificate not found.'); }
$ext=pathinfo($f,PATHINFO_EXTENSION); $type=['pdf'=>'application/pdf','jpg'=>'image/jpeg','png'=>'image/png'][$ext];
audit($pdo,$_SESSION['uid'],'VIEW','healthcare_organizations',$id,'Viewed registration certificate.');
header('Content-Type: '.$type); header('X-Content-Type-Options: nosniff'); header('Content-Length: '.filesize($path));
header('Content-Disposition: inline; filename="certificate-'.$id.'.'.$ext.'"'); header('Cache-Control: private, no-store');
readfile($path);
