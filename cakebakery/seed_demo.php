<?php
// OPTIONAL: fills the database with demo customers/orders so your screens look like the Milestone 2 mock-ups.
// Log in as admin, open /cakebakery/seed_demo.php once. Delete this file afterwards.
require_once 'includes/auth.php'; require_admin();
$pdo = db();
if ($pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn() > 0) exit('Orders already exist - demo seed skipped.');
$pdo->exec('ALTER TABLE orders AUTO_INCREMENT=1042');
$hash = password_hash('Password123!', PASSWORD_DEFAULT);
$mk = fn($n,$e,$p) => $pdo->prepare('INSERT INTO users(full_name,email,phone,password_hash) VALUES(?,?,?,?)')->execute([$n,$e,$p,$hash]) ? (int)$pdo->lastInsertId() : 0;
$amina = $mk('Amina Wanjiru','amina@example.com','254712345678'); $peter = $mk('Peter Kamau','peter@example.com','254722111222'); $grace = $mk('Grace Mwende','grace@example.com','254733444555');
$prod = fn($name) => (int)$pdo->query("SELECT product_id FROM products WHERE name=".$pdo->quote($name))->fetchColumn();
$ins = function($uid,$name,$phone,$needed,$odate,$status,$pay,$items,$msg=null) use ($pdo) {
    $total = 0; foreach ($items as [$pid,$q,$price]) $total += $q*$price;
    $pdo->prepare('INSERT INTO orders(user_id,order_date,date_needed,contact_name,contact_phone,total_amount,status) VALUES(?,?,?,?,?,?,?)')->execute([$uid,$odate,$needed,$name,$phone,$total,$status]);
    $oid = (int)$pdo->lastInsertId();
    foreach ($items as [$pid,$q,$price]) $pdo->prepare('INSERT INTO order_items(order_id,product_id,quantity,unit_price,cake_message) VALUES(?,?,?,?,?)')->execute([$oid,$pid,$q,$price,$msg]);
    $pdo->prepare('INSERT INTO payments(order_id,method,amount,status,paid_at) VALUES(?,?,?,?,?)')->execute([$oid,'mpesa',$total,$pay,$pay==='paid'?$odate:null]);
};
$choc=$prod('Chocolate Cake'); $van=$prod('Vanilla Cupcakes'); $bread=$prod('White Bread'); $red=$prod('Red Velvet Cake');
// The three orders shown in the mock-up
$ins($amina,'Amina Wanjiru','254712345678','2026-12-05 10:00:00',date('Y-m-d H:i:s'),'in_progress','paid',[[$choc,1,1800]],'Happy Birthday Amina');
$ins($peter,'Peter Kamau','254722111222','2026-12-06 10:00:00',date('Y-m-d H:i:s'),'new','pending',[[$van,2,600]]);
$ins($grace,'Grace Mwende','254733444555','2026-12-06 09:00:00',date('Y-m-d H:i:s'),'ready','paid',[[$bread,20,70]]);
// History for the sales chart (last 6 months)
mt_srand(7);
for ($m=5; $m>=1; $m--) for ($i=0; $i<mt_rand(22,40); $i++) {
    $d = date('Y-m-d H:i:s', strtotime("-$m months -".mt_rand(0,25)." days"));
    $pick = [[$choc,1,1800],[$van,mt_rand(1,3),600],[$bread,mt_rand(2,20),70],[$red,1,2200]][mt_rand(0,3)];
    $ins($amina,'Amina Wanjiru','254712345678',$d,$d,'delivered','paid',[$pick]);
}
echo 'Demo data added. Customers use password: Password123!  — now delete seed_demo.php.';
