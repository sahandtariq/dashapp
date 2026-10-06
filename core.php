<?php
$MODE=$MODE??'admin'; session_name('gb_'.$MODE); session_start(); require 'config.php';
$db=new PDO("mysql:host=$H;dbname=$D;charset=utf8mb4",$U,$P,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
function q($s,$a=[]){global $db;$t=$db->prepare($s);$t->execute($a);return $t;}
function e($s){return htmlspecialchars((string)$s);} function n($v){return number_format((float)$v);}
if(!q("select count(*) c from users")->fetch()['c'])q("insert into users(name,email,password,role) values(?,?,?,'admin')",['بەڕێوەبەر','admin@grandblvd.org',password_hash('ChangeMe123',PASSWORD_DEFAULT)]);
$me=$_SESSION['u']??null; $p=$_GET['p']??'home';
if($me&&(($MODE=='admin'&&$me['role']=='resident')||($MODE=='app'&&$me['role']!='resident'))){header('Location: '.($me['role']=='resident'?'app.php':'admin.php').'?p=home');exit;}
function can($x){global $me;return $me&&$me['role']!='resident'&&($me['role']=='admin'||in_array($x,explode(',',$me['permissions']??'')));}
function need($x){if(!can($x))die('403');}
function back(){header('Location: '.$_SERVER['REQUEST_URI']);exit;}
$L=['electricity'=>'کارەبا','gas'=>'غاز','service'=>'خزمەتگوزاری'];
$PERMS=['reports.view'=>'بینینی داهات','units.manage'=>'شوقەکان','invoices.manage'=>'پسوولە','payments.collect'=>'وەرگرتنی پارە','vehicles.manage'=>'سەیارەکان'];
$R=fn($k)=>trim($_POST[$k]??'');
if($_SERVER['REQUEST_METHOD']=='POST'){ $a=$R('a');
 if($a=='login'){$u=q("select * from users where email=?",[$R('email')])->fetch();
  if($u&&password_verify($_POST['password'],$u['password'])){
   if(($MODE=='admin')==($u['role']=='resident'))$err='ئەم ئەکاونتە بۆ '.($u['role']=='resident'?'ئەپی دانیشتوانە (app.php)':'سیستەمی بەڕێوبەرە (admin.php)').'یە';
   else{$_SESSION['u']=$u;header('Location: ?p=home');exit;}}
  else $err='ئیمەیڵ یان وشەی نهێنی هەڵەیە';}
 elseif($a=='logout'){session_destroy();header('Location: ?p=login');exit;}
 elseif($a=='unit_add'){need('units.manage');q("insert into units(name,number,owner_name,owner_phone,service_fee) values(?,?,?,?,?)",[$R('name'),$R('number'),$R('owner_name'),$R('owner_phone'),$R('service_fee')]);
  $id=$db->lastInsertId();q("insert into users(name,email,password,role,unit_id) values(?,?,?,'resident',?)",[$R('owner_name'),$R('email'),password_hash($R('password'),PASSWORD_DEFAULT),$id]);
  if($R('plate'))q("insert into vehicles(unit_id,plate,type) values(?,?,?)",[$id,$R('plate'),$R('type')]);back();}
 elseif($a=='unit_del'){need('units.manage');q("delete from units where id=?",[$R('id')]);back();}
 elseif($a=='inv_add'){need('invoices.manage');q("insert into invoices(unit_id,type,amount,due_date,period) values(?,?,?,?,?)",[$R('unit_id'),$R('type'),$R('amount'),$R('due_date'),substr($R('due_date'),0,7)]);back();}
 elseif($a=='inv_gen'){need('invoices.manage');$m=date('Y-m');foreach(q("select * from units where service_fee>0")->fetchAll() as $u)
  if(!q("select 1 from invoices where unit_id=? and type='service' and period=?",[$u['id'],$m])->fetch())q("insert into invoices(unit_id,type,amount,due_date,period) values(?,'service',?,LAST_DAY(CURDATE()),?)",[$u['id'],$u['service_fee'],$m]);back();}
 elseif($a=='inv_pay'){need('payments.collect');$i=q("select i.amount-coalesce((select sum(amount) from payments where invoice_id=i.id),0) r,unit_id from invoices i where id=?",[$R('id')])->fetch();
  $amt=min((float)($R('amount')?:$i['r']),$i['r']);if($amt>0)q("insert into payments(invoice_id,unit_id,amount,received_by) values(?,?,?,?)",[$R('id'),$i['unit_id'],$amt,$me['id']]);back();}
 elseif($a=='staff_add'&&$me['role']=='admin'){q("insert into users(name,email,password,role,permissions) values(?,?,?,'staff',?)",[$R('name'),$R('email'),password_hash($R('password'),PASSWORD_DEFAULT),implode(',',$_POST['perm']??[])]);back();}
 elseif($a=='staff_del'&&$me['role']=='admin'){q("delete from users where id=? and role='staff'",[$R('id')]);back();}
 elseif(in_array($a,['v_add','v_upd','v_del'])&&can('vehicles.manage')){
 if($a=='v_add')q("insert into vehicles(unit_id,plate,type) values(?,?,?)",[$R('unit_id'),$R('plate'),$R('type')]);
 if($a=='v_upd')q("update vehicles set plate=?,type=? where id=?",[$R('plate'),$R('type'),$R('id')]);
 if($a=='v_del')q("delete from vehicles where id=?",[$R('id')]);back();}
 elseif($me&&$me['role']=='resident'){$uid=$me['unit_id'];
  if($a=='veh_add')q("insert into vehicles(unit_id,plate,type) values(?,?,?)",[$uid,$R('plate'),$R('type')]);
  if($a=='veh_upd')q("update vehicles set plate=?,type=? where id=? and unit_id=?",[$R('plate'),$R('type'),$R('id'),$uid]);
  if($a=='veh_del')q("delete from vehicles where id=? and unit_id=?",[$R('id'),$uid]);back();}
}

if($p=='receipt'&&$me){$r=q("select p.*,u.number,u.owner_name,i.type from payments p join units u on u.id=p.unit_id join invoices i on i.id=p.invoice_id where p.id=?",[$_GET['id']??0])->fetch();
 if(!$r||($me['role']=='resident'&&$r['unit_id']!=$me['unit_id'])||($me['role']!='resident'&&!can('payments.collect')&&!can('reports.view')))die('403');
 echo "<!doctype html><html dir=rtl lang=ckb><meta charset=utf-8><link href='https://fonts.googleapis.com/css2?family=Poppins&family=Noto+Kufi+Arabic&display=swap' rel=stylesheet><body style='font-family:Poppins,'Noto Kufi Arabic',Tahoma;max-width:380px;margin:2rem auto;border:2px dashed #ef4444;padding:1.5rem;text-align:center'><h2 style='color:#ef4444;margin:0'>Grand Boulevard</h2><small>Nergis Park</small><hr><p>وەسڵی ژمارە #{$r['id']}</p><p>شوقە: ".e($r['number'])." — ".e($r['owner_name'])."</p><p>جۆر: {$L[$r['type']]}</p><h1>".n($r['amount'])." د.ع</h1><small>{$r['paid_at']}</small><hr><button onclick=print() style='padding:.5rem 1rem'>چاپکردن</button>";exit;}
if($p=='home'){ if(!$me)$p='login'; else {header('Location: ?p='.($me['role']=='resident'?'portal':(can('reports.view')?'dash':(can('units.manage')?'units':'invoices'))));exit;}}
if(!$me)$p='login';
?><!doctype html><html lang="ckb" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#0d0809"><link rel="manifest" href="manifest.json"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-status-bar-style" content="black-translucent"><link rel="apple-touch-icon" href="icon-192.png"><title>Grand Boulevard</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Kufi+Arabic:wght@300;400;600;800&family=Poppins:wght@300;400;600;800&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script><script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>:root{color-scheme:dark;--p:#ef4444;--p2:#991b1b;--bg:#0d0809;--c:#1a0f11;--t:#f5eaea;--b:#3a1a1e}
*{font-family:'Poppins','Noto Kufi Arabic',Tahoma,sans-serif}body{background:var(--bg);color:var(--t);margin:0}
.card{background:var(--c);border:1px solid var(--b);border-radius:1.25rem;padding:1.1rem;box-shadow:0 4px 20px rgba(239,68,68,.08)}
.btn{background:linear-gradient(135deg,var(--p),var(--p2));color:#fff;border-radius:.8rem;padding:.55rem 1.1rem;font-weight:600;transition:.2s}.btn:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(239,68,68,.4)}
.in{background:var(--c);color:var(--t);border:1px solid var(--b);border-radius:.8rem;padding:.55rem .8rem;width:100%;outline:0}.in:focus{border-color:var(--p);box-shadow:0 0 0 3px rgba(239,68,68,.2)}
.hero{background:linear-gradient(135deg,var(--p),var(--p2));color:#fff;border-radius:1.5rem;padding:1.6rem;box-shadow:0 12px 30px rgba(153,27,27,.35)}
table th{font-weight:600;font-size:.8rem;padding:.6rem}table td{padding:.75rem .5rem}table tr:hover td{background:rgba(239,68,68,.06)}
.bd{padding:.15rem .7rem;border-radius:99px;font-size:.78rem;background:#dcfce7;color:#166534}
.side a,.side button{display:flex;gap:.6rem;padding:.65rem .9rem;border-radius:.9rem;white-space:nowrap}.side a.on,.side a:hover,.side button:hover{background:rgba(239,68,68,.2);color:#fecaca}
.tabbar{position:fixed;bottom:0;left:0;right:0;z-index:30;display:flex;background:rgba(20,10,12,.92);backdrop-filter:blur(14px);border-top:1px solid var(--b);padding:.4rem .5rem calc(.4rem + env(safe-area-inset-bottom))}
.tabbar a{flex:1;display:flex;flex-direction:column;align-items:center;gap:2px;font-size:.7rem;padding:.4rem;border-radius:1rem;opacity:.6}.tabbar a span{font-size:1.4rem}.tabbar a.on{opacity:1;color:#ef4444;background:rgba(239,68,68,.12)}
.item{display:flex;align-items:center;gap:.8rem;padding:.8rem 0;border-top:1px solid var(--b)}.item:first-of-type{border:0}.ico{width:44px;height:44px;border-radius:14px;background:rgba(239,68,68,.15);display:flex;align-items:center;justify-content:center;font-size:1.3rem}
.plate{background:#f5f5f4;color:#111;border:3px solid #111;border-radius:.6rem;text-align:center;font-weight:800;letter-spacing:.15em;font-size:1.3rem;padding:.35rem .8rem;direction:ltr}
main{animation:fi .35s ease}@keyframes fi{from{opacity:0;transform:translateY(8px)}}.in,.btn{min-height:48px;font-size:16px}body{-webkit-tap-highlight-color:transparent}
</style>
<script>document.documentElement.dataset.t=localStorage.t||'';Chart.defaults.color='#c9a9ab';Chart.defaults.borderColor='#3a1a1e';function th(){let d=document.documentElement;d.dataset.t=d.dataset.t=='dark'?'':'dark';localStorage.t=d.dataset.t}
function fl(i){let v=i.value.toLowerCase();document.querySelectorAll('main .row,main tr:not(:first-child)').forEach(r=>r.style.display=(r.textContent+[...r.querySelectorAll('input')].map(x=>x.value).join(' ')).toLowerCase().includes(v)?'':'none')}</script></head><body>
<?php $nav=[['dash','reports.view','📊','داشبۆرد'],['units','units.manage','🏢','شوقەکان'],['vehicles','vehicles.manage','🚗','سەیارەکان'],['invoices','invoices.manage','🧾','پسوولەکان']];
if($me&&$me['role']!='resident'){?><aside class="side bg-[#140a0c] border-l border-[#3a1a1e] text-white md:fixed md:inset-y-0 md:right-0 md:w-64 p-3 flex md:flex-col gap-1 overflow-x-auto z-10">
<div class="px-3 py-2 md:mb-4 hidden md:block"><div class="text-xl font-extrabold">Grand Boulevard</div><div class="text-xs opacity-70">Nergis Park</div></div>
<?php foreach($nav as [$k,$pm,$ic,$l])if(can($pm))echo "<a href='?p=$k' class='".($p==$k?'on':'')."'>$ic $l</a>";if($me['role']=='admin')echo "<a href='?p=staff' class='".($p=='staff'?'on':'')."'>👥 کارمەندان</a>";?>
<div class="md:mt-auto flex md:flex-col gap-1"><form method="post"><input type="hidden" name="a" value="logout"><button>🚪 چوونەدەرەوە</button></form></div></aside><div class="md:mr-64">
<?php }elseif($me){?><div class="pb-24"><header class="sticky top-0 z-20 flex items-center justify-between px-5 pb-3 backdrop-blur bg-[#0d0809]/80 border-b border-[#3a1a1e]" style="padding-top:calc(env(safe-area-inset-top) + .75rem)"><div><div class="font-extrabold text-lg text-[#ef4444]">Grand Boulevard</div><div class="text-[11px] opacity-60">Nergis Park</div></div><a href="?p=account" class="w-10 h-10 rounded-full bg-[#ef4444]/20 flex items-center justify-center font-bold"><?=e(mb_substr($me['name'],0,1))?></a></header>
<?php }else{?><div><?php }?>
<main class="p-4 md:p-8 space-y-5 <?=($me&&$me['role']=='resident')?'max-w-2xl':'max-w-6xl'?> mx-auto">
<?php
if($p=='login'){?><form method="post" class="card max-w-sm mx-auto space-y-4 mt-16 p-8"><input type="hidden" name="a" value="login"><div class="text-center text-3xl font-extrabold text-[#ef4444] pt-4">Grand Boulevard</div><p class="text-center text-sm opacity-60">Nergis Park</p><input class="in" name="email" type="email" placeholder="ئیمەیڵ" required><input class="in" name="password" type="password" placeholder="وشەی نهێنی" required><?php if(!empty($err))echo "<p class='text-red-600'>$err</p>";?><button class="btn w-full">چوونەژوورەوە</button></form><?php }
elseif($p=='dash'){need('reports.view');
 $s=fn($w)=>(int)q("select coalesce(sum(amount),0) s from payments $w")->fetch()['s'];
 $due=(int)q("select coalesce(sum(i.amount),0)-(select coalesce(sum(amount),0) from payments) d from invoices i")->fetch()['d'];
 $c=[['داهاتی ئەمڕۆ',$s("where date(paid_at)=curdate()")],['داهاتی ئەم مانگە',$s("where date_format(paid_at,'%Y-%m')=date_format(now(),'%Y-%m')")],['داهاتی ئەمساڵ',$s("where year(paid_at)=year(now())")],['قەرزی ماوە',$due],['ژمارەی شوقە',q("select count(*) c from units")->fetch()['c']]];
 echo '<div class="grid grid-cols-2 md:grid-cols-5 gap-3">';foreach($c as [$l,$v])echo "<div class='card'><div class='text-sm text-gray-500'>$l</div><div class='text-2xl font-bold text-[#ef4444]'>".n($v)."</div></div>";echo '</div><div class="card"><h3 class="font-bold mb-2">داهاتی ١٢ مانگی دوایی</h3><canvas id="ch" height="110"></canvas></div>';
 $rows=array_reverse(q("select date_format(paid_at,'%Y-%m') m,sum(amount) s from payments group by m order by m desc limit 12")->fetchAll());
 echo '<script>new Chart(ch,{type:"bar",data:{labels:'.json_encode(array_column($rows,'m')).',datasets:[{data:'.json_encode(array_map('floatval',array_column($rows,'s'))).',backgroundColor:"#ef4444",borderRadius:8}]},options:{plugins:{legend:{display:false}}}})</script>';
 echo '<div class="card"><h3 class="font-bold mb-2">دوایین پارەدانەکان</h3><table class="w-full text-center">';
 foreach(q("select p.*,u.number,i.type from payments p join units u on u.id=p.unit_id join invoices i on i.id=p.invoice_id order by p.id desc limit 8")->fetchAll() as $r)echo "<tr class='border-t'><td>".e($r['number'])."</td><td>{$L[$r['type']]}</td><td>".n($r['amount'])."</td><td>{$r['paid_at']}</td><td><a class='text-[#ef4444]' target=_blank href='?p=receipt&id={$r['id']}'>🧾 وەسڵ</a></td></tr>";echo '</table></div>';}
elseif($p=='units'){need('units.manage');?>
<form method="post" class="card grid md:grid-cols-4 gap-2"><input type="hidden" name="a" value="unit_add">
<input class="in" name="name" placeholder="ناوی شوقە" required><input class="in" name="number" placeholder="ژمارەی شوقە" required><input class="in" name="owner_name" placeholder="خاوەن" required><input class="in" name="owner_phone" placeholder="مۆبایل">
<input class="in" name="service_fee" type="number" placeholder="خزمەتگوزاری مانگانە" required>
<input class="in" name="email" type="email" placeholder="ئیمەیڵی دانیشتوو" required><input class="in" name="password" placeholder="وشەی نهێنی" required><button class="btn md:col-span-4">زیادکردنی شوقە</button></form>
<input class="in" placeholder="🔍 گەڕان..." oninput="fl(this)"><div class="card overflow-x-auto"><table class="w-full text-center"><tr class="text-gray-500"><th>شوقە</th><th>ژمارە</th><th>خاوەن</th><th>سەیارەکان</th><th>قەرز</th><th></th></tr>
<?php foreach(q("select u.*,(select coalesce(sum(amount),0) from invoices where unit_id=u.id)-(select coalesce(sum(amount),0) from payments where unit_id=u.id) bal from units u order by number")->fetchAll() as $u){
 $v=array_map(fn($x)=>e($x['plate']).' ('.e($x['type']).')',q("select * from vehicles where unit_id=?",[$u['id']])->fetchAll());
 echo "<tr class='border-t'><td>".e($u['name'])."</td><td>".e($u['number'])."</td><td>".e($u['owner_name'])."<br><small>".e($u['owner_phone'])."</small></td><td>".implode('<br>',$v)."</td><td>".n($u['bal'])."</td><td><form method='post' onsubmit=\"return confirm('دڵنیای؟')\"><input type='hidden' name='a' value='unit_del'><input type='hidden' name='id' value='{$u['id']}'><button class='text-red-600'>سڕینەوە</button></form></td></tr>";}?></table></div><?php }
elseif($p=='invoices'){need('invoices.manage');?>
<div class="card"><form method="post" class="grid md:grid-cols-5 gap-2"><input type="hidden" name="a" value="inv_add"><select class="in" name="unit_id"><?php foreach(q("select * from units order by number")->fetchAll() as $u)echo "<option value='{$u['id']}'>".e($u['number'].' - '.$u['name'].' - '.$u['owner_name'])."</option>";?></select>
<select class="in" name="type"><?php foreach($L as $k=>$v)echo "<option value='$k'>$v</option>";?></select><input class="in" name="amount" type="number" placeholder="بڕ" required><input class="in" name="due_date" type="date" required><button class="btn">پسوولە دروست بکە</button></form>
<form method="post" class="mt-2"><input type="hidden" name="a" value="inv_gen"><button class="btn">دروستکردنی خزمەتگوزاری ئەم مانگە بۆ هەموو شوقەکان</button></form></div>
<input class="in" placeholder="🔍 گەڕان..." oninput="fl(this)"><div class="card overflow-x-auto"><table class="w-full text-center"><tr class="text-gray-500"><th>شوقە</th><th>جۆر</th><th>بڕ</th><th>ماوە</th><th>دوایین بەروار</th><th></th></tr>
<?php foreach(q("select i.*,u.number,i.amount-coalesce((select sum(amount) from payments where invoice_id=i.id),0) rem from invoices i join units u on u.id=i.unit_id order by i.id desc limit 200")->fetchAll() as $i){
 echo "<tr class='border-t'><td>".e($i['number'])."</td><td>{$L[$i['type']]}</td><td>".n($i['amount'])."</td><td>".n($i['rem'])."</td><td>{$i['due_date']}</td><td>";
 echo $i['rem']>0?(can('payments.collect')?"<form method='post'><input type='hidden' name='a' value='inv_pay'><input type='hidden' name='id' value='{$i['id']}'><button class='btn'>وەرگرتنی پارە</button></form>":'-'):"<span class='bd'>پارەدراوە</span>";echo "</td></tr>";}?></table></div><?php }
elseif($p=='vehicles'){need('vehicles.manage');?>
<form method="post" class="card grid md:grid-cols-4 gap-2"><input type="hidden" name="a" value="v_add"><h3 class="font-bold md:col-span-4">➕ زیادکردنی سەیارە — یەکەم خاوەنی شوقە هەڵبژێرە</h3><select class="in" name="unit_id"><?php foreach(q("select * from units order by number")->fetchAll() as $u)echo "<option value='{$u['id']}'>".e($u['number'].' - '.$u['name'].' - '.$u['owner_name'])."</option>";?></select><input class="in" name="plate" placeholder="ژمارەی سەیارە" required><input class="in" name="type" placeholder="جۆری سەیارە" required><button class="btn">زیادکردنی سەیارە</button></form>
<input class="in" placeholder="🔍 گەڕان بە ژمارە، جۆر، شوقە..." oninput="fl(this)"><div class="card">
<?php foreach(q("select v.*,u.number,u.owner_name from vehicles v join units u on u.id=v.unit_id order by u.number")->fetchAll() as $v)echo "<form method='post' class='row grid grid-cols-2 md:grid-cols-5 gap-2 items-center border-t py-2'><input type=hidden name=id value='{$v['id']}'><div>🏢 ".e($v['number'])."<br><small>".e($v['owner_name'])."</small></div><input class=in name=plate value='".e($v['plate'])."'><input class=in name=type value='".e($v['type'])."'><button name=a value=v_upd class=btn>پاشەکەوت</button><button name=a value=v_del class='text-red-600' onclick=\"return confirm('دڵنیای؟')\">سڕینەوە</button></form>";?></div><?php }
elseif($p=='staff'&&$me['role']=='admin'){?>
<form method="post" class="card grid md:grid-cols-3 gap-2"><input type="hidden" name="a" value="staff_add"><input class="in" name="name" placeholder="ناو" required><input class="in" name="email" type="email" placeholder="ئیمەیڵ" required><input class="in" name="password" placeholder="وشەی نهێنی" required>
<div class="md:col-span-3 flex flex-wrap gap-4"><?php foreach($PERMS as $k=>$l)echo "<label><input type='checkbox' name='perm[]' value='$k'> $l</label>";?></div><button class="btn md:col-span-3">زیادکردنی کارمەند</button></form>
<div class="card"><table class="w-full text-center"><?php foreach(q("select * from users where role='staff'")->fetchAll() as $s){
 $pp=implode('، ',array_map(fn($x)=>$PERMS[$x]??$x,array_filter(explode(',',$s['permissions']))));
 echo "<tr class='border-t'><td>".e($s['name'])."</td><td>".e($s['email'])."</td><td class='text-sm'>$pp</td><td><form method='post'><input type='hidden' name='a' value='staff_del'><input type='hidden' name='id' value='{$s['id']}'><button class='text-red-600'>سڕینەوە</button></form></td></tr>";}?></table></div><?php }
elseif($p=='portal'&&$me['role']=='resident'){$uid=$me['unit_id'];$u=q("select * from units where id=?",[$uid])->fetch();
 $inv=q("select i.*,i.amount-coalesce((select sum(amount) from payments where invoice_id=i.id),0) rem from invoices i where unit_id=? having rem>0 order by due_date",[$uid])->fetchAll();
 $ic=['electricity'=>'⚡','gas'=>'🔥','service'=>'🛠'];$tot=array_sum(array_column($inv,'rem'));$nv=q("select count(*) c from vehicles where unit_id=?",[$uid])->fetch()['c'];
 echo "<div><div class='opacity-70 text-sm'>سڵاو 👋</div><div class='text-xl font-extrabold'>".e($me['name'])."</div></div>";
 echo "<div class='hero text-center'><div class='opacity-80 text-sm'>شوقەی ".e($u['number'])." — ".e($u['name'])."</div><div class='text-5xl font-extrabold my-3'>".n($tot)."<span class='text-lg font-semibold'> د.ع</span></div><div class='text-sm opacity-90'>کۆی قەرزی ماوە</div><div class='mt-4 text-sm bg-black/25 rounded-2xl py-2.5'>".($inv?"⏰ نزیکترین بەرواری دان: ".$inv[0]['due_date']:"✓ هیچ قەرزێکت نییە")."</div></div>";
 echo "<div class='grid grid-cols-2 gap-3'><div class='card text-center'><div class='text-2xl font-extrabold'>".count($inv)."</div><div class='text-xs opacity-60'>پسوولەی ماوە</div></div><a href='?p=myvehicles' class='card text-center'><div class='text-2xl font-extrabold'>$nv</div><div class='text-xs opacity-60'>سەیارە</div></a></div>";
 echo "<div class='card'><h3 class='font-bold mb-1'>پسوولە ماوەکان</h3>";if(!$inv)echo '<p class="opacity-60 py-3">هیچ پسوولەیەک نییە ✓</p>';
 foreach($inv as $i){$d=(int)round((strtotime($i['due_date'])-strtotime(date('Y-m-d')))/86400);$late=$d<0;
  echo "<div class='item'><div class='ico'>{$ic[$i['type']]}</div><div class='flex-1'><div class='font-semibold'>{$L[$i['type']]}</div><div class='text-xs ".($late?'text-red-400':'opacity-60')."'>".($late?'دواکەوتووە '.abs($d).' ڕۆژ':($d==0?'ئەمڕۆ':"$d ڕۆژی ماوە"))." • {$i['due_date']}</div></div><div class='font-bold'>".n($i['rem'])."</div></div>";}
 echo "</div><div class='card'><h3 class='font-bold mb-1'>پارەدانەکانم</h3>";
 $h=q("select p.*,i.type from payments p join invoices i on i.id=p.invoice_id where p.unit_id=? order by p.id desc limit 10",[$uid])->fetchAll();if(!$h)echo '<p class="opacity-60 py-3">هێشتا پارەدانێک نییە</p>';
 foreach($h as $r)echo "<div class='item'><div class='ico'>✅</div><div class='flex-1'><div class='font-semibold'>{$L[$r['type']]}</div><div class='text-xs opacity-60'>".substr($r['paid_at'],0,10)."</div></div><div class='font-bold'>".n($r['amount'])."</div><a target=_blank href='?p=receipt&id={$r['id']}' class='text-xl'>🧾</a></div>";
 echo '</div>';}
elseif($p=='myvehicles'&&$me['role']=='resident'){$uid=$me['unit_id'];$u=q("select * from units where id=?",[$uid])->fetch();
 echo "<div><div class='text-xl font-extrabold'>🚗 سەیارەکانم</div><div class='text-sm opacity-60'>شوقەی ".e($u['number'])." — ".e($u['name'])."</div></div>";
 $vs=q("select * from vehicles where unit_id=?",[$uid])->fetchAll();if(!$vs)echo '<div class="card text-center opacity-70">هێشتا هیچ سەیارەیەکت تۆمار نەکراوە.</div>';
 foreach($vs as $v)echo "<form method='post' class='card space-y-3'><div class='plate'>".e($v['plate'])."</div><input type='hidden' name='id' value='{$v['id']}'><input class='in' name='plate' value='".e($v['plate'])."'><input class='in' name='type' value='".e($v['type'])."'><div class='flex gap-2'><button name='a' value='veh_upd' class='btn flex-1'>پاشەکەوت</button><button name='a' value='veh_del' class='flex-1 rounded-xl border border-red-500/50 text-red-400' onclick=\"return confirm('دڵنیای؟')\">سڕینەوە</button></div></form>";
 echo "<form method='post' class='card space-y-3'><input type='hidden' name='a' value='veh_add'><h3 class='font-bold'>➕ زیادکردنی سەیارە</h3><input class='in' name='plate' placeholder='ژمارەی سەیارە' required><input class='in' name='type' placeholder='جۆری سەیارە' required><button class='btn w-full'>زیادکردن</button></form>";}
elseif($p=='account'&&$me['role']=='resident'){$u=q("select * from units where id=?",[$me['unit_id']])->fetch();
 echo "<div class='card text-center'><div class='w-20 h-20 mx-auto rounded-full bg-[#ef4444]/20 flex items-center justify-center text-3xl font-extrabold'>".e(mb_substr($me['name'],0,1))."</div><div class='font-extrabold text-xl mt-3'>".e($me['name'])."</div><div class='opacity-60 text-sm'>".e($me['email'])."</div></div>";
 echo "<div class='card'><div class='item'><div class='ico'>🏢</div><div class='flex-1'>ناوی شوقە</div><b>".e($u['name'])."</b></div><div class='item'><div class='ico'>#</div><div class='flex-1'>ژمارەی شوقە</div><b>".e($u['number'])."</b></div><div class='item'><div class='ico'>📞</div><div class='flex-1'>مۆبایل</div><b dir='ltr'>".e($u['owner_phone'])."</b></div></div>";
 echo "<form method='post'><input type='hidden' name='a' value='logout'><button class='w-full rounded-xl border border-red-500/50 text-red-400 py-3 font-semibold'>🚪 چوونەدەرەوە</button></form>";}
else echo '<p>404</p>';
?></main></div><?php if($me&&$me['role']=='resident'){foreach([['portal','🏠','سەرەکی'],['myvehicles','🚗','سەیارەکانم'],['account','👤','هەژمار']] as [$k,$ic,$l])$tb[]="<a href='?p=$k' class='".($p==$k?'on':'')."'><span>$ic</span>$l</a>";echo '<nav class="tabbar">'.implode('',$tb).'</nav>';}?></body></html>
