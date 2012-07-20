<?php
require '../../billin.php5';

session_start();
$billin = new BillinSession($_SESSION['XBS-SID']);

$customer = $billin->find_customer(array(id => $_SESSION['CUSTOMER_ID']));
$subscription = $billin->create_subscription(ref(-1));
$product = $billin->get_product_params($_POST['productid'], ref(-2));
$billin->assign_product($product, ref(-2));
$result = $billin->commit();
?>

<?
if ($result) {
?>
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=utf8">
	</head>
	<body>
		<p>Właśnie zostałeś abonentem!</p>

		<p>Oto faktura VAT za Twój zakup:</p>
		<?
		$billin->list_customer_invoices($customer);
		$billin->elt(0);
		$output = tmpfile();
		$billin->get_document_image($output, ref(-1), keyword('html'), keyword('original'));
		fseek($output, 0);
		$image = stream_get_contents($output);
		print($image);
		?>
		<p>Dziękujemy!</p>
	</body>
<html>
<?
}
?>
