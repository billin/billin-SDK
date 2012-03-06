<?
require '../../billin.php5';

## PHP session start
session_start();

## Billin session start
$billin = new BillinSession();
$_SESSION['XBS-SID'] = $billin->sid;
$billin->create_billing_data(array(fname => $_POST['fname'], 
                                   lname => $_POST['lname'],
                                   company_name => $_POST['company'],
                                   street => $_POST['street'],
                                   city => $_POST['city'],
                                   post_code => $_POST['post_code'],
                                   tax_id => $_POST['tax_id'],
                                   email => $_POST['email']));
$customer = $billin->create_customer();
$_SESSION['CUSTOMER_ID'] = $customer->id;
$billin->commit();
?>

<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf8">
        <title>Wybierz produkt</title>
    </head>
    <body>

	<p>Wybierz produkt z listy:</p>
	<form action='03-create-subscription.php' method="POST">
		<select name="productid">
		<?
			foreach ($billin->list_products() as $prod) {
				printf("\t\t<option value='%s'>%s</option>\n", $prod[id], $prod[name]);
			}
		?>
		</select>
		<p><input type="submit" value="Kup abonament"></p>
	</form>
    </body>
</html>
